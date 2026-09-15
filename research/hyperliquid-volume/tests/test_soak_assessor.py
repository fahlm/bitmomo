from market_selector.soak_assessor import SoakCriteria, assess_history


def sample(
    ts_ms,
    *,
    healthy=True,
    universe=("BTC", "ETH"),
    reconnects=0,
    state="IDLE",
    active=None,
    action="IDLE",
    mainnet=False,
    mode="SHADOW_ONLY",
    book_dup=0,
    trade_dup=0,
):
    return {
        "updated_at_ms": ts_ms,
        "mode": mode,
        "mainnet_order_submission": mainnet,
        "universe": list(universe),
        "transport": {
            "generation": 1,
            "healthy": healthy,
            "reconnects": reconnects,
            "max_book_age_seconds": 0.5,
        },
        "event_integrity": {
            "book_duplicates": book_dup,
            "trade_duplicates": trade_dup,
        },
        "decision": {
            "state": state,
            "active_market": active,
            "action": action,
        },
    }


def test_healthy_12h_shadow_soak_passes():
    base = 1_700_000_000_000
    rows = [sample(base + i * 60_000) for i in range(12 * 60 + 1)]
    result = assess_history(rows)

    assert result.passed is True
    assert result.duration_hours == 12.0
    assert result.healthy_fraction == 1.0
    assert result.mainnet_submission_disabled_all is True


def test_short_soak_fails_duration_only():
    base = 1_700_000_000_000
    rows = [sample(base), sample(base + 60_000)]
    result = assess_history(rows)

    assert result.passed is False
    assert any(reason.startswith("duration_below_min") for reason in result.reasons)


def test_prolonged_unhealthy_period_and_final_unhealthy_fail():
    base = 1_700_000_000_000
    rows = [
        sample(base, healthy=True),
        sample(base + 60_000, healthy=False, state="HALT", action="HALT"),
        sample(base + 180_000, healthy=False, state="HALT", action="HALT"),
        sample(base + 360_000, healthy=False, state="HALT", action="HALT"),
    ]
    result = assess_history(
        rows,
        criteria=SoakCriteria(
            min_duration_hours=0.0,
            min_healthy_fraction=0.0,
            max_sample_gap_seconds=300.0,
            max_unhealthy_streak_seconds=180.0,
        ),
    )

    assert result.passed is False
    assert "final_transport_unhealthy" in result.reasons
    assert any(reason.startswith("unhealthy_streak_exceeded") for reason in result.reasons)
    assert result.halt_samples == 3


def test_mainnet_enable_or_empty_universe_fail_soak():
    base = 1_700_000_000_000
    rows = [
        sample(base),
        sample(base + 60_000, mainnet=True, universe=()),
    ]
    result = assess_history(
        rows,
        criteria=SoakCriteria(min_duration_hours=0.0, min_healthy_fraction=0.0),
    )

    assert result.passed is False
    assert "mainnet_submission_enabled_observed" in result.reasons
    assert "empty_universe_observed" in result.reasons


def test_reports_reconnects_changes_and_duplicate_deltas_without_auto_failing():
    base = 1_700_000_000_000
    rows = [
        sample(base, reconnects=0, universe=("BTC", "ETH")),
        sample(
            base + 60_000,
            reconnects=1,
            universe=("BTC", "SOL"),
            state="ACTIVE",
            active="SOL",
            action="ACTIVATE",
            book_dup=2,
            trade_dup=3,
        ),
        sample(
            base + 120_000,
            reconnects=1,
            universe=("BTC", "SOL"),
            state="ACTIVE",
            active="SOL",
            action="HOLD_ACTIVE",
            book_dup=2,
            trade_dup=4,
        ),
    ]
    result = assess_history(
        rows,
        criteria=SoakCriteria(min_duration_hours=0.0, min_healthy_fraction=1.0),
    )

    assert result.passed is True
    assert result.reconnect_events_observed == 1
    assert result.universe_changes == 1
    assert result.decision_transitions == 2
    assert result.book_duplicates_delta == 2
    assert result.trade_duplicates_delta == 4
