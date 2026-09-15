"""24/7 autonomous Hyperliquid market scanner / shadow supervisor.

This process is intentionally READ-ONLY with respect to trading. It discovers the
liquid Hyperliquid universe, maintains one shared resilient public-data websocket,
builds rolling market/execution evidence, ranks qualified markets, and continuously
decides ACTIVE / STOP / DRAIN / SWITCH / IDLE through MarketSupervisor.

There is deliberately no Exchange client, wallet key, or order-submission path in
this module. A future live execution authority may consume the supervisor decision
only after an execution policy passes fresh unseen validation and separate risk
controls are integrated.
"""

from __future__ import annotations

import argparse
import json
import threading
import time
from collections import defaultdict
from pathlib import Path
from typing import Dict, Iterable

from hyperliquid.info import Info
from hyperliquid.utils import constants

from .config import DEFAULT_CONFIG
from .eligibility import evaluate_market
from .feedback import (
    ExecutionFeedbackEvent,
    ExecutionFeedbackRouter,
    append_feedback_event,
)
from .live_dry_run import depth, safe_disconnect
from .observer import BookObservation, MarketObserver, TradeObservation
from .ranker import rank_markets
from .runtime_state import (
    RuntimeStateStore,
    export_supervisor_state,
    restore_supervisor_state,
)
from .shadow_probe import ShadowProbeConfig, ShadowProbeEngine
from .supervisor import MarketSupervisor
from .transport import FeedHealth
from .universe import UniverseManager, UniverseSnapshot


def _fmt(value, suffix="", digits=2):
    if value is None:
        return "UNKNOWN"
    return f"{value:.{digits}f}{suffix}"


def load_historical_feedback(
    path: str | Path,
    *,
    policy_id: str,
) -> tuple[dict[str, list[ExecutionFeedbackEvent]], int]:
    """Load append-only shadow history once at startup, grouped by coin.

    Only the current policy_id is accepted. Mixing execution evidence from a prior
    policy would make the selector's economics invalid.
    """

    target = Path(path)
    grouped: dict[str, list[ExecutionFeedbackEvent]] = defaultdict(list)
    invalid = 0
    if not target.exists():
        return grouped, invalid

    with target.open("r", encoding="utf-8") as handle:
        for line in handle:
            if not line.strip():
                continue
            try:
                event = ExecutionFeedbackEvent.from_dict(json.loads(line))
            except Exception:
                invalid += 1
                continue
            if event.policy_id != policy_id:
                continue
            grouped[event.coin].append(event)

    for events in grouped.values():
        events.sort(key=lambda x: (x.timestamp_ms, x.attempt_id))
    return grouped, invalid


