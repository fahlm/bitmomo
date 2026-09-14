from __future__ import annotations

import math
import statistics
import time
from collections import deque
from dataclasses import dataclass
from typing import Deque, Optional

from .config import SelectorConfig
from .models import RollingMetrics


@dataclass(frozen=True)
class BookObservation:
    timestamp_ms: int
    best_bid: float
    best_ask: float
    bid_size: float
    ask_size: float
    bid_depth5: float
    ask_depth5: float


@dataclass(frozen=True)
class TradeObservation:
    timestamp_ms: int
    side: str
    price: float
    size: float


@dataclass(frozen=True)
class ExecutionObservation:
    timestamp_ms: int
    filled: bool
    maker_entry: bool
    maker_exit: bool
    markout_5s_bps: Optional[float]
    round_trip_volume_usd: Optional[float]
    pnl_usd: Optional[float]


class MarketObserver:
    """Rolling read-only market metrics for one Hyperliquid market.

    Raw market state intentionally uses a short window while empirical execution
    evidence uses a longer bounded window. This prevents a 15-minute spread/queue
    horizon from continuously deleting the sample needed to estimate fill, maker
    ratio, markout, P10K and T10K.
    """

    def __init__(
        self,
        coin: str,
        cfg: SelectorConfig,
        *,
        window_seconds: int | None = None,
    ):
        self.coin = coin
        self.cfg = cfg
        self.market_window_ms = int(
            (cfg.market_window_seconds if window_seconds is None else window_seconds)
            * 1000
        )
        self.execution_window_ms = int(cfg.execution_window_seconds * 1000)
        self.execution_max_samples = cfg.execution_max_samples
        self.started_ms = int(time.time() * 1000)
        self.books: Deque[BookObservation] = deque()
        self.trades: Deque[TradeObservation] = deque()
        self.executions: Deque[ExecutionObservation] = deque()
        self.day_volume_usd: Optional[float] = None
        self.hard_fault: Optional[str] = None

    def set_day_volume(self, value: Optional[float]) -> None:
        self.day_volume_usd = value

    def set_hard_fault(self, reason: Optional[str]) -> None:
        self.hard_fault = reason

    def on_book(self, obs: BookObservation) -> None:
        self.books.append(obs)
        self._trim_market(obs.timestamp_ms)
        self._trim_execution(obs.timestamp_ms)

    def on_trade(self, obs: TradeObservation) -> None:
        self.trades.append(obs)
        self._trim_market(obs.timestamp_ms)
        self._trim_execution(obs.timestamp_ms)

    def on_execution(self, obs: ExecutionObservation) -> None:
        self.executions.append(obs)
        self._trim_execution(obs.timestamp_ms)

    def _trim_market(self, now_ms: int) -> None:
        cutoff = now_ms - self.market_window_ms
        for queue in (self.books, self.trades):
            while queue and queue[0].timestamp_ms < cutoff:
                queue.popleft()

    def _trim_execution(self, now_ms: int) -> None:
        cutoff = now_ms - self.execution_window_ms
        while self.executions and self.executions[0].timestamp_ms < cutoff:
            self.executions.popleft()
        while len(self.executions) > self.execution_max_samples:
            self.executions.popleft()

    @staticmethod
    def _p90(values: list[float]) -> Optional[float]:
        if not values:
            return None
        values = sorted(values)
        index = min(len(values) - 1, math.ceil(len(values) * 0.90) - 1)
        return values[index]

    def snapshot(self, now_ms: Optional[int] = None) -> RollingMetrics:
        now_ms = now_ms or int(time.time() * 1000)
        self._trim_market(now_ms)
        self._trim_execution(now_ms)

        spreads: list[float] = []
        queues_usd: list[float] = []
        imbalances: list[float] = []
        micro_edges: list[float] = []

        for book in self.books:
            mid = (book.best_bid + book.best_ask) / 2
            if mid <= 0:
                continue

            spreads.append((book.best_ask - book.best_bid) / mid * 10_000)
            bid_queue_usd = book.bid_size * book.best_bid
            ask_queue_usd = book.ask_size * book.best_ask
            queues_usd.append((bid_queue_usd + ask_queue_usd) / 2)

            depth_total = book.bid_depth5 + book.ask_depth5
            if depth_total > 0:
                imbalances.append((book.bid_depth5 - book.ask_depth5) / depth_total)

            top_total = book.bid_size + book.ask_size
            if top_total > 0:
                microprice = (
                    book.best_ask * book.bid_size
                    + book.best_bid * book.ask_size
                ) / top_total
                micro_edges.append((microprice - mid) / mid * 10_000)

        buy_size = sum(t.size for t in self.trades if t.side == "B")
        sell_size = sum(t.size for t in self.trades if t.side == "A")
        flow_total = buy_size + sell_size
        aggressor_flow = (
            (buy_size - sell_size) / flow_total
            if flow_total > 0
            else None
        )

        observed_seconds = max(0.0, (now_ms - self.started_ms) / 1000)
        effective_trade_window_minutes = min(
            max(observed_seconds / 60, 1e-9),
            self.market_window_ms / 60_000,
        )
        trade_rate = (
            len(self.trades) / effective_trade_window_minutes
            if self.trades
            else 0.0
        )

        last_book_ms = self.books[-1].timestamp_ms if self.books else None
        last_trade_ms = self.trades[-1].timestamp_ms if self.trades else None
        last_execution_ms = self.executions[-1].timestamp_ms if self.executions else None
        book_age = (
            (now_ms - last_book_ms) / 1000
            if last_book_ms is not None
            else float("inf")
        )
        trade_age = (
            (now_ms - last_trade_ms) / 1000
            if last_trade_ms is not None
            else float("inf")
        )
        execution_age = (
            (now_ms - last_execution_ms) / 1000
            if last_execution_ms is not None
            else float("inf")
        )

        execution_samples = len(self.executions)
        fill_rate = None
        maker_ratio = None
        markout_5s = None
        volume_per_hour = None
        t10k = None
        p10k = None

        if execution_samples:
            filled = [x for x in self.executions if x.filled]
            fill_rate = len(filled) / execution_samples

            completed = [
                x
                for x in filled
                if x.round_trip_volume_usd is not None
            ]
            if completed:
                maker_legs = sum(
                    int(x.maker_entry) + int(x.maker_exit)
                    for x in completed
                )
                maker_ratio = maker_legs / (2 * len(completed))

                markouts = [
                    x.markout_5s_bps
                    for x in completed
                    if x.markout_5s_bps is not None
                ]
                if markouts:
                    markout_5s = statistics.mean(markouts)

                volume = sum(x.round_trip_volume_usd or 0.0 for x in completed)
                pnl_values = [x.pnl_usd for x in completed if x.pnl_usd is not None]

                # Restart-safe throughput accounting: when historical execution
                # events are rehydrated into a fresh observer, their evidence
                # horizon must not be clipped to the new process start time.
                # For an uninterrupted process, started_ms still counts any
                # pre-attempt observation/warm-up time as before.
                observed_execution_start_ms = min(
                    self.started_ms,
                    self.executions[0].timestamp_ms,
                )
                evidence_start_ms = max(
                    now_ms - self.execution_window_ms,
                    observed_execution_start_ms,
                )
                elapsed_hours = max(
                    (now_ms - evidence_start_ms) / 3_600_000,
                    1 / 3600,
                )
                volume_per_hour = volume / elapsed_hours
                if volume_per_hour > 0:
                    t10k = 10_000 / volume_per_hour
                if volume > 0 and pnl_values:
                    p10k = sum(pnl_values) / volume * 10_000

        avg_queue = statistics.mean(queues_usd) if queues_usd else None

        return RollingMetrics(
            coin=self.coin,
            timestamp_ms=now_ms,
            observation_seconds=observed_seconds,
            book_observations=len(self.books),
            trade_observations=len(self.trades),
            book_age_seconds=book_age,
            trade_age_seconds=trade_age,
            day_volume_usd=self.day_volume_usd,
            spread_median_bps=statistics.median(spreads) if spreads else None,
            spread_p90_bps=self._p90(spreads),
            bbo_queue_usd=avg_queue,
            bbo_queue_multiple=(
                avg_queue / self.cfg.target_order_notional
                if avg_queue is not None and self.cfg.target_order_notional > 0
                else None
            ),
            trade_rate_per_minute=trade_rate,
            book_imbalance=statistics.mean(imbalances) if imbalances else None,
            microprice_edge_bps=statistics.mean(micro_edges) if micro_edges else None,
            aggressor_flow=aggressor_flow,
            execution_samples=execution_samples,
            execution_age_seconds=execution_age,
            fill_rate=fill_rate,
            maker_ratio=maker_ratio,
            markout_5s_bps=markout_5s,
            volume_per_hour_usd=volume_per_hour,
            t10k_hours=t10k,
            p10k_usd=p10k,
            data_stale=book_age > self.cfg.max_book_age_seconds,
            hard_fault=self.hard_fault,
        )
