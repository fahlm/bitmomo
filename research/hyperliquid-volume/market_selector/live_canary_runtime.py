from __future__ import annotations

import argparse
import json
import math
import os
import tempfile
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from .canary_controller import (
    CanaryAction,
    CanaryControllerConfig,
    CanarySessionState,
    evaluate_canary_controller,
)
from .canary_policy import CanaryPolicyConfig, MacroPrior
from .execution_boundary import SingleExecutionAuthority
from .hyperliquid_adapter import (
    ExchangeAccountSnapshot,
    HyperliquidAccountAdapter,
    HyperliquidOrderAdapter,
)
from .live_accounting import HyperliquidMissionAccounting
from .mission import CANARY_MISSION
from .operator_control import OperatorControl


LIVE_ACK = "I_UNDERSTAND_BITMOMO_CANARY_PLACES_REAL_MAINNET_ORDERS"


class AtomicCanarySessionStore:
    """Small fsync+replace journal for live canary ownership/recovery state."""

    def __init__(self, path: str | Path):
        self.path = Path(path)

    def load(self) -> dict[str, Any] | None:
        if not self.path.exists():
            return None
        try:
            payload = json.loads(self.path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            raise RuntimeError("canary_session_unreadable")
        if not isinstance(payload, dict):
            raise RuntimeError("canary_session_invalid")
        return payload

    def save(self, session: CanarySessionState) -> None:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        payload = session.to_dict()
        payload["schema_version"] = 1
        payload["updated_at_ms"] = int(time.time() * 1000)
        serialized = json.dumps(payload, sort_keys=True, indent=2)
        fd, temp_name = tempfile.mkstemp(
            prefix=self.path.name + ".",
            suffix=".tmp",
            dir=str(self.path.parent),
            text=True,
        )
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as handle:
                handle.write(serialized)
                handle.write("\n")
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temp_name, self.path)
        finally:
            try:
                os.unlink(temp_name)
            except FileNotFoundError:
                pass


@dataclass(frozen=True)
class LiveCanaryRuntimeConfig:
    scanner_state_path: Path
    session_state_path: Path
    poll_seconds: float = 1.0
    confirmation_timeout_seconds: float = 5.0
    confirmation_poll_seconds: float = 0.25
    flatten_attempts: int = 3
    max_account_value_fraction: float = 0.80
    position_flat_tolerance: float = 1e-12
    controller: CanaryControllerConfig = field(default_factory=CanaryControllerConfig)
    policy: CanaryPolicyConfig = field(
        default_factory=lambda: CanaryPolicyConfig(macro_prior=MacroPrior.BEARISH)
    )

    def validate(self) -> None:
        if self.poll_seconds <= 0:
            raise ValueError("poll_seconds must be positive")
        if self.confirmation_timeout_seconds <= 0:
            raise ValueError("confirmation_timeout_seconds must be positive")
        if self.confirmation_poll_seconds <= 0:
            raise ValueError("confirmation_poll_seconds must be positive")
        if self.flatten_attempts < 1:
            raise ValueError("flatten_attempts must be >= 1")
        if not 0 < self.max_account_value_fraction <= 1:
            raise ValueError("max_account_value_fraction must be in (0, 1]")
        if self.position_flat_tolerance < 0:
            raise ValueError("position_flat_tolerance must be non-negative")
        self.controller.validate()
        self.policy.validate()


