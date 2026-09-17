from market_selector.canary_controller import (
    CanaryAction,
    CanaryControllerConfig,
    CanarySessionState,
    evaluate_canary_controller,
)
from market_selector.hyperliquid_adapter import ExchangeAccountSnapshot
from market_selector.live_accounting import LiveMissionSnapshot, LiveMissionStatus
from market_selector.operator_control import OperatorControlState


NOW = 1_000_000


def _scanner(direction=-1, *, allow=True, healthy=True, verdict="QUALIFIED"):
    if direction == -1:
        metrics = {
            "book_imbalance": -0.2,
            "microprice_edge_bps": -1.0,
            "aggressor_flow": -0.2,
            "data_stale": False,
        }
    elif direction == 1:
        metrics = {
            "book_imbalance": 0.2,
            "microprice_edge_bps": 1.0,
            "aggressor_flow": 0.2,
            "data_stale": False,
        }
    else:
        metrics = {
            "book_imbalance": 0.0,
            "microprice_edge_bps": 0.0,
            "aggressor_flow": 0.0,
            "data_stale": False,
        }
    return {
        "updated_at_ms": NOW - 500,
        "mode": "SHADOW_ONLY",
        "mainnet_order_submission": False,
        "transport": {"healthy": healthy},
        "decision": {"allow_new_entries": allow, "active_market": "PONS"},
        "ranking": [{"coin": "PONS", "verdict": verdict, "rank": 1, "score": 90}],
        "metrics": {"PONS": metrics},
    }


def _mission(status=LiveMissionStatus.RUNNING, remaining=1000.0):
    return LiveMissionSnapshot(
        status=status,
        epoch=1,
        cumulative_volume_usd=1000.0 - remaining,
        cumulative_net_pnl_usd=0.0,
        epoch_net_pnl_usd=0.0,
        remaining_volume_usd=remaining,
        remaining_epoch_loss_buffer_usd=1.0,
        remaining_mission_loss_buffer_usd=2.0,
        fills=0,
        trading_fees_usd=0.0,
        funding_pnl_usd=0.0,
    )


def _account(positions=None, orders=()):
    return ExchangeAccountSnapshot(
        positions=positions or {},
        open_order_ids=frozenset(orders),
        account_value_usd=100.0,
        withdrawable_usd=100.0,
    )


def _operator(enabled=True, killed=False):
    return OperatorControlState(
        execution_enabled=enabled and not killed,
        killed=killed,
        reason="test",
    )


def _session(active=None, opened_at=None):
    return CanarySessionState(
        mission_start_ms=1,
        epoch_start_ms=1,
        active_coin=active,
        opened_at_ms=opened_at,
    )


def test_flat_all_green_opens_short_only():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account(),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=_session(),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.OPEN_SHORT
    assert decision.coin == "PONS"
    assert decision.target_notional_usd == 100.0


def test_flat_bullish_micro_does_not_open_long():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(1),
        account=_account(),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=_session(),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.IDLE


def test_open_short_holds_while_bearish_persists():
    session = _session(active="PONS", opened_at=NOW - 10_000)
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account({"PONS": -12.0}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=session,
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.HOLD_SHORT
    assert session.non_bearish_streak == 0


def test_bullish_flip_flattens_immediately():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(1),
        account=_account({"PONS": -12.0}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=_session(active="PONS", opened_at=NOW - 10_000),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.FLATTEN
    assert decision.reason == "microstructure_flipped_bullish"


def test_neutral_requires_configured_streak_before_flatten():
    session = _session(active="PONS", opened_at=NOW - 10_000)
    cfg = CanaryControllerConfig(neutral_exit_streak=2)

    first = evaluate_canary_controller(
        scanner_state=_scanner(0),
        account=_account({"PONS": -12.0}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=session,
        now_ms=NOW,
        cfg=cfg,
    )
    assert first.action == CanaryAction.HOLD_SHORT
    assert session.non_bearish_streak == 1

    second = evaluate_canary_controller(
        scanner_state=_scanner(0),
        account=_account({"PONS": -12.0}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=session,
        now_ms=NOW + 1_000,
        cfg=cfg,
    )
    assert second.action == CanaryAction.FLATTEN
    assert second.reason == "bearish_alignment_lost"


def test_max_hold_forces_flatten_even_if_signal_remains_bearish():
    cfg = CanaryControllerConfig(max_hold_seconds=60)
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account({"PONS": -12.0}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=_session(active="PONS", opened_at=NOW - 60_000),
        now_ms=NOW,
        cfg=cfg,
    )
    assert decision.action == CanaryAction.FLATTEN
    assert decision.reason == "max_hold_reached"


def test_operator_kill_flattens_owned_inventory():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account({"PONS": -12.0}),
        mission=_mission(),
        operator=_operator(killed=True),
        authority_held=True,
        session=_session(active="PONS", opened_at=NOW - 1_000),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.FLATTEN
    assert decision.reason == "operator_kill"


def test_ioc_canary_rejects_any_resting_order():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account(orders={"123"}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=_session(),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.HALT
    assert decision.reason == "unexpected_open_orders"


def test_unowned_exchange_position_halts_instead_of_adopting_it():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account({"BTC": -0.001}),
        mission=_mission(),
        operator=_operator(),
        authority_held=True,
        session=_session(),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.HALT
    assert decision.reason == "unowned_exchange_position"


def test_mission_hard_limit_flattens_before_halt():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account({"PONS": -12.0}),
        mission=_mission(LiveMissionStatus.HALT),
        operator=_operator(),
        authority_held=True,
        session=_session(active="PONS", opened_at=NOW - 1_000),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.FLATTEN
    assert decision.reason == "mission_hard_limit"


def test_epoch_stop_when_flat_requests_next_epoch():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account(),
        mission=_mission(LiveMissionStatus.EPOCH_STOP),
        operator=_operator(),
        authority_held=True,
        session=_session(),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.BEGIN_NEXT_EPOCH


def test_remaining_volume_bounds_final_notional():
    decision = evaluate_canary_controller(
        scanner_state=_scanner(-1),
        account=_account(),
        mission=_mission(remaining=60.0),
        operator=_operator(),
        authority_held=True,
        session=_session(),
        now_ms=NOW,
    )
    assert decision.action == CanaryAction.OPEN_SHORT
    assert decision.target_notional_usd == 30.0
