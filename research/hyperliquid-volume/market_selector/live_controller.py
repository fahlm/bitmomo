from __future__ import annotations

import json
import os
from dataclasses import asdict, dataclass
from pathlib import Path

from .execution_boundary import SingleExecutionAuthority, reconcile_account
from .hyperliquid_adapter import (
    HyperliquidAccountAdapter,
    HyperliquidOrderAdapter,
    OrderSubmitResult,
)
from .mission import MissionRiskGuardian, MissionSnapshot
from .models import SupervisorDecision
from .operator_control import OperatorControl


@dataclass(frozen=True)
class EntryIntent:
    coin: str
    is_buy: bool
    size: float
    limit_price: float

    def validate(self) -> None:
        if not self.coin.strip():
            raise ValueError("coin must be non-empty")
        if self.size <= 0:
            raise ValueError("size must be positive")
        if self.limit_price <= 0:
            raise ValueError("limit_price must be positive")


@dataclass
class ExecutionState:
    expected_positions: dict[str, float]
    expected_open_orders: dict[str, str]
    working_entry_oid: str | None = None
    working_entry_coin: str | None = None

    @classmethod
    def empty(cls) -> "ExecutionState":
        return cls(expected_positions={}, expected_open_orders={})


class ExecutionStateStore:
    """Atomic local state used only for fail-closed reconciliation.

    This file never grants trading authority. If it is missing after restart, the
    controller starts from an empty expectation; any real exchange exposure/order
    will then appear as an unexpected state and block new entries.
    """

    def __init__(self, path: str | Path):
        self.path = Path(path)

    def load(self) -> ExecutionState:
        if not self.path.exists():
            return ExecutionState.empty()
        payload = json.loads(self.path.read_text(encoding="utf-8"))
        return ExecutionState(
            expected_positions={
                str(k): float(v)
                for k, v in (payload.get("expected_positions") or {}).items()
            },
            expected_open_orders={
                str(k): str(v)
                for k, v in (payload.get("expected_open_orders") or {}).items()
            },
            working_entry_oid=(
                None if payload.get("working_entry_oid") is None else str(payload["working_entry_oid"])
            ),
            working_entry_coin=(
                None if payload.get("working_entry_coin") is None else str(payload["working_entry_coin"])
            ),
        )

    def save(self, state: ExecutionState) -> None:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        temp = self.path.with_suffix(self.path.suffix + ".tmp")
        payload = asdict(state)
        with temp.open("w", encoding="utf-8") as handle:
            json.dump(payload, handle, sort_keys=True)
            handle.write("\n")
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temp, self.path)


@dataclass(frozen=True)
class ControllerDecision:
    accepted: bool
    reason: str
    order: OrderSubmitResult | None = None


