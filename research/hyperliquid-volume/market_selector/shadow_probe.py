from __future__ import annotations

from dataclasses import dataclass
from typing import Optional

from .feedback import ExecutionFeedbackEvent
from .models import RollingMetrics
from .observer import BookObservation, TradeObservation


@dataclass(frozen=True)
class ShadowProbeConfig:
    """Provisional standardized execution probe, not a validated alpha policy."""

    target_notional_usd: float = 100.0
    entry_lifetime_ms: int = 10_000
    exit_lifetime_ms: int = 30_000
    cooldown_ms: int = 15_000
    min_abs_book_imbalance: float = 0.05
    min_abs_aggressor_flow: float = 0.05
    maker_fee_bps: float = 1.5
    taker_fee_bps: float = 4.5
    policy_id: str = "shadow-probe-join-3of3-e10-x30-v0"


@dataclass
class _Attempt:
    attempt_id: str
    direction: int  # +1 long, -1 short
    created_ms: int
    entry_quote: float
    qty: float
    entry_queue_ahead_usd: float
    entry_consumed_usd: float = 0.0
    entry_fill_ms: Optional[int] = None
    exit_quote: Optional[float] = None
    exit_queue_ahead_usd: float = 0.0
    exit_consumed_usd: float = 0.0
    exit_quote_ms: Optional[int] = None
    exit_price: Optional[float] = None
    maker_exit: Optional[bool] = None
    markout_5s_bps: Optional[float] = None
    closed_ms: Optional[int] = None


