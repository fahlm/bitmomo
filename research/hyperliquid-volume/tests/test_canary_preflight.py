from market_selector.canary_preflight import assess_canary_preflight
from market_selector.hyperliquid_adapter import ExchangeAccountSnapshot
from market_selector.operator_control import OperatorControlState


NOW = 1_000_000


def _scanner(*, healthy=True, schema=3):
    return {
        "schema_version": schema,
        "updated_at_ms": NOW - 1_000,
        "mode": "SHADOW_ONLY",
        "mainnet_order_submission": False,
        "universe": ["PONS"],
        "transport": {"healthy": healthy},
        "decision": {"allow_new_entries": False, "active_market": None},
        "ranking": [],
        "metrics": {
            "PONS": {
                "book_imbalance": -0.2,
                "microprice_edge_bps": -1.0,
                "aggressor_flow": -0.2,
                "data_stale": False,
            }
        },
    }


def _account(*, positions=None, orders=(), value=100.0, withdrawable=100.0):
    return ExchangeAccountSnapshot(
        positions=positions or {},
        open_order_ids=frozenset(orders),
        account_value_usd=value,
        withdrawable_usd=withdrawable,
    )


def _operator(*, enabled=False, killed=False):
    return OperatorControlState(
        execution_enabled=enabled and not killed,
        killed=killed,
        reason="test",
    )


def test_clean_read_only_preflight_passes_even_without_current_entry_signal():
    result = assess_canary_preflight(
        scanner_state=_scanner(),
        account=_account(),
        operator=_operator(),
        session_payload=None,
        authority_available=True,
        now_ms=NOW,
    )
    assert result.ok is True
    assert result.reasons == ()
    assert result.entry_signal_now == "IDLE"


def test_preflight_requires_execution_to_still_be_disabled():
    result = assess_canary_preflight(
        scanner_state=_scanner(),
        account=_account(),
        operator=_operator(enabled=True),
        session_payload=None,
        authority_available=True,
        now_ms=NOW,
    )
    assert result.ok is False
    assert "operator_still_disabled" in result.reasons


def test_preflight_rejects_nonflat_or_open_order_account():
    result = assess_canary_preflight(
        scanner_state=_scanner(),
        account=_account(positions={"PONS": -1.0}, orders={"7"}),
        operator=_operator(),
        session_payload=None,
        authority_available=True,
        now_ms=NOW,
    )
    assert result.ok is False
    assert "account_flat" in result.reasons
    assert "zero_open_orders" in result.reasons


def test_preflight_rejects_old_scanner_schema_without_direction_fields():
    state = _scanner(schema=2)
    state["metrics"]["PONS"].pop("aggressor_flow")
    result = assess_canary_preflight(
        scanner_state=state,
        account=_account(),
        operator=_operator(),
        session_payload=None,
        authority_available=True,
        now_ms=NOW,
    )
    assert result.ok is False
    assert "scanner_schema_directional" in result.reasons
    assert "directional_fields_present" in result.reasons


def test_preflight_rejects_stale_or_unhealthy_scanner():
    state = _scanner(healthy=False)
    state["updated_at_ms"] = NOW - 200_000
    result = assess_canary_preflight(
        scanner_state=state,
        account=_account(),
        operator=_operator(),
        session_payload=None,
        authority_available=True,
        now_ms=NOW,
    )
    assert result.ok is False
    assert "scanner_fresh" in result.reasons
    assert "transport_healthy" in result.reasons


def test_preflight_rejects_dirty_session_or_taken_authority():
    result = assess_canary_preflight(
        scanner_state=_scanner(),
        account=_account(),
        operator=_operator(),
        session_payload={"active_coin": "PONS"},
        authority_available=False,
        now_ms=NOW,
    )
    assert result.ok is False
    assert "fresh_session" in result.reasons
    assert "authority_lock_available" in result.reasons


def test_preflight_requires_sufficient_known_balance():
    result = assess_canary_preflight(
        scanner_state=_scanner(),
        account=_account(value=15.0, withdrawable=15.0),
        operator=_operator(),
        session_payload=None,
        authority_available=True,
        now_ms=NOW,
    )
    assert result.ok is False
    assert "account_value_sufficient" in result.reasons
