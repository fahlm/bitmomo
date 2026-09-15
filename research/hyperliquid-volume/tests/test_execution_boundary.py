from market_selector.execution_boundary import (
    ReconciliationSnapshot,
    SingleExecutionAuthority,
    reconcile_account,
)


def test_reconciliation_passes_on_exact_state():
    result = reconcile_account(
        ReconciliationSnapshot(
            expected_positions={"PONS": 0.5},
            actual_positions={"PONS": 0.5},
            expected_open_order_ids=frozenset({"o1"}),
            actual_open_order_ids=frozenset({"o1"}),
        )
    )
    assert result.ok is True
    assert result.inventory_flat is False
    assert result.reasons == ()


def test_reconciliation_fails_closed_on_position_or_order_mismatch():
    result = reconcile_account(
        ReconciliationSnapshot(
            expected_positions={"PONS": 0.5},
            actual_positions={"PONS": 0.4, "VVV": 0.1},
            expected_open_order_ids=frozenset({"o1"}),
            actual_open_order_ids=frozenset({"o2"}),
        )
    )
    assert result.ok is False
    assert result.inventory_flat is False
    assert any(x.startswith("position_mismatch:PONS") for x in result.reasons)
    assert any(x.startswith("position_mismatch:VVV") for x in result.reasons)
    assert "missing_orders:o1" in result.reasons
    assert "unexpected_orders:o2" in result.reasons


def test_reconciliation_inventory_flat_uses_actual_exchange_state():
    result = reconcile_account(
        ReconciliationSnapshot(
            expected_positions={},
            actual_positions={"PONS": 0.0},
        )
    )
    assert result.ok is True
    assert result.inventory_flat is True


def test_single_execution_authority_is_exclusive(tmp_path):
    path = tmp_path / "authority.lock"
    first = SingleExecutionAuthority(path, owner_id="first")
    second = SingleExecutionAuthority(path, owner_id="second")

    assert first.acquire() is True
    assert first.held is True
    assert second.acquire() is False

    first.release()
    assert first.held is False
    assert second.acquire() is True
    second.release()