class LiveCanaryRuntime:
    """Thin single-authority execution loop for the first controlled canary.

    Public-data selection remains owned by AutonomousShadowRuntime. This process
    reads its state, verifies the live account, and creates risk only after every
    explicit canary gate passes. V0 uses IOC market helpers only: no resting entry
    order is intentionally created.
    """

    FATAL_REASONS = {
        "multiple_exchange_positions",
        "unowned_exchange_position",
        "restart_state_ambiguous",
        "restart_open_orders_ambiguous",
        "unexpected_open_orders",
        "flatten_confirmation_failed",
        "unexpected_non_short_position",
    }

    def __init__(
        self,
        *,
        info: Any,
        account_address: str,
        order_adapter: HyperliquidOrderAdapter,
        operator_control: OperatorControl,
        authority: SingleExecutionAuthority,
        cfg: LiveCanaryRuntimeConfig,
    ):
        cfg.validate()
        self.info = info
        self.account_address = account_address
        self.order_adapter = order_adapter
        self.operator_control = operator_control
        self.authority = authority
        self.cfg = cfg
        self.account_adapter = HyperliquidAccountAdapter(
            info,
            account_address=account_address,
        )
        self.accounting = HyperliquidMissionAccounting(
            info,
            account_address=account_address,
        )
        self.session_store = AtomicCanarySessionStore(cfg.session_state_path)
        self.session: CanarySessionState | None = None
        self._sz_decimals: dict[str, int] = {}

    def _scanner_state(self) -> dict[str, Any]:
        try:
            payload = json.loads(self.cfg.scanner_state_path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return {}
        return payload if isinstance(payload, dict) else {}

    def _load_or_create_session(self, account: ExchangeAccountSnapshot) -> CanarySessionState:
        raw = self.session_store.load()
        now_ms = int(time.time() * 1000)
        if raw is None:
            if account.positions or account.open_order_ids:
                raise RuntimeError("fresh_canary_requires_flat_account_and_zero_open_orders")
            session = CanarySessionState(
                mission_start_ms=now_ms,
                epoch_start_ms=now_ms,
                epoch=1,
            )
            self.session_store.save(session)
            return session

        session = CanarySessionState.from_dict(raw)
        # Process restart is a hard authority boundary. If a prior submit/position
        # was journaled, the new process may only reconcile/flatten before trading.
        if session.active_coin or session.pending_coin:
            session.recovery_required = True
            self.session_store.save(session)
        return session

    def _meta_decimals(self, coin: str) -> int:
        if coin in self._sz_decimals:
            return self._sz_decimals[coin]
        meta = self.info.meta()
        for row in meta.get("universe", []):
            name = str(row.get("name", ""))
            if name:
                self._sz_decimals[name] = int(row.get("szDecimals", 0))
        if coin not in self._sz_decimals:
            raise RuntimeError(f"coin_not_in_meta:{coin}")
        return self._sz_decimals[coin]

    def _size_for_notional(self, coin: str, notional_usd: float) -> tuple[float, float]:
        mids = self.info.all_mids()
        raw_mid = mids.get(coin)
        if raw_mid is None:
            raise RuntimeError(f"mid_unavailable:{coin}")
        mid = float(raw_mid)
        if mid <= 0:
            raise RuntimeError(f"invalid_mid:{coin}")

        decimals = self._meta_decimals(coin)
        factor = 10 ** decimals
        size = math.floor((notional_usd / mid) * factor) / factor
        if size <= 0:
            raise RuntimeError(f"size_rounds_to_zero:{coin}")
        return size, mid

    def _wait_for_account(self, predicate) -> ExchangeAccountSnapshot:
        deadline = time.monotonic() + self.cfg.confirmation_timeout_seconds
        last = self.account_adapter.account_snapshot()
        while time.monotonic() < deadline:
            if predicate(last):
                return last
            time.sleep(self.cfg.confirmation_poll_seconds)
            last = self.account_adapter.account_snapshot()
        return last

    def _is_flat_size(self, value: float) -> bool:
        return abs(float(value)) <= self.cfg.position_flat_tolerance

    def _flatten(self, coin: str, reason: str) -> None:
        assert self.session is not None
        for _ in range(self.cfg.flatten_attempts):
            before = self.account_adapter.account_snapshot()
            size = abs(float(before.positions.get(coin, 0.0)))
            if self._is_flat_size(size):
                self.session.active_coin = None
                self.session.pending_coin = None
                self.session.opened_at_ms = None
                self.session.non_bearish_streak = 0
                self.session.recovery_required = False
                self.session.last_flat_ms = int(time.time() * 1000)
                self.session_store.save(self.session)
                print(f"CANARY FLAT coin={coin} reason={reason}")
                return

            self.order_adapter.flatten_market(
                coin=coin,
                size=size,
                slippage=self.cfg.controller.max_flatten_slippage,
            )
            after = self._wait_for_account(
                lambda snap: self._is_flat_size(snap.positions.get(coin, 0.0))
            )
            if self._is_flat_size(after.positions.get(coin, 0.0)):
                self.session.active_coin = None
                self.session.pending_coin = None
                self.session.opened_at_ms = None
                self.session.non_bearish_streak = 0
                self.session.recovery_required = False
                self.session.last_flat_ms = int(time.time() * 1000)
                self.session_store.save(self.session)
                print(f"CANARY FLAT coin={coin} reason={reason}")
                return

        self.session.halt_reason = "flatten_confirmation_failed"
        self.session_store.save(self.session)
        raise RuntimeError("flatten_confirmation_failed")

    def _open_short(self, coin: str, target_notional_usd: float) -> None:
        assert self.session is not None
        before = self.account_adapter.account_snapshot()
        if before.positions or before.open_order_ids:
            raise RuntimeError("entry_requires_flat_account_and_zero_open_orders")
        if before.account_value_usd is None or before.withdrawable_usd is None:
            raise RuntimeError("account_balance_unavailable")
        available = min(float(before.account_value_usd), float(before.withdrawable_usd))
        if available <= 0:
            raise RuntimeError("account_balance_non_positive")

        capital_cap = available * self.cfg.max_account_value_fraction
        bounded_notional = min(float(target_notional_usd), capital_cap)
        if bounded_notional < self.cfg.controller.minimum_order_notional_usd:
            raise RuntimeError(
                f"account_cap_below_minimum:{bounded_notional:.4f}"
            )

        size, mid = self._size_for_notional(coin, bounded_notional)
        actual_target = size * mid
        if actual_target < self.cfg.controller.minimum_order_notional_usd:
            raise RuntimeError(f"rounded_notional_below_minimum:{actual_target:.4f}")

        # Journal ownership before the external action. If the process dies after
        # submit but before acknowledgement, restart recovery can only flatten.
        self.session.pending_coin = coin
        self.session.recovery_required = False
        self.session_store.save(self.session)

        result = self.order_adapter.market_open(
            coin=coin,
            is_buy=False,
            size=size,
            slippage=self.cfg.controller.max_entry_slippage,
        )

        if not result.accepted:
            self.session.pending_coin = None
            self.session.last_flat_ms = int(time.time() * 1000)
            self.session_store.save(self.session)
            print(f"CANARY ENTRY_REJECT coin={coin} error={result.error}")
            return

        after = self._wait_for_account(
            lambda snap: (
                coin in snap.positions
                or bool(snap.open_order_ids)
            )
        )

        if after.open_order_ids:
            self.session.halt_reason = "unexpected_open_orders"
            self.session_store.save(self.session)
            if coin in after.positions:
                self.session.active_coin = coin
                self._flatten(coin, "ioc_unexpected_open_order")
            raise RuntimeError("ioc_left_unexpected_open_order")

        position = float(after.positions.get(coin, 0.0))
        other_positions = {
            name: value for name, value in after.positions.items()
            if name != coin and not self._is_flat_size(value)
        }
        if other_positions:
            self.session.halt_reason = "multiple_exchange_positions"
            self.session_store.save(self.session)
            if not self._is_flat_size(position):
                self.session.active_coin = coin
                self._flatten(coin, "unexpected_other_position")
            raise RuntimeError("multiple_exchange_positions")

        if position < -self.cfg.position_flat_tolerance:
            self.session.active_coin = coin
            self.session.pending_coin = None
            self.session.opened_at_ms = int(time.time() * 1000)
            self.session.non_bearish_streak = 0
            self.session_store.save(self.session)
            print(
                f"CANARY OPEN_SHORT coin={coin} size={abs(position):.12g} "
                f"target_notional={actual_target:.2f} capital_cap={capital_cap:.2f}"
            )
            return

        if position > self.cfg.position_flat_tolerance:
            self.session.active_coin = coin
            self.session.pending_coin = None
            self.session.opened_at_ms = int(time.time() * 1000)
            self.session_store.save(self.session)
            self._flatten(coin, "unexpected_long_after_short_submit")
            raise RuntimeError("unexpected_long_after_short_submit")

        # IOC accepted but no position remains: zero-fill or immediate no-op.
        self.session.pending_coin = None
        self.session.last_flat_ms = int(time.time() * 1000)
        self.session_store.save(self.session)
        print(f"CANARY NO_FILL coin={coin}")

    def step(self) -> CanaryAction:
        if self.session is None:
            self.session = self._load_or_create_session(
                self.account_adapter.account_snapshot()
            )

        now_ms = int(time.time() * 1000)
        scanner = self._scanner_state()
        account = self.account_adapter.account_snapshot()
        mission = self.accounting.mission_snapshot(
            cfg=CANARY_MISSION,
            mission_start_ms=self.session.mission_start_ms,
            epoch_start_ms=self.session.epoch_start_ms,
            epoch=self.session.epoch,
            end_ms=now_ms,
        )
        operator = self.operator_control.read()

        decision = evaluate_canary_controller(
            scanner_state=scanner,
            account=account,
            mission=mission,
            operator=operator,
            authority_held=self.authority.held,
            session=self.session,
            now_ms=now_ms,
            cfg=self.cfg.controller,
            policy_cfg=self.cfg.policy,
        )
        self.session_store.save(self.session)

        print(
            f"CANARY action={decision.action.value} reason={decision.reason} "
            f"epoch={mission.epoch} volume={mission.cumulative_volume_usd:.2f}/"
            f"{CANARY_MISSION.target_volume_usd:.0f} pnl={mission.cumulative_net_pnl_usd:.4f} "
            f"epoch_buffer={mission.remaining_epoch_loss_buffer_usd:.4f} "
            f"mission_buffer={mission.remaining_mission_loss_buffer_usd:.4f}"
        )

        if decision.action == CanaryAction.OPEN_SHORT:
            assert decision.coin is not None and decision.target_notional_usd is not None
            self._open_short(decision.coin, decision.target_notional_usd)
        elif decision.action == CanaryAction.FLATTEN:
            if decision.coin is None:
                raise RuntimeError("flatten_without_coin")
            self._flatten(decision.coin, decision.reason)
        elif decision.action == CanaryAction.BEGIN_NEXT_EPOCH:
            if self.session.epoch >= CANARY_MISSION.max_epochs:
                self.session.halt_reason = "max_epochs_exhausted"
            else:
                self.session.epoch += 1
                self.session.epoch_start_ms = now_ms
                self.session.last_flat_ms = now_ms
            self.session_store.save(self.session)
        elif decision.action == CanaryAction.HALT and decision.reason in self.FATAL_REASONS:
            self.session.halt_reason = decision.reason
            self.session_store.save(self.session)

        return decision.action

    def run_forever(self) -> None:
        if not self.authority.acquire():
            raise RuntimeError("execution_authority_already_held")
        try:
            print("BITMOMO HYPERLIQUID CONTROLLED CANARY V0")
            print("LIVE MAINNET | SHORT-ONLY | IOC-ONLY | TARGET $1,000 | HARD FLOOR -$2")
            while True:
                action = self.step()
                if action == CanaryAction.SUCCESS:
                    print("CANARY SUCCESS")
                    return
                if self.session is not None and self.session.halt_reason:
                    print(f"CANARY HALT reason={self.session.halt_reason}")
                    return
                time.sleep(self.cfg.poll_seconds)
        finally:
            self.authority.release()


def _build_mainnet_clients(account_address: str, secret_key: str):
    # Imported only in the explicit live bootstrap so unit tests and read-only
    # modules never require a signer or SDK import at module import time.
    import eth_account
    from hyperliquid.exchange import Exchange
    from hyperliquid.info import Info
    from hyperliquid.utils import constants

    wallet = eth_account.Account.from_key(secret_key)
    info = Info(constants.MAINNET_API_URL, skip_ws=True)
    exchange = Exchange(
        wallet,
        constants.MAINNET_API_URL,
        account_address=account_address,
    )
    return info, exchange, wallet.address


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--live-mainnet-canary", action="store_true")
    parser.add_argument(
        "--scanner-state",
        default="data/runtime/autonomous_state.json",
    )
    parser.add_argument(
        "--session-state",
        default="data/runtime/live_canary_session.json",
    )
    parser.add_argument(
        "--enable-file",
        default="data/runtime/ENABLE_LIVE_CANARY",
    )
    parser.add_argument(
        "--kill-file",
        default="data/runtime/KILL_LIVE_CANARY",
    )
    parser.add_argument(
        "--authority-lock",
        default="data/runtime/live_canary.lock",
    )
    parser.add_argument("--poll-seconds", type=float, default=1.0)
    args = parser.parse_args()

    if not args.live_mainnet_canary:
        raise SystemExit(
            "Mainnet execution defaults OFF. Re-run only after readiness review with "
            "--live-mainnet-canary and external operator controls configured."
        )

    if os.environ.get("HL_CANARY_ACK") != LIVE_ACK:
        raise SystemExit("HL_CANARY_ACK does not contain the exact live-canary acknowledgement")

    account_address = os.environ.get("HL_ACCOUNT_ADDRESS", "").strip()
    secret_key = os.environ.get("HL_SECRET_KEY", "").strip()
    if not account_address:
        raise SystemExit("HL_ACCOUNT_ADDRESS is required")
    if not secret_key:
        raise SystemExit("HL_SECRET_KEY is required and must never be committed to GitHub")

    info, exchange, signer_address = _build_mainnet_clients(account_address, secret_key)
    print(f"Canary account={account_address} signer={signer_address}")

    runtime = LiveCanaryRuntime(
        info=info,
        account_address=account_address,
        order_adapter=HyperliquidOrderAdapter(exchange),
        operator_control=OperatorControl(
            enable_path=args.enable_file,
            kill_path=args.kill_file,
        ),
        authority=SingleExecutionAuthority(
            args.authority_lock,
            owner_id="bitmomo-live-canary-v0",
        ),
        cfg=LiveCanaryRuntimeConfig(
            scanner_state_path=Path(args.scanner_state),
            session_state_path=Path(args.session_state),
            poll_seconds=args.poll_seconds,
        ),
    )
    runtime.run_forever()


if __name__ == "__main__":
    main()
