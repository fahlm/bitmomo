"""Read-only Hyperliquid market selector dry-run.

This adapter subscribes to public L2/trade feeds and prints a rolling leaderboard.
It NEVER creates an Exchange client and has no order-submission path.

Optional finalized shadow/paper execution feedback can be tailed from JSONL via
--feedback-jsonl. Missing execution feedback remains UNKNOWN, so the selector fails
closed rather than inventing fill/maker/P10K economics from book snapshots.
"""

from __future__ import annotations

import argparse
import threading
import time
from typing import Dict

from hyperliquid.info import Info
from hyperliquid.utils import constants

from .config import DEFAULT_CONFIG
from .eligibility import evaluate_market
from .feedback import ExecutionFeedbackRouter, JsonlExecutionFeedbackTailer
from .observer import BookObservation, MarketObserver, TradeObservation
from .ranker import rank_markets
from .supervisor import MarketSupervisor


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


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--top", type=int, default=12)
    parser.add_argument("--coins", default="")
    parser.add_argument("--interval", type=int, default=30)
    parser.add_argument("--window", type=int, default=900)
    parser.add_argument(
        "--feedback-jsonl",
        default="",
        help=(
            "Optional JSONL written by a shadow/paper execution engine. "
            "No file means execution economics stay UNKNOWN."
        ),
    )
    args = parser.parse_args()

    cfg = DEFAULT_CONFIG
    info = Info(constants.MAINNET_API_URL, skip_ws=False)

    if args.coins.strip():
        discovered = [(x.strip(), 0.0) for x in args.coins.split(",") if x.strip()]
        # Populate 24h volumes from metadata when possible.
        metadata = dict(discover_markets(info, 500, 0))
        discovered = [(coin, metadata.get(coin, 0.0)) for coin, _ in discovered]
    else:
        discovered = discover_markets(info, args.top, cfg.min_day_volume_usd)

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

    def make_book_callback(coin):
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
            with lock:
                observers[coin].on_book(obs)
        return on_book

    def make_trade_callback(coin):
        def on_trades(message):
            rows = message.get("data") or []
            with lock:
                for trade in rows:
                    observers[coin].on_trade(
                        TradeObservation(
                            timestamp_ms=int(trade["time"]),
                            side=str(trade["side"]),
                            price=float(trade["px"]),
                            size=float(trade["sz"]),
                        )
                    )
        return on_trades

    for coin in observers:
        info.subscribe(
            {"type": "l2Book", "coin": coin, "fast": True},
            make_book_callback(coin),
        )
        info.subscribe(
            {"type": "trades", "coin": coin},
            make_trade_callback(coin),
        )

    print("BITMOMO HYPERLIQUID SELECTOR — DRY RUN")
    print("Trading: DISABLED | Wallet: NOT USED | Fail-closed: ENABLED")
    if feedback_tailer:
        print(f"Execution feedback: {feedback_tailer.path}")
    else:
        print("Execution feedback: NONE — economics remain UNKNOWN/WATCH")

    try:
        while True:
            time.sleep(args.interval)

            feedback_loaded = 0
            if feedback_tailer is not None:
                events = feedback_tailer.poll()
                with lock:
                    feedback_loaded = feedback_router.route_many(events)

            with lock:
                metrics = [observer.snapshot() for observer in observers.values()]

            results = [evaluate_market(item, cfg) for item in metrics]
            decision = supervisor.decide(results, inventory_flat=True)
            ranking = rank_markets(results)
            by_coin = {item.coin: item for item in metrics}

            print("\n" + "=" * 118)
            print(
                f"SUPERVISOR state={decision.state.value} active={decision.active_market or '-'} "
                f"action={decision.action} new_entries={'YES' if decision.allow_new_entries else 'NO'}"
            )
            if feedback_tailer is not None:
                print(
                    "FEEDBACK "
                    f"loaded={feedback_loaded} "
                    f"accepted={feedback_router.stats.accepted} "
                    f"dedup={feedback_router.stats.duplicate} "
                    f"unknown_coin={feedback_router.stats.unknown_coin} "
                    f"invalid={feedback_tailer.stats.invalid}"
                )
            print(
                "Rank Coin       Verdict      Score Spread  Queue/$100 Trades/m ExecN Maker   Markout5  P10K      T10K"
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
                    f"{fmt(None if m.maker_ratio is None else m.maker_ratio * 100, '%'):>7} "
                    f"{fmt(m.markout_5s_bps, 'bp'):>9} "
                    f"{fmt(m.p10k_usd, '$'):>9} "
                    f"{fmt(m.t10k_hours, 'h'):>8}"
                )
    finally:
        info.disconnect_websocket()


if __name__ == "__main__":
    main()