class ShadowProbeEngine:
    """Conservative queue-ahead shadow execution probe for one market.

    It never sends an order. JOIN quotes are simulated at the current BBO. A maker
    fill requires observed opposite-aggressor notional at/through the quote to
    consume the full displayed queue ahead plus the hypothetical order notional.
    No cancellation credit is granted. If a filled position cannot maker-exit within
    the exit lifetime, it is marked as a taker fallback at the next available BBO.

    The probe exists to generate comparable execution observations across markets.
    Its policy is provisional and must not be confused with validated trading alpha.
    """

    def __init__(self, coin: str, cfg: ShadowProbeConfig | None = None):
        self.coin = coin
        self.cfg = cfg or ShadowProbeConfig()
        self.latest_book: Optional[BookObservation] = None
        self.attempt: Optional[_Attempt] = None
        self.last_attempt_end_ms: int = 0
        self.counter: int = 0
        self._events: list[ExecutionFeedbackEvent] = []

    def on_book(self, book: BookObservation, metrics: Optional[RollingMetrics] = None) -> None:
        self.latest_book = book
        self._advance_time(book.timestamp_ms)

        if self.attempt is not None and self.attempt.entry_fill_ms is not None:
            self._capture_markout(book)
            if self.attempt.exit_quote is None:
                self._place_exit_quote(book)
            self._maybe_finalize_closed(book.timestamp_ms)

        if self.attempt is None and metrics is not None:
            self._maybe_start(metrics, book)

    def on_trade(self, trade: TradeObservation) -> None:
        self._advance_time(trade.timestamp_ms)
        attempt = self.attempt
        if attempt is None:
            return

        trade_notional = max(0.0, trade.price * trade.size)
        if attempt.entry_fill_ms is None:
            if self._trade_hits_entry(trade, attempt):
                attempt.entry_consumed_usd += trade_notional
                threshold = attempt.entry_queue_ahead_usd + self.cfg.target_notional_usd
                if attempt.entry_consumed_usd >= threshold:
                    attempt.entry_fill_ms = trade.timestamp_ms
                    # Wait for the next L2 update before placing the hypothetical
                    # exit. Reusing the pre-fill book could create an optimistic
                    # stale exit quote immediately after the queue was consumed.
            return

        if attempt.exit_price is None and attempt.exit_quote is not None:
            if self._trade_hits_exit(trade, attempt):
                attempt.exit_consumed_usd += trade_notional
                threshold = attempt.exit_queue_ahead_usd + self.cfg.target_notional_usd
                if attempt.exit_consumed_usd >= threshold:
                    attempt.exit_price = attempt.exit_quote
                    attempt.maker_exit = True
                    attempt.closed_ms = trade.timestamp_ms
                    self._maybe_finalize_closed(trade.timestamp_ms)

    def drain_events(self) -> list[ExecutionFeedbackEvent]:
        events = self._events
        self._events = []
        return events

    def _maybe_start(self, metrics: RollingMetrics, book: BookObservation) -> None:
        now_ms = book.timestamp_ms
        if now_ms - self.last_attempt_end_ms < self.cfg.cooldown_ms:
            return

        direction = self._aligned_direction(metrics)
        if direction == 0:
            return

        quote = book.best_bid if direction > 0 else book.best_ask
        size = book.bid_size if direction > 0 else book.ask_size
        if quote <= 0 or size < 0:
            return

        self.counter += 1
        attempt_id = f"{self.coin}-{now_ms}-{self.counter}"
        self.attempt = _Attempt(
            attempt_id=attempt_id,
            direction=direction,
            created_ms=now_ms,
            entry_quote=quote,
            qty=self.cfg.target_notional_usd / quote,
            entry_queue_ahead_usd=size * quote,
        )

    def _aligned_direction(self, metrics: RollingMetrics) -> int:
        imbalance = metrics.book_imbalance
        micro = metrics.microprice_edge_bps
        flow = metrics.aggressor_flow
        if imbalance is None or micro is None or flow is None:
            return 0

        if (
            imbalance >= self.cfg.min_abs_book_imbalance
            and micro > 0
            and flow >= self.cfg.min_abs_aggressor_flow
        ):
            return 1
        if (
            imbalance <= -self.cfg.min_abs_book_imbalance
            and micro < 0
            and flow <= -self.cfg.min_abs_aggressor_flow
        ):
            return -1
        return 0

    def _advance_time(self, now_ms: int) -> None:
        attempt = self.attempt
        if attempt is None:
            return

        if attempt.entry_fill_ms is None:
            if now_ms - attempt.created_ms >= self.cfg.entry_lifetime_ms:
                self._events.append(
                    ExecutionFeedbackEvent(
                        attempt_id=attempt.attempt_id,
                        coin=self.coin,
                        timestamp_ms=now_ms,
                        filled=False,
                        policy_id=self.cfg.policy_id,
                        source="shadow_probe",
                    )
                )
                self._end_attempt(now_ms)
            return

        if attempt.exit_price is None and attempt.exit_quote_ms is not None:
            if now_ms - attempt.exit_quote_ms >= self.cfg.exit_lifetime_ms:
                if self.latest_book is None:
                    return
                # Conservative fallback: cross the current BBO.
                attempt.exit_price = (
                    self.latest_book.best_bid
                    if attempt.direction > 0
                    else self.latest_book.best_ask
                )
                attempt.maker_exit = False
                attempt.closed_ms = now_ms

        self._maybe_finalize_closed(now_ms)

    def _place_exit_quote(self, book: BookObservation) -> None:
        attempt = self.attempt
        if attempt is None or attempt.entry_fill_ms is None or attempt.exit_quote is not None:
            return

        if attempt.direction > 0:
            attempt.exit_quote = book.best_ask
            attempt.exit_queue_ahead_usd = book.ask_size * book.best_ask
        else:
            attempt.exit_quote = book.best_bid
            attempt.exit_queue_ahead_usd = book.bid_size * book.best_bid
        attempt.exit_quote_ms = max(book.timestamp_ms, attempt.entry_fill_ms)

    def _capture_markout(self, book: BookObservation) -> None:
        attempt = self.attempt
        if (
            attempt is None
            or attempt.entry_fill_ms is None
            or attempt.markout_5s_bps is not None
            or book.timestamp_ms - attempt.entry_fill_ms < 5_000
        ):
            return

        mid = (book.best_bid + book.best_ask) / 2
        if mid <= 0 or attempt.entry_quote <= 0:
            return
        attempt.markout_5s_bps = (
            attempt.direction * (mid - attempt.entry_quote) / attempt.entry_quote * 10_000
        )

    def _trade_hits_entry(self, trade: TradeObservation, attempt: _Attempt) -> bool:
        if attempt.direction > 0:
            return trade.side == "A" and trade.price <= attempt.entry_quote
        return trade.side == "B" and trade.price >= attempt.entry_quote

    def _trade_hits_exit(self, trade: TradeObservation, attempt: _Attempt) -> bool:
        assert attempt.exit_quote is not None
        if attempt.direction > 0:
            return trade.side == "B" and trade.price >= attempt.exit_quote
        return trade.side == "A" and trade.price <= attempt.exit_quote

    def _maybe_finalize_closed(self, now_ms: int) -> None:
        attempt = self.attempt
        if (
            attempt is None
            or attempt.entry_fill_ms is None
            or attempt.exit_price is None
            or attempt.maker_exit is None
            or attempt.markout_5s_bps is None
        ):
            return

        entry_notional = attempt.entry_quote * attempt.qty
        exit_notional = attempt.exit_price * attempt.qty
        gross_pnl = (
            attempt.direction
            * (attempt.exit_price - attempt.entry_quote)
            * attempt.qty
        )
        entry_fee = entry_notional * self.cfg.maker_fee_bps / 10_000
        exit_fee_bps = self.cfg.maker_fee_bps if attempt.maker_exit else self.cfg.taker_fee_bps
        exit_fee = exit_notional * exit_fee_bps / 10_000
        net_pnl = gross_pnl - entry_fee - exit_fee

        self._events.append(
            ExecutionFeedbackEvent(
                attempt_id=attempt.attempt_id,
                coin=self.coin,
                timestamp_ms=attempt.closed_ms or now_ms,
                filled=True,
                maker_entry=True,
                maker_exit=attempt.maker_exit,
                markout_5s_bps=attempt.markout_5s_bps,
                round_trip_volume_usd=entry_notional + exit_notional,
                pnl_usd=net_pnl,
                policy_id=self.cfg.policy_id,
                source="shadow_probe",
            )
        )
        self._end_attempt(now_ms)

    def _end_attempt(self, now_ms: int) -> None:
        self.attempt = None
        self.last_attempt_end_ms = now_ms
