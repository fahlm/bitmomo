import pytest

from market_selector.models import RollingMetrics
from market_selector.observer import BookObservation, TradeObservation
from market_selector.shadow_probe import ShadowProbeConfig, ShadowProbeEngine


def bullish_metrics(coin="VVV"):
    return RollingMetrics(
        coin=coin,
        timestamp_ms=100_000,
        book_imbalance=0.20,
        microprice_edge_bps=0.10,
        aggressor_flow=0.25,
    )


def bearish_metrics(coin="VVV"):
    return RollingMetrics(
        coin=coin,
        timestamp_ms=100_000,
        book_imbalance=-0.20,
        microprice_edge_bps=-0.10,
        aggressor_flow=-0.25,
    )


def book(ts, bid=100.0, ask=101.0, bid_size=1.0, ask_size=1.0):
    return BookObservation(
        timestamp_ms=ts,
        best_bid=bid,
        best_ask=ask,
        bid_size=bid_size,
        ask_size=ask_size,
        bid_depth5=5.0,
        ask_depth5=4.0,
    )


def trade(ts, side, price, size):
    return TradeObservation(timestamp_ms=ts, side=side, price=price, size=size)


def cfg(**kwargs):
    base = dict(
        target_notional_usd=100.0,
        entry_lifetime_ms=10_000,
        exit_lifetime_ms=30_000,
        cooldown_ms=0,
        min_abs_book_imbalance=0.05,
        min_abs_aggressor_flow=0.05,
    )
    base.update(kwargs)
    return ShadowProbeConfig(**base)


def test_probe_requires_three_way_alignment():
    engine = ShadowProbeEngine("VVV", cfg())
    m = bullish_metrics()
    m.microprice_edge_bps = -0.1
    engine.on_book(book(100_000), m)
    assert engine.attempt is None


def test_long_join_fill_and_maker_exit_emits_completed_event():
    engine = ShadowProbeEngine("VVV", cfg())
    engine.on_book(book(100_000), bullish_metrics())
    assert engine.attempt is not None
    assert engine.attempt.direction == 1

    # Bid queue is $100 and hypothetical order is $100. $200 of aggressive sells
    # at/through the bid are required under the no-cancellation-credit model.
    engine.on_trade(trade(100_100, "A", 100.0, 2.0))
    assert engine.attempt.entry_fill_ms == 100_100

    # Next book establishes passive exit at ask: queue ahead ~= $101.
    engine.on_book(book(100_200), bullish_metrics())
    engine.on_trade(trade(100_300, "B", 101.0, 2.0))

    # Event waits until a >=5s post-entry book exists so markout is empirical.
    assert engine.drain_events() == []
    engine.on_book(book(105_200, bid=100.8, ask=101.0), bullish_metrics())
    events = engine.drain_events()

    assert len(events) == 1
    event = events[0]
    assert event.filled is True
    assert event.maker_entry is True
    assert event.maker_exit is True
    assert event.round_trip_volume_usd > 0
    assert event.markout_5s_bps is not None
    assert event.policy_id.startswith("shadow-probe-")


def test_unfilled_entry_emits_negative_fill_sample_on_timeout():
    engine = ShadowProbeEngine("VVV", cfg())
    engine.on_book(book(100_000), bullish_metrics())
    engine.on_book(book(110_001), bullish_metrics())
    events = engine.drain_events()

    assert len(events) == 1
    assert events[0].filled is False
    assert events[0].round_trip_volume_usd is None


def test_exit_timeout_uses_taker_fallback():
    engine = ShadowProbeEngine("VVV", cfg())
    engine.on_book(book(100_000), bullish_metrics())
    engine.on_trade(trade(100_100, "A", 100.0, 2.0))
    engine.on_book(book(100_200), bullish_metrics())

    # No buy aggressor consumes the ask queue; after 30s the probe crosses bid.
    engine.on_book(book(130_201, bid=99.5, ask=100.0), bullish_metrics())
    events = engine.drain_events()

    assert len(events) == 1
    assert events[0].filled is True
    assert events[0].maker_exit is False
    assert events[0].pnl_usd < 0


def test_bearish_probe_uses_ask_entry_and_bid_exit():
    engine = ShadowProbeEngine("VVV", cfg())
    engine.on_book(book(100_000), bearish_metrics())
    assert engine.attempt is not None
    assert engine.attempt.direction == -1
    assert engine.attempt.entry_quote == pytest.approx(101.0)

    # Ask queue ~= $101 + $100 own notional; enough aggressive buys fill short.
    engine.on_trade(trade(100_100, "B", 101.0, 2.1))
    engine.on_book(book(100_200), bearish_metrics())
    assert engine.attempt.exit_quote == pytest.approx(100.0)
