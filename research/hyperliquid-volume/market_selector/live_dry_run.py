"""Read-only Hyperliquid market selector dry-run with resilient feed recovery.

This adapter subscribes to public L2/trade feeds and prints a rolling leaderboard.
It NEVER creates an Exchange client and has no order-submission path.

Execution economics can come from either:
1. an external shadow/paper producer via --feedback-jsonl; or
2. the built-in conservative standardized probe via --shadow-probe.

Missing execution feedback remains UNKNOWN. Transport recovery is generation-fenced:
callbacks from an old websocket are ignored after reconnect, and the supervisor stays
fail-closed until the replacement generation receives fresh book data.
"""

from __future__ import annotations

import argparse
import threading
import time
from pathlib import Path
from typing import Dict

from hyperliquid.info import Info
from hyperliquid.utils import constants

from .config import DEFAULT_CONFIG
from .eligibility import evaluate_market
from .feedback import (
    ExecutionFeedbackRouter,
    JsonlExecutionFeedbackTailer,
    append_feedback_event,
)
from .observer import BookObservation, MarketObserver, TradeObservation
from .ranker import rank_markets
from .shadow_probe import ShadowProbeConfig, ShadowProbeEngine
from .supervisor import MarketSupervisor
from .transport import FeedHealth


def discover_markets(info: Info, top_n: int, min_volume: float) -> list[tuple[str, float]]:
    meta, contexts = info.meta_and_asset_ctxs()
    markets = []
    for asset, ctx in zip(meta["universe"], contexts):
        volume = float(ctx.get("dayNtlVlm", 0) or 0)
        if volume >= min_volume:
            markets.append((asset["name"], volume))
    markets.sort(key=lambda item: item[1], reverse=True)
    return markets[:top_n]


def depth(levels, n=5) -> float:
    return sum(float(x["sz"]) for x in levels[:n])


def fmt(value, suffix="", digits=2):
    if value is None:
        return "UNKNOWN"
    return f"{value:.{digits}f}{suffix}"