class LiveCanaryController:
    """Single-order canary control plane around the SDK adapter.

    This controller is deliberately conservative:
    - only one working ENTRY order at a time;
    - new entries require flat reconciled inventory;
    - entry is post-only through HyperliquidOrderAdapter;
    - operator enable, authority lock, mission risk, feed health, reconciliation,
      and supervisor selection must all pass;
    - kill/disable never blocks risk-reducing cancel/flatten actions.

    It does not decide direction or market selection. Those come from the frozen
    selector/direction layer and are presented as EntryIntent.
    """

    def __init__(
        self,
        *,
        account: HyperliquidAccountAdapter,
        orders: HyperliquidOrderAdapter,
        authority: SingleExecutionAuthority,
        operator: OperatorControl,
        state_store: ExecutionStateStore,
        risk_guardian: MissionRiskGuardian | None = None,
    ):
        self.account = account
        self.orders = orders
        self.authority = authority
        self.operator = operator
        self.state_store = state_store
        self.state = state_store.load()
        self.risk_guardian = risk_guardian or MissionRiskGuardian()

    def reconcile(self):
        snapshot = self.account.reconciliation_snapshot(
            expected_positions=self.state.expected_positions,
            expected_open_order_ids=frozenset(self.state.expected_open_orders),
        )
        return reconcile_account(snapshot)

    def submit_entry(
        self,
        intent: EntryIntent,
        *,
        mission: MissionSnapshot,
        supervisor: SupervisorDecision,
        feed_healthy: bool,
    ) -> ControllerDecision:
        intent.validate()
        control = self.operator.read()
        if not self.authority.held:
            return ControllerDecision(False, "execution_authority_lock_not_held")
        if control.killed:
            return ControllerDecision(False, "operator_kill_switch")
        if not control.execution_enabled:
            return ControllerDecision(False, f"operator_disabled:{control.reason}")
        if self.state.working_entry_oid is not None:
            return ControllerDecision(False, "working_entry_exists")
        if supervisor.active_market != intent.coin:
            return ControllerDecision(False, "intent_market_not_supervisor_active")

        reconciliation = self.reconcile()
        gate = self.risk_guardian.evaluate(
            mission=mission,
            supervisor=supervisor,
            feed_healthy=feed_healthy,
            reconciliation_ok=reconciliation.ok,
            execution_authority_enabled=True,
            inventory_flat=reconciliation.inventory_flat,
        )
        if not gate.allow_new_entries:
            return ControllerDecision(False, gate.reason)
        if not reconciliation.inventory_flat:
            return ControllerDecision(False, "inventory_not_flat_for_new_entry")

        result = self.orders.place_post_only(
            coin=intent.coin,
            is_buy=intent.is_buy,
            size=intent.size,
            limit_price=intent.limit_price,
            reduce_only=False,
        )
        if not result.accepted:
            return ControllerDecision(False, f"exchange_rejected:{result.error}", result)

        # ALO normally rests or rejects. Handle immediate fill defensively.
        if result.resting_oid is not None:
            oid = result.resting_oid
            self.state.expected_open_orders[oid] = intent.coin
            self.state.working_entry_oid = oid
            self.state.working_entry_coin = intent.coin
        elif result.filled_oid is not None and result.filled_size:
            signed = result.filled_size if intent.is_buy else -result.filled_size
            self.state.expected_positions[intent.coin] = (
                self.state.expected_positions.get(intent.coin, 0.0) + signed
            )
        else:
            return ControllerDecision(False, "accepted_order_without_trackable_state", result)

        self.state_store.save(self.state)
        return ControllerDecision(True, "entry_submitted", result)

    def cancel_working_entry(self) -> ControllerDecision:
        """Risk-reducing action; remains allowed even when operator gate is OFF."""
        if not self.authority.held:
            return ControllerDecision(False, "execution_authority_lock_not_held")
        oid = self.state.working_entry_oid
        coin = self.state.working_entry_coin
        if oid is None or coin is None:
            return ControllerDecision(False, "no_working_entry")
        if not self.orders.cancel(coin=coin, oid=oid):
            return ControllerDecision(False, "cancel_not_acknowledged")

        self.state.expected_open_orders.pop(oid, None)
        self.state.working_entry_oid = None
        self.state.working_entry_coin = None
        self.state_store.save(self.state)
        return ControllerDecision(True, "cancel_acknowledged")

    def refresh_expected_position_from_exchange(self) -> ControllerDecision:
        """Adopt actual positions only when there are no unknown exchange orders.

        This method is intended after a known entry order has disappeared from the
        open-order set (filled/canceled) and before any next entry. Unknown orders
        are never silently adopted.
        """
        if not self.authority.held:
            return ControllerDecision(False, "execution_authority_lock_not_held")
        actual = self.account.account_snapshot()
        known_ids = frozenset(self.state.expected_open_orders)
        unknown_orders = actual.open_order_ids - known_ids
        if unknown_orders:
            return ControllerDecision(False, "unexpected_exchange_orders")

        # If the known working order is no longer open, clear it and synchronize
        # position expectation to the exchange. This is safe only because the sole
        # authority lock prevents another local process from creating exposure.
        if self.state.working_entry_oid and self.state.working_entry_oid not in actual.open_order_ids:
            self.state.expected_open_orders.pop(self.state.working_entry_oid, None)
            self.state.working_entry_oid = None
            self.state.working_entry_coin = None
            self.state.expected_positions = dict(actual.positions)
            self.state_store.save(self.state)
            return ControllerDecision(True, "known_order_closed_state_synchronized")

        reconciliation = self.reconcile()
        if not reconciliation.ok:
            return ControllerDecision(False, "reconciliation_failed")
        return ControllerDecision(True, "already_reconciled")

    def flatten_all(self) -> ControllerDecision:
        """Risk-reducing emergency flatten; allowed regardless of operator enable.

        Open known entry orders are canceled first. Actual exchange positions are
        then closed one market at a time. State remains non-flat until the exchange
        confirms fills and the subsequent synchronization/reconciliation succeeds.
        """
        if not self.authority.held:
            return ControllerDecision(False, "execution_authority_lock_not_held")

        if self.state.working_entry_oid is not None:
            canceled = self.cancel_working_entry()
            if not canceled.accepted:
                return canceled

        actual = self.account.account_snapshot()
        if actual.open_order_ids:
            return ControllerDecision(False, "unexpected_open_orders_block_flatten")
        if not actual.positions:
            self.state.expected_positions = {}
            self.state_store.save(self.state)
            return ControllerDecision(True, "already_flat")

        last: OrderSubmitResult | None = None
        for coin, signed_size in sorted(actual.positions.items()):
            if signed_size == 0:
                continue
            last = self.orders.flatten_market(coin=coin, size=abs(signed_size))
            if not last.accepted:
                return ControllerDecision(False, f"flatten_rejected:{coin}:{last.error}", last)

        # Do not optimistically mark flat here. The next exchange snapshot is the
        # authority for whether flatten fills actually completed.
        return ControllerDecision(True, "flatten_submitted_reconcile_required", last)
