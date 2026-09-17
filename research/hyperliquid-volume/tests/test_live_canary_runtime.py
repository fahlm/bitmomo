import json

import pytest

from market_selector.canary_controller import CanarySessionState
from market_selector.execution_boundary import SingleExecutionAuthority
from market_selector.hyperliquid_adapter import OrderSubmitResult
from market_selector.live_canary_runtime import (
    AtomicCanarySessionStore,
    LiveCanaryRuntime,
    LiveCanaryRuntimeConfig,
)
from market_selector.operator_control import OperatorControl


class FakeInfo:
    def __init__(self, *, account_value=100.0, withdrawable=100.0):
        self.positions = {}
        self.orders = []
        self.account_value = account_value
        self.withdrawable = withdrawable
        self.fills = []
        self.funding = []

    def user_state(self, address):
        return {
            "assetPositions": [
                {"position": {"coin": coin, "szi": str(size)}}
                for coin, size in self.positions.items()
                if size != 0
            ],
            "marginSummary": {"accountValue": str(self.account_value)},
            "withdrawable": str(self.withdrawable),
        }

    def open_orders(self, address):
        return list(self.orders)

    def user_fills_by_time(self, address, start_time, end_time=None, aggregate_by_time=False):
        return list(self.fills)

    def user_funding_history(self, user, startTime, endTime=None):
        return list(self.funding)

    def query_order_by_oid(self, user, oid):
        return {"status": "order", "order": {"order": {"oid": oid}}}

    def meta(self):
        return {"universe": [{"name": "PONS", "szDecimals": 2}]}

    def all_mids(self):
        return {"PONS": "10"}


class FakeOrderAdapter:
    def __init__(self, info, *, fill_entry=True, flatten_works=True):
        self.info = info
        self.fill_entry = fill_entry
        self.flatten_works = flatten_works
        self.market_open_calls = []
        self.flatten_calls = []

    def market_open(self, *, coin, is_buy, size, slippage):
        self.market_open_calls.append((coin, is_buy, size, slippage))
        if self.fill_entry:
            self.info.positions[coin] = size if is_buy else -size
        return OrderSubmitResult(
            accepted=True,
            filled_oid="1" if self.fill_entry else None,
            filled_size=size if self.fill_entry else 0.0,
            average_price=10.0 if self.fill_entry else None,
        )

    def flatten_market(self, *, coin, size=None, slippage=0.01):
        self.flatten_calls.append((coin, size, slippage))
        if self.flatten_works:
            self.info.positions.pop(coin, None)
        return OrderSubmitResult(
            accepted=True,
            filled_oid="2" if self.flatten_works else None,
            filled_size=size if self.flatten_works else 0.0,
            average_price=10.0 if self.flatten_works else None,
        )


class FakeAuthority:
    def __init__(self, held=True):
        self.held = held

    def acquire(self):
        self.held = True
        return True

    def release(self):
        self.held = False


def _runtime(tmp_path, info, order_adapter=None, *, flatten_attempts=1):
    scanner = tmp_path / "scanner.json"
    scanner.write_text("{}")
    enable = tmp_path / "enable"
    kill = tmp_path / "kill"
    return LiveCanaryRuntime(
        info=info,
        account_address="0xabc",
        order_adapter=order_adapter or FakeOrderAdapter(info),
        operator_control=OperatorControl(enable_path=enable, kill_path=kill),
        authority=FakeAuthority(True),
        cfg=LiveCanaryRuntimeConfig(
            scanner_state_path=scanner,
            session_state_path=tmp_path / "session.json",
            confirmation_timeout_seconds=0.01,
            confirmation_poll_seconds=0.001,
            flatten_attempts=flatten_attempts,
        ),
    )


def test_fresh_session_requires_flat_account(tmp_path):
    info = FakeInfo()
    info.positions["PONS"] = -1.0
    runtime = _runtime(tmp_path, info)
    with pytest.raises(RuntimeError, match="fresh_canary_requires_flat_account"):
        runtime._load_or_create_session(runtime.account_adapter.account_snapshot())


def test_entry_notional_is_capped_to_80_percent_of_available_capital(tmp_path):
    info = FakeInfo(account_value=100.0, withdrawable=100.0)
    orders = FakeOrderAdapter(info)
    runtime = _runtime(tmp_path, info, orders)
    runtime.session = CanarySessionState(mission_start_ms=1, epoch_start_ms=1)
    runtime._open_short("PONS", 100.0)

    assert orders.market_open_calls == [("PONS", False, 8.0, 0.002)]
    assert info.positions["PONS"] == -8.0
    assert runtime.session.active_coin == "PONS"
    assert runtime.session.pending_coin is None


def test_ioc_no_fill_clears_pending_and_stays_flat(tmp_path):
    info = FakeInfo()
    orders = FakeOrderAdapter(info, fill_entry=False)
    runtime = _runtime(tmp_path, info, orders)
    runtime.session = CanarySessionState(mission_start_ms=1, epoch_start_ms=1)
    runtime._open_short("PONS", 80.0)

    assert info.positions == {}
    assert runtime.session.pending_coin is None
    assert runtime.session.active_coin is None
    assert runtime.session.last_flat_ms is not None


def test_existing_owned_session_requires_restart_recovery(tmp_path):
    info = FakeInfo()
    info.positions["PONS"] = -8.0
    runtime = _runtime(tmp_path, info)
    store = AtomicCanarySessionStore(runtime.cfg.session_state_path)
    store.save(
        CanarySessionState(
            mission_start_ms=1,
            epoch_start_ms=1,
            active_coin="PONS",
            opened_at_ms=2,
        )
    )

    session = runtime._load_or_create_session(runtime.account_adapter.account_snapshot())
    assert session.recovery_required is True
    assert session.active_coin == "PONS"


def test_restart_recovery_step_flattens_instead_of_reentering(tmp_path):
    info = FakeInfo()
    info.positions["PONS"] = -8.0
    orders = FakeOrderAdapter(info)
    runtime = _runtime(tmp_path, info, orders)
    runtime.session = CanarySessionState(
        mission_start_ms=1,
        epoch_start_ms=1,
        active_coin="PONS",
        opened_at_ms=2,
        recovery_required=True,
    )

    action = runtime.step()
    assert action.value == "FLATTEN"
    assert orders.market_open_calls == []
    assert len(orders.flatten_calls) == 1
    assert info.positions == {}
    assert runtime.session.recovery_required is False


def test_flatten_failure_persists_hard_halt(tmp_path):
    info = FakeInfo()
    info.positions["PONS"] = -8.0
    orders = FakeOrderAdapter(info, flatten_works=False)
    runtime = _runtime(tmp_path, info, orders, flatten_attempts=1)
    runtime.session = CanarySessionState(
        mission_start_ms=1,
        epoch_start_ms=1,
        active_coin="PONS",
        opened_at_ms=2,
    )

    with pytest.raises(RuntimeError, match="flatten_confirmation_failed"):
        runtime._flatten("PONS", "test")

    assert runtime.session.halt_reason == "flatten_confirmation_failed"
    saved = json.loads(runtime.cfg.session_state_path.read_text())
    assert saved["halt_reason"] == "flatten_confirmation_failed"


def test_atomic_session_store_round_trips_recovery_flag(tmp_path):
    store = AtomicCanarySessionStore(tmp_path / "session.json")
    source = CanarySessionState(
        mission_start_ms=10,
        epoch_start_ms=11,
        active_coin="PONS",
        recovery_required=True,
    )
    store.save(source)
    restored = CanarySessionState.from_dict(store.load())
    assert restored.recovery_required is True
    assert restored.active_coin == "PONS"
