from market_selector.feedback import ExecutionFeedbackEvent
from market_selector.mission import (
    CANARY_MISSION,
    FULL_BETA_MISSION,
    MissionLedger,
    MissionRiskGuardian,
    MissionStatus,
)
from market_selector.models import SupervisorDecision, SupervisorState


def event(attempt_id: str, *, volume=200.0, pnl=0.0, filled=True):
    return ExecutionFeedbackEvent(
        attempt_id=attempt_id,
        coin="TEST",
        timestamp_ms=1_000 + int(attempt_id.strip("a") or 0),
        filled=filled,
        maker_entry=filled,
        maker_exit=filled,
        markout_5s_bps=0.0 if filled else None,
        round_trip_volume_usd=volume if filled else None,
        pnl_usd=pnl if filled else None,
        policy_id="test",
        source="test",
    )


def supervisor(*, allow=True, action="HOLD_ACTIVE"):
    return SupervisorDecision(
        state=SupervisorState.ACTIVE if allow else SupervisorState.IDLE,
        active_market="TEST" if allow else None,
        candidate_market=None,
        allow_new_entries=allow,
        action=action,
        reason="test",
    )


def test_profit_expands_loss_buffer():
    ledger = MissionLedger(FULL_BETA_MISSION)
    snap = ledger.record(event("a1", pnl=2.0))

    assert snap.cumulative_pnl_usd == 2.0
    assert snap.remaining_mission_loss_buffer_usd == 12.0
    assert snap.remaining_epoch_loss_buffer_usd == 7.0

    snap = ledger.record(event("a2", pnl=-4.0))
    assert snap.cumulative_pnl_usd == -2.0
    assert snap.remaining_mission_loss_buffer_usd == 8.0
    assert snap.remaining_epoch_loss_buffer_usd == 3.0
    assert snap.status == MissionStatus.RUNNING


def test_unfilled_and_duplicate_events_do_not_change_mission():
    ledger = MissionLedger(FULL_BETA_MISSION)
    snap = ledger.record(event("a1", filled=False))
    assert snap.cumulative_volume_usd == 0.0
    assert snap.cumulative_pnl_usd == 0.0

    ledger.record(event("a2", pnl=1.0))
    snap = ledger.record(event("a2", pnl=1.0))
    assert snap.cumulative_volume_usd == 200.0
    assert snap.cumulative_pnl_usd == 1.0
    assert snap.duplicate_events == 1


def test_epoch_stop_requires_flat_inventory_before_next_epoch():
    ledger = MissionLedger(FULL_BETA_MISSION)
    snap = ledger.record(event("a1", pnl=-5.0))
    assert snap.status == MissionStatus.EPOCH_STOP
    assert snap.epoch == 1

    try:
        ledger.begin_next_epoch(inventory_flat=False)
        assert False, "expected RuntimeError"
    except RuntimeError:
        pass

    snap = ledger.begin_next_epoch(inventory_flat=True)
    assert snap.status == MissionStatus.RUNNING
    assert snap.epoch == 2
    assert snap.epoch_pnl_usd == 0.0
    assert snap.cumulative_pnl_usd == -5.0


def test_second_epoch_loss_limit_halts():
    ledger = MissionLedger(FULL_BETA_MISSION)
    ledger.record(event("a1", pnl=-5.0))
    ledger.begin_next_epoch(inventory_flat=True)
    snap = ledger.record(event("a2", pnl=-5.0))

    assert snap.status == MissionStatus.HALT
    assert snap.cumulative_pnl_usd == -10.0


def test_target_at_exact_budget_floor_succeeds_but_overshoot_halts():
    exact = MissionLedger(CANARY_MISSION)
    snap = exact.record(event("a1", volume=1_000.0, pnl=-2.0))
    assert snap.status == MissionStatus.SUCCESS

    overshoot = MissionLedger(CANARY_MISSION)
    snap = overshoot.record(event("a2", volume=1_000.0, pnl=-2.01))
    assert snap.status == MissionStatus.HALT


def test_risk_guardian_fail_closed_and_allows_only_full_green_path():
    guardian = MissionRiskGuardian()
    mission = MissionLedger(FULL_BETA_MISSION).snapshot()

    green = guardian.evaluate(
        mission=mission,
        supervisor=supervisor(allow=True),
        feed_healthy=True,
        reconciliation_ok=True,
        execution_authority_enabled=True,
        inventory_flat=True,
    )
    assert green.allow_new_entries is True
    assert green.hard_halt is False

    disabled = guardian.evaluate(
        mission=mission,
        supervisor=supervisor(allow=True),
        feed_healthy=True,
        reconciliation_ok=True,
        execution_authority_enabled=False,
        inventory_flat=True,
    )
    assert disabled.allow_new_entries is False
    assert disabled.reason == "execution_authority_disabled"

    recon_fail = guardian.evaluate(
        mission=mission,
        supervisor=supervisor(allow=True),
        feed_healthy=True,
        reconciliation_ok=False,
        execution_authority_enabled=True,
        inventory_flat=False,
    )
    assert recon_fail.allow_new_entries is False
    assert recon_fail.must_flatten is True
    assert recon_fail.hard_halt is True


def test_epoch_stop_blocks_new_entries_and_demands_flatten_if_needed():
    ledger = MissionLedger(FULL_BETA_MISSION)
    mission = ledger.record(event("a1", pnl=-5.0))
    gate = MissionRiskGuardian().evaluate(
        mission=mission,
        supervisor=supervisor(allow=True),
        feed_healthy=True,
        reconciliation_ok=True,
        execution_authority_enabled=True,
        inventory_flat=False,
    )

    assert gate.allow_new_entries is False
    assert gate.must_flatten is True
    assert gate.hard_halt is False
    assert gate.reason == "epoch_loss_limit"
