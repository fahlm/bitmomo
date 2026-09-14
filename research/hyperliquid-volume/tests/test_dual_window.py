from market_selector.config import SelectorConfig
from market_selector.observer import (
    BookObservation,
    ExecutionObservation,
    MarketObserver,
    TradeObservation,
)


def book(ts):
    return BookObservation(
        timestamp_ms=ts,
        best_bid=100.0,
        best_ask=101.0,
        bid_size=1.0,
        ask_size=1.0,
        bid_depth5=5.0,
        ask_depth5=4.0,
    )


def execution(ts, filled=True):
    return ExecutionObservation(
        timestamp_ms=ts,
        filled=filled,
        maker_entry=filled,
        maker_exit=filled,
        markout_5s_bps=0.2 if filled else None,
        round_trip_volume_usd=200.0 if filled else None,
        pnl_usd=0.01 if filled else None,
    )


def test_execution_evidence_survives_short_market_window():
    cfg = SelectorConfig(
        warmup_seconds=0,
        market_window_seconds=900,
        execution_window_seconds=14_400,
        execution_max_samples=100,
    )
    observer = MarketObserver("VVV", cfg)
    start = 1_000_000
    observer.started_ms = start

    observer.on_book(book(start))
    observer.on_trade(TradeObservation(start, "B", 100.5, 1.0))
    observer.on_execution(execution(start))

    now = start + 1_800_000  # 30 minutes later
    observer.on_book(book(now))
    metrics = observer.snapshot(now_ms=now)

    assert metrics.book_observations == 1
    assert metrics.trade_observations == 0
    assert metrics.execution_samples == 1
    assert metrics.execution_age_seconds == 1800.0


def test_execution_evidence_is_bounded_by_sample_cap():
    cfg = SelectorConfig(
        warmup_seconds=0,
        execution_window_seconds=14_400,
        execution_max_samples=3,
    )
    observer = MarketObserver("VVV", cfg)
    observer.started_ms = 1_000_000

    for i in range(5):
        observer.on_execution(execution(1_000_000 + i * 1000))

    metrics = observer.snapshot(now_ms=1_005_000)
    assert metrics.execution_samples == 3


def test_execution_evidence_expires_on_long_window():
    cfg = SelectorConfig(
        warmup_seconds=0,
        execution_window_seconds=3600,
        execution_max_samples=100,
    )
    observer = MarketObserver("VVV", cfg)
    start = 1_000_000
    observer.started_ms = start
    observer.on_execution(execution(start))

    metrics = observer.snapshot(now_ms=start + 3_600_001)
    assert metrics.execution_samples == 0
    assert metrics.execution_age_seconds == float("inf")
