from market_selector.config import SelectorConfig
from market_selector.eligibility import evaluate_market
from market_selector.models import RollingMetrics, SupervisorState, Verdict
from market_selector.supervisor import MarketSupervisor


def good_metrics(coin="VVV", score_bias=0.0):
    return RollingMetrics(
        coin=coin,
        timestamp_ms=1_000_000,
        observation_seconds=600,
        book_observations=500,
        trade_observations=100,
        book_age_seconds=0.2,
        trade_age_seconds=0.5,
        day_volume_usd=50_000_000 + score_bias,
        spread_median_bps=4.0,
        spread_p90_bps=6.0,
        bbo_queue_usd=300.0,
        bbo_queue_multiple=3.0,
        trade_rate_per_minute=10.0,
        execution_samples=60,
        fill_rate=0.15,
        maker_ratio=0.80,
        markout_5s_bps=0.25,
        volume_per_hour_usd=2500,
        t10k_hours=4.0,
        p10k_usd=-1.0,
    )


def test_warmup_is_fail_closed():
    cfg = SelectorConfig()
    m = good_metrics()
    m.observation_seconds = 30
    result = evaluate_market(m, cfg)
    assert result.verdict == Verdict.WARMUP


def test_stale_feed_hard_rejects():
    cfg = SelectorConfig()
    m = good_metrics()
    m.book_age_seconds = 99
    result = evaluate_market(m, cfg)
    assert result.verdict == Verdict.REJECT
    assert result.hard_fail is True


def test_stale_trade_timestamp_alone_is_not_transport_failure():
    cfg = SelectorConfig()
    m = good_metrics()
    m.trade_age_seconds = 99
    result = evaluate_market(m, cfg)
    assert result.hard_fail is False
    assert result.verdict == Verdict.QUALIFIED


def test_low_trade_activity_still_rejects_market_quality():
    cfg = SelectorConfig()
    m = good_metrics()
    m.trade_age_seconds = 99
    m.trade_rate_per_minute = 0.5
    result = evaluate_market(m, cfg)
    assert result.hard_fail is False
    assert result.verdict == Verdict.REJECT
    assert result.reasons == ["structural_fail:trade_activity"]


def test_unknown_execution_metrics_never_qualify():
    cfg = SelectorConfig()
    m = good_metrics()
    m.execution_samples = 60
    m.maker_ratio = None
    result = evaluate_market(m, cfg)
    assert result.verdict == Verdict.WATCH


def test_no_qualified_market_means_idle():
    cfg = SelectorConfig(qualification_windows=1)
    supervisor = MarketSupervisor(cfg)
    weak = good_metrics()
    weak.p10k_usd = -9
    result = evaluate_market(weak, cfg)
    decision = supervisor.decide([result], inventory_flat=True)
    assert decision.state == SupervisorState.IDLE
    assert decision.allow_new_entries is False


def test_active_degradation_stops_new_entry_and_waits_for_flat():
    cfg = SelectorConfig(qualification_windows=1, degradation_windows=1)
    supervisor = MarketSupervisor(cfg)
    result = evaluate_market(good_metrics(), cfg)
    first = supervisor.decide([result], inventory_flat=True)
    assert first.state == SupervisorState.ACTIVE

    degraded = good_metrics()
    degraded.p10k_usd = -10
    second = supervisor.decide(
        [evaluate_market(degraded, cfg)],
        inventory_flat=False,
    )
    assert second.state == SupervisorState.STOP_NEW_ENTRY
    assert second.allow_new_entries is False


def test_switch_only_when_flat():
    cfg = SelectorConfig(
        qualification_windows=1,
        degradation_windows=1,
        switch_confirmation_windows=1,
        challenger_margin=0,
    )
    supervisor = MarketSupervisor(cfg)
    vvv = evaluate_market(good_metrics("VVV"), cfg)
    supervisor.decide([vvv], inventory_flat=True)

    bad_vvv = good_metrics("VVV")
    bad_vvv.p10k_usd = -10
    pons = evaluate_market(good_metrics("PONS"), cfg)

    draining = supervisor.decide(
        [evaluate_market(bad_vvv, cfg), pons],
        inventory_flat=False,
    )
    assert draining.allow_new_entries is False
    assert draining.active_market == "VVV"

    switched = supervisor.decide(
        [evaluate_market(bad_vvv, cfg), pons],
        inventory_flat=True,
    )
    assert switched.active_market == "PONS"
    assert switched.allow_new_entries is True


def test_hysteresis_prevents_single_window_activation():
    cfg = SelectorConfig(qualification_windows=3)
    supervisor = MarketSupervisor(cfg)
    result = evaluate_market(good_metrics(), cfg)

    d1 = supervisor.decide([result], inventory_flat=True)
    d2 = supervisor.decide([result], inventory_flat=True)
    assert d1.state == SupervisorState.IDLE
    assert d2.state == SupervisorState.IDLE

    d3 = supervisor.decide([result], inventory_flat=True)
    assert d3.state == SupervisorState.ACTIVE
    assert d3.active_market == "VVV"