def safe_disconnect(info: Info | None, timeout_seconds: float = 5.0) -> None:
    """Disconnect without letting a stuck SDK cleanup block the supervisor."""

    if info is None:
        return

    error_holder: list[BaseException] = []

    def _disconnect():
        try:
            info.disconnect_websocket()
        except BaseException as exc:  # cleanup must not take down research loop
            error_holder.append(exc)

    worker = threading.Thread(target=_disconnect, daemon=True)
    worker.start()
    worker.join(timeout_seconds)
    if worker.is_alive():
        print("DISCONNECT_TIMEOUT continuing with new generation")
    elif error_holder:
        print(f"DISCONNECT_ERROR {type(error_holder[0]).__name__}: {error_holder[0]}")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--top", type=int, default=12)
    parser.add_argument("--coins", default="")
    parser.add_argument("--interval", type=int, default=30)
    parser.add_argument("--window", type=int, default=900)
    parser.add_argument(
        "--transport-stale-seconds",
        type=float,
        default=60.0,
        help="Reconnect when all watched L2 book feeds are stale for this long.",
    )
    parser.add_argument(
        "--feedback-jsonl",
        default="",
        help=(
            "Optional JSONL written by an external shadow/paper execution engine. "
            "No file means those execution observations stay unavailable."
        ),
    )
    parser.add_argument(
        "--shadow-probe",
        action="store_true",
        help=(
            "Enable the built-in conservative JOIN/queue-ahead execution probe. "
            "Research only; never submits orders."
        ),
    )
    parser.add_argument(
        "--shadow-output-jsonl",
        default="",
        help="Optional append-only log for finalized built-in shadow-probe attempts.",
    )
    args = parser.parse_args()

    if (
        args.feedback_jsonl
        and args.shadow_output_jsonl
        and Path(args.feedback_jsonl).resolve() == Path(args.shadow_output_jsonl).resolve()
    ):
        parser.error("--feedback-jsonl and --shadow-output-jsonl must be different files")

    cfg = DEFAULT_CONFIG
    current_info = Info(constants.MAINNET_API_URL, skip_ws=False)

    if args.coins.strip():
        discovered = [(x.strip(), 0.0) for x in args.coins.split(",") if x.strip()]
        metadata = dict(discover_markets(current_info, 500, 0))
        discovered = [(coin, metadata.get(coin, 0.0)) for coin, _ in discovered]
    else:
        discovered = discover_markets(current_info, args.top, cfg.min_day_volume_usd)

    observers: Dict[str, MarketObserver] = {
        coin: MarketObserver(coin, cfg, window_seconds=args.window)
        for coin, _ in discovered
    }
    for coin, volume in discovered:
        observers[coin].set_day_volume(volume)

    lock = threading.Lock()
    supervisor = MarketSupervisor(cfg)
    feedback_router = ExecutionFeedbackRouter(observers)
    feedback_tailer = (
        JsonlExecutionFeedbackTailer(args.feedback_jsonl)
        if args.feedback_jsonl.strip()
        else None
    )
    probe_cfg = ShadowProbeConfig(target_notional_usd=cfg.target_order_notional)
    probes = (
        {coin: ShadowProbeEngine(coin, probe_cfg) for coin in observers}
        if args.shadow_probe
        else {}
    )
    health = FeedHealth.for_coins(
        observers.keys(),
        stale_seconds=args.transport_stale_seconds,
        initial_backoff_seconds=2.0,
        max_backoff_seconds=30.0,
    )

    def persist_probe_events(events):
        if not args.shadow_output_jsonl:
            return
        for event in events:
            append_feedback_event(args.shadow_output_jsonl, event)

    def make_book_callback(coin, generation):
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
            emitted = []
            with lock:
                if not health.note_book(generation, coin):
                    return
                observers[coin].on_book(obs)
                if coin in probes:
                    metrics = observers[coin].snapshot(now_ms=obs.timestamp_ms)
                    probes[coin].on_book(obs, metrics)
                    emitted = probes[coin].drain_events()
                    feedback_router.route_many(emitted)
            persist_probe_events(emitted)
        return on_book

    def make_trade_callback(coin, generation):
        def on_trades(message):
            rows = message.get("data") or []
            emitted = []
            with lock:
                if not health.accepts(generation):
                    return
                for trade in rows:
                    obs = TradeObservation(
                        timestamp_ms=int(trade["time"]),
                        side=str(trade["side"]),
                        price=float(trade["px"]),
                        size=float(trade["sz"]),
                    )
                    observers[coin].on_trade(obs)
                    if coin in probes:
                        probes[coin].on_trade(obs)
                        emitted.extend(probes[coin].drain_events())
                feedback_router.route_many(emitted)
            persist_probe_events(emitted)
        return on_trades

    def subscribe_generation(info: Info, generation: int) -> None:
        for coin in observers:
            info.subscribe(
                {"type": "l2Book", "coin": coin, "fast": True},
                make_book_callback(coin, generation),
            )
            info.subscribe(
                {"type": "trades", "coin": coin},
                make_trade_callback(coin, generation),
            )

    with lock:
        initial_generation = health.start_generation()
    subscribe_generation(current_info, initial_generation)
    print(f"CONNECT generation={initial_generation}")

    def reconnect(reason: str) -> None:
        nonlocal current_info

        print(f"TRANSPORT_STALE reason={reason}")
        safe_disconnect(current_info)

        while True:
            with lock:
                delay = health.register_reconnect()
                reconnect_number = health.reconnects
                next_generation = health.generation + 1
            print(
                f"RECONNECT number={reconnect_number} "
                f"backoff={delay:.0f}s next_generation={next_generation}"
            )
            time.sleep(delay)

            replacement = None
            try:
                replacement = Info(constants.MAINNET_API_URL, skip_ws=False)
                with lock:
                    generation = health.start_generation()
                subscribe_generation(replacement, generation)
                current_info = replacement
                print(f"CONNECT generation={generation}")
                return
            except Exception as exc:
                print(f"CONNECTION_ERROR {type(exc).__name__}: {exc}")
                safe_disconnect(replacement)

    print("BITMOMO HYPERLIQUID SELECTOR — RESILIENT DRY RUN")
    print("Trading: DISABLED | Wallet: NOT USED | Fail-closed: ENABLED")
    print(
        f"Transport watchdog: {args.transport_stale_seconds:.0f}s all-book stale | "
        "Reconnect: AUTOMATIC"
    )
    if args.shadow_probe:
        print(f"Built-in shadow probe: ENABLED ({probe_cfg.policy_id})")
        print("Probe status: PROVISIONAL RESEARCH, not validated alpha")
    else:
        print("Built-in shadow probe: DISABLED")
    if feedback_tailer:
        print(f"External execution feedback: {feedback_tailer.path}")
    else:
        print("External execution feedback: NONE")

    try:
        while True:
            time.sleep(args.interval)

            with lock:
                transport_stale = health.all_books_stale()
            if transport_stale:
                reconnect("all_book_feeds_stale")
                continue

            feedback_loaded = 0
            if feedback_tailer is not None:
                events = feedback_tailer.poll()
                with lock:
                    feedback_loaded = feedback_router.route_many(events)

            with lock:
                metrics = [observer.snapshot() for observer in observers.values()]
                generation = health.generation
                reconnects = health.reconnects
                feed_healthy = health.healthy
                max_book_age = health.max_book_age()

            results = [evaluate_market(item, cfg) for item in metrics]
            decision = supervisor.decide(
                results,
                inventory_flat=True,
                hard_fault=None if feed_healthy else "feed_reconnecting",
            )
            ranking = rank_markets(results)
            by_coin = {item.coin: item for item in metrics}

            print("\n" + "=" * 118)
            print(
                f"HEALTH generation={generation} healthy={'YES' if feed_healthy else 'NO'} "
                f"max_book_age={max_book_age:.1f}s reconnects={reconnects}"
            )
            print(
                f"SUPERVISOR state={decision.state.value} active={decision.active_market or '-'} "
                f"action={decision.action} new_entries={'YES' if decision.allow_new_entries else 'NO'}"
            )
            print(
                "FEEDBACK "
                f"external_loaded={feedback_loaded} "
                f"accepted={feedback_router.stats.accepted} "
                f"dedup={feedback_router.stats.duplicate} "
                f"unknown_coin={feedback_router.stats.unknown_coin} "
                f"invalid_external={feedback_tailer.stats.invalid if feedback_tailer else 0}"
            )
            print(
                "Rank Coin       Verdict      Score Spread  Queue/$100 Trades/m ExecN Fill   Maker   Markout5  P10K      T10K"
            )
            print("-" * 118)
            for ranked in ranking:
                m = by_coin[ranked.coin]
                print(
                    f"{ranked.rank:>4} {ranked.coin:<10} {ranked.verdict.value:<12} "
                    f"{ranked.score:>5.1f} "
                    f"{fmt(m.spread_median_bps, 'bp'):>7} "
                    f"{fmt(m.bbo_queue_multiple, 'x'):>10} "
                    f"{fmt(m.trade_rate_per_minute):>8} "
                    f"{m.execution_samples:>5} "
                    f"{fmt(None if m.fill_rate is None else m.fill_rate * 100, '%'):>6} "
                    f"{fmt(None if m.maker_ratio is None else m.maker_ratio * 100, '%'):>7} "
                    f"{fmt(m.markout_5s_bps, 'bp'):>9} "
                    f"{fmt(m.p10k_usd, '$'):>9} "
                    f"{fmt(m.t10k_hours, 'h'):>8}"
                )
    finally:
        safe_disconnect(current_info)


if __name__ == "__main__":
    main()