class AutonomousShadowRuntime:
    """One-process dynamic universe scanner with fail-closed supervision."""

    def __init__(
        self,
        *,
        scan_top: int,
        retain_buffer: int,
        universe_refresh_seconds: float,
        report_interval_seconds: float,
        transport_stale_seconds: float,
        state_path: str | Path,
        shadow_feedback_path: str | Path,
    ):
        self.cfg = DEFAULT_CONFIG
        self.report_interval_seconds = report_interval_seconds
        self.transport_stale_seconds = transport_stale_seconds
        self.state_store = RuntimeStateStore(state_path)
        self.shadow_feedback_path = Path(shadow_feedback_path)
        self.universe = UniverseManager(
            scan_top=scan_top,
            min_day_volume_usd=self.cfg.min_day_volume_usd,
            retain_buffer=retain_buffer,
            refresh_seconds=universe_refresh_seconds,
        )
        self.supervisor = MarketSupervisor(self.cfg)
        saved = self.state_store.load()
        restore_supervisor_state(self.supervisor, saved.get("supervisor") or {})

        self.lock = threading.RLock()
        self.current_info: Info | None = None
        self.health = FeedHealth.for_coins(
            (),
            stale_seconds=transport_stale_seconds,
            initial_backoff_seconds=2.0,
            max_backoff_seconds=30.0,
        )

        # Pools survive universe rotation within one process so execution evidence
        # is not lost merely because a market temporarily leaves the scan-top set.
        self.observers: Dict[str, MarketObserver] = {}
        self.probes: Dict[str, ShadowProbeEngine] = {}
        self.active_coins: tuple[str, ...] = ()

        self.probe_cfg = ShadowProbeConfig(
            target_notional_usd=self.cfg.target_order_notional
        )
        self.feedback_router = ExecutionFeedbackRouter(self.observers)
        self.historical_feedback, self.invalid_history = load_historical_feedback(
            self.shadow_feedback_path,
            policy_id=self.probe_cfg.policy_id,
        )
        self.hydrated_coins: set[str] = set()

    def _pinned_markets(self) -> tuple[str, ...]:
        values = [
            self.supervisor.active_market,
            self.supervisor.pending_market,
        ]
        return tuple(x for x in values if x)

    def _ensure_market(self, coin: str, day_volume: float) -> None:
        if coin not in self.observers:
            self.observers[coin] = MarketObserver(coin, self.cfg)
        self.observers[coin].set_day_volume(day_volume)

        if coin not in self.probes:
            self.probes[coin] = ShadowProbeEngine(coin, self.probe_cfg)

        if coin not in self.hydrated_coins:
            for event in self.historical_feedback.get(coin, []):
                self.feedback_router.route(event)
            self.hydrated_coins.add(coin)

    def _apply_universe(self, snapshot: UniverseSnapshot) -> None:
        volumes = snapshot.volume_by_coin()
        for coin in snapshot.coins:
            self._ensure_market(coin, volumes[coin])
        self.active_coins = snapshot.coins
        self.health.coins = self.active_coins

    def _persist_probe_events(self, events: Iterable[ExecutionFeedbackEvent]) -> None:
        for event in events:
            append_feedback_event(self.shadow_feedback_path, event)

    def _make_book_callback(self, coin: str, generation: int):
        def on_book(message):
            data = message.get("data")
            if not data:
                return
            levels = data.get("levels")
            if not levels or len(levels) != 2 or not levels[0] or not levels[1]:
                return
            bids, asks = levels
            obs = BookObservation(
                timestamp_ms=int(data.get("time", time.time() * 1000)),
                best_bid=float(bids[0]["px"]),
                best_ask=float(asks[0]["px"]),
                bid_size=float(bids[0]["sz"]),
                ask_size=float(asks[0]["sz"]),
                bid_depth5=depth(bids),
                ask_depth5=depth(asks),
            )
            emitted: list[ExecutionFeedbackEvent] = []
            with self.lock:
                if coin not in self.active_coins:
                    return
                if not self.health.note_book(generation, coin):
                    return
                observer = self.observers[coin]
                observer.on_book(obs)
                metrics = observer.snapshot(now_ms=obs.timestamp_ms)
                probe = self.probes[coin]
                probe.on_book(obs, metrics)
                emitted = probe.drain_events()
                self.feedback_router.route_many(emitted)
            self._persist_probe_events(emitted)

        return on_book

    def _make_trade_callback(self, coin: str, generation: int):
        def on_trades(message):
            rows = message.get("data") or []
            emitted: list[ExecutionFeedbackEvent] = []
            with self.lock:
                if coin not in self.active_coins or not self.health.accepts(generation):
                    return
                observer = self.observers[coin]
                probe = self.probes[coin]
                for trade in rows:
                    obs = TradeObservation(
                        timestamp_ms=int(trade["time"]),
                        side=str(trade["side"]),
                        price=float(trade["px"]),
                        size=float(trade["sz"]),
                    )
                    observer.on_trade(obs)
                    probe.on_trade(obs)
                    emitted.extend(probe.drain_events())
                self.feedback_router.route_many(emitted)
            self._persist_probe_events(emitted)

        return on_trades

    def _subscribe_generation(self, info: Info, generation: int) -> None:
        for coin in self.active_coins:
            info.subscribe(
                {"type": "l2Book", "coin": coin, "fast": True},
                self._make_book_callback(coin, generation),
            )
            info.subscribe(
                {"type": "trades", "coin": coin},
                self._make_trade_callback(coin, generation),
            )

    def _replace_connection(self, reason: str, *, fault: bool) -> None:
        old = self.current_info
        safe_disconnect(old)

        while True:
            if fault:
                with self.lock:
                    delay = self.health.register_reconnect()
                    reconnect_number = self.health.reconnects
                print(
                    f"RECONNECT reason={reason} number={reconnect_number} "
                    f"backoff={delay:.0f}s"
                )
                time.sleep(delay)
            else:
                delay = 0.0
                print(f"RESUBSCRIBE reason={reason} coins={len(self.active_coins)}")

            replacement = None
            try:
                replacement = Info(constants.MAINNET_API_URL, skip_ws=False)
                with self.lock:
                    generation = self.health.start_generation()
                self._subscribe_generation(replacement, generation)
                self.current_info = replacement
                print(
                    f"CONNECT generation={generation} watched={len(self.active_coins)}"
                )
                return
            except Exception as exc:
                print(f"CONNECTION_ERROR {type(exc).__name__}: {exc}")
                safe_disconnect(replacement)
                fault = True

    def _refresh_universe(self, *, force: bool = False) -> bool:
        if not force and not self.universe.due():
            return False
        if self.current_info is None:
            raise RuntimeError("current_info is not initialized")

        snapshot, changed = self.universe.refresh(
            self.current_info,
            pinned=self._pinned_markets(),
        )
        if not snapshot.coins:
            raise RuntimeError("universe discovery produced zero eligible markets")

        with self.lock:
            old = set(self.active_coins)
            self._apply_universe(snapshot)
            new = set(self.active_coins)

        added = sorted(new - old)
        removed = sorted(old - new)
        print(
            f"UNIVERSE markets={len(new)} changed={'YES' if changed else 'NO'} "
            f"added={','.join(added) or '-'} removed={','.join(removed) or '-'}"
        )
        return changed

    def _status_payload(self, decision, metrics, ranking) -> dict:
        metric_payload = {}
        for m in metrics:
            metric_payload[m.coin] = {
                "timestamp_ms": m.timestamp_ms,
                "day_volume_usd": m.day_volume_usd,
                "spread_median_bps": m.spread_median_bps,
                "bbo_queue_multiple": m.bbo_queue_multiple,
                "trade_rate_per_minute": m.trade_rate_per_minute,
                "execution_samples": m.execution_samples,
                "execution_age_seconds": m.execution_age_seconds,
                "fill_rate": m.fill_rate,
                "maker_ratio": m.maker_ratio,
                "markout_5s_bps": m.markout_5s_bps,
                "p10k_usd": m.p10k_usd,
                "t10k_hours": m.t10k_hours,
                "data_stale": m.data_stale,
            }

        return {
            "schema_version": 1,
            "updated_at_ms": int(time.time() * 1000),
            "mode": "SHADOW_ONLY",
            "mainnet_order_submission": False,
            "universe": list(self.active_coins),
            "transport": {
                "generation": self.health.generation,
                "healthy": self.health.healthy,
                "reconnects": self.health.reconnects,
                "max_book_age_seconds": self.health.max_book_age(),
            },
            "supervisor": export_supervisor_state(self.supervisor),
            "decision": {
                "state": decision.state.value,
                "active_market": decision.active_market,
                "candidate_market": decision.candidate_market,
                "allow_new_entries": decision.allow_new_entries,
                "action": decision.action,
                "reason": decision.reason,
            },
            "ranking": [
                {
                    "rank": r.rank,
                    "coin": r.coin,
                    "score": r.score,
                    "verdict": r.verdict.value,
                    "reasons": r.reasons,
                }
                for r in ranking
            ],
            "metrics": metric_payload,
            "feedback": {
                "policy_id": self.probe_cfg.policy_id,
                "accepted": self.feedback_router.stats.accepted,
                "duplicate": self.feedback_router.stats.duplicate,
                "unknown_coin": self.feedback_router.stats.unknown_coin,
                "invalid_history": self.invalid_history,
            },
        }

    def _report_once(self) -> None:
        with self.lock:
            metrics = [self.observers[c].snapshot() for c in self.active_coins]
            feed_healthy = self.health.healthy
            generation = self.health.generation
            reconnects = self.health.reconnects
            max_book_age = self.health.max_book_age()

        results = [evaluate_market(item, self.cfg) for item in metrics]
        decision = self.supervisor.decide(
            results,
            inventory_flat=True,  # shadow runtime owns no wallet/inventory
            hard_fault=None if feed_healthy else "feed_reconnecting",
        )
        ranking = rank_markets(results)
        by_coin = {m.coin: m for m in metrics}

        self.state_store.save(self._status_payload(decision, metrics, ranking))

        print("\n" + "=" * 124)
        print(
            f"HEALTH generation={generation} healthy={'YES' if feed_healthy else 'NO'} "
            f"max_book_age={max_book_age:.1f}s reconnects={reconnects} "
            f"universe={len(self.active_coins)}"
        )
        print(
            f"SUPERVISOR state={decision.state.value} "
            f"active={decision.active_market or '-'} candidate={decision.candidate_market or '-'} "
            f"action={decision.action} would_allow_entry="
            f"{'YES' if decision.allow_new_entries else 'NO'}"
        )
        print("EXECUTION mainnet=DISABLED wallet=NONE shadow_probes=ALL_WATCHED_MARKETS")
        print(
            "Rank Coin       Verdict      Score Spread  Queue/$100 Trades/m ExecN Fill   Maker   Markout5  P10K      T10K"
        )
        print("-" * 124)
        for ranked in ranking:
            m = by_coin[ranked.coin]
            print(
                f"{ranked.rank:>4} {ranked.coin:<10} {ranked.verdict.value:<12} "
                f"{ranked.score:>5.1f} "
                f"{_fmt(m.spread_median_bps, 'bp'):>7} "
                f"{_fmt(m.bbo_queue_multiple, 'x'):>10} "
                f"{_fmt(m.trade_rate_per_minute):>8} "
                f"{m.execution_samples:>5} "
                f"{_fmt(None if m.fill_rate is None else m.fill_rate * 100, '%'):>6} "
                f"{_fmt(None if m.maker_ratio is None else m.maker_ratio * 100, '%'):>7} "
                f"{_fmt(m.markout_5s_bps, 'bp'):>9} "
                f"{_fmt(m.p10k_usd, '$'):>9} "
                f"{_fmt(m.t10k_hours, 'h'):>8}"
            )

    def run_forever(self) -> None:
        self.current_info = Info(constants.MAINNET_API_URL, skip_ws=False)
        self._refresh_universe(force=True)
        self._replace_connection("initial_universe", fault=False)

        print("BITMOMO AUTONOMOUS HYPERLIQUID MARKET SUPERVISOR")
        print("Mode: SHADOW_ONLY | Mainnet orders: DISABLED | Wallet: NOT USED")
        print(
            f"Universe refresh={self.universe.refresh_seconds:.0f}s "
            f"scan_top={self.universe.scan_top} retain_buffer={self.universe.retain_buffer}"
        )
        print(
            f"Shadow policy={self.probe_cfg.policy_id} "
            f"feedback={self.shadow_feedback_path}"
        )

        next_report = time.monotonic()
        try:
            while True:
                now = time.monotonic()

                with self.lock:
                    stale = self.health.all_books_stale(now)
                if stale:
                    self._replace_connection("all_book_feeds_stale", fault=True)
                    next_report = time.monotonic()
                    continue

                if self.universe.due(now):
                    try:
                        changed = self._refresh_universe()
                    except Exception as exc:
                        # Discovery failure must not destroy the currently healthy
                        # scanner. Keep the prior universe and try again next loop.
                        print(f"UNIVERSE_ERROR {type(exc).__name__}: {exc}")
                        changed = False
                    if changed:
                        self._replace_connection("universe_changed", fault=False)
                        next_report = time.monotonic()

                if now >= next_report:
                    self._report_once()
                    next_report = now + self.report_interval_seconds

                time.sleep(min(1.0, self.report_interval_seconds))
        finally:
            safe_disconnect(self.current_info)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--scan-top", type=int, default=24)
    parser.add_argument("--retain-buffer", type=int, default=8)
    parser.add_argument("--universe-refresh", type=float, default=300.0)
    parser.add_argument("--report-interval", type=float, default=30.0)
    parser.add_argument("--transport-stale", type=float, default=60.0)
    parser.add_argument(
        "--state-json",
        default="data/runtime/autonomous_state.json",
    )
    parser.add_argument(
        "--shadow-feedback-jsonl",
        default="data/runtime/autonomous_shadow_feedback.jsonl",
    )
    args = parser.parse_args()

    runtime = AutonomousShadowRuntime(
        scan_top=args.scan_top,
        retain_buffer=args.retain_buffer,
        universe_refresh_seconds=args.universe_refresh,
        report_interval_seconds=args.report_interval,
        transport_stale_seconds=args.transport_stale,
        state_path=args.state_json,
        shadow_feedback_path=args.shadow_feedback_jsonl,
    )
    runtime.run_forever()


if __name__ == "__main__":
    main()
