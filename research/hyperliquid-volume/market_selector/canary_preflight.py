from __future__ import annotations

import argparse
import json
import os
import time
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any

from .canary_policy import CanaryIntent, CanaryPolicyConfig, evaluate_short_only_entry
from .execution_boundary import SingleExecutionAuthority
from .hyperliquid_adapter import ExchangeAccountSnapshot, HyperliquidAccountAdapter
from .operator_control import OperatorControl, OperatorControlState


@dataclass(frozen=True)
class CanaryPreflightResult:
    ok: bool
    checks: dict[str, bool]
    reasons: tuple[str, ...]
    entry_signal_now: str
    entry_coin_now: str | None
    account_value_usd: float | None
    withdrawable_usd: float | None

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


def assess_canary_preflight(
    *,
    scanner_state: dict[str, Any],
    account: ExchangeAccountSnapshot,
    operator: OperatorControlState,
    session_payload: dict[str, Any] | None,
    authority_available: bool,
    now_ms: int,
    minimum_account_value_usd: float = 20.0,
    max_scanner_age_seconds: float = 90.0,
) -> CanaryPreflightResult:
    checks: dict[str, bool] = {}
    reasons: list[str] = []

    checks["scanner_mode_shadow_only"] = scanner_state.get("mode") == "SHADOW_ONLY"
    checks["scanner_mainnet_flag_false"] = scanner_state.get("mainnet_order_submission") is False
    checks["scanner_schema_directional"] = int(scanner_state.get("schema_version", 0) or 0) >= 3

    updated_at_ms = int(scanner_state.get("updated_at_ms", 0) or 0)
    scanner_age = float("inf")
    if updated_at_ms > 0 and now_ms >= updated_at_ms:
        scanner_age = (now_ms - updated_at_ms) / 1000.0
    checks["scanner_fresh"] = scanner_age <= max_scanner_age_seconds

    transport = scanner_state.get("transport") or {}
    checks["transport_healthy"] = transport.get("healthy") is True
    checks["universe_nonempty"] = bool(scanner_state.get("universe"))

    metrics = scanner_state.get("metrics") or {}
    directional_keys = {"book_imbalance", "microprice_edge_bps", "aggressor_flow"}
    checks["directional_fields_present"] = bool(metrics) and all(
        isinstance(row, dict) and directional_keys.issubset(row.keys())
        for row in metrics.values()
    )

    checks["account_flat"] = not account.positions
    checks["zero_open_orders"] = not account.open_order_ids
    checks["account_value_known"] = account.account_value_usd is not None
    checks["withdrawable_known"] = account.withdrawable_usd is not None
    checks["account_value_sufficient"] = (
        account.account_value_usd is not None
        and account.withdrawable_usd is not None
        and min(float(account.account_value_usd), float(account.withdrawable_usd))
        >= minimum_account_value_usd
    )

    # Preflight is deliberately run while execution is OFF. Enabling before the
    # read-only checks complete is itself a failed launch procedure.
    checks["operator_still_disabled"] = not operator.execution_enabled
    checks["kill_switch_absent"] = not operator.killed
    checks["authority_lock_available"] = authority_available

    if session_payload is None:
        checks["fresh_session"] = True
    else:
        checks["fresh_session"] = not any(
            (
                session_payload.get("active_coin"),
                session_payload.get("pending_coin"),
                session_payload.get("recovery_required"),
                session_payload.get("halt_reason"),
            )
        )

    for name, passed in checks.items():
        if not passed:
            reasons.append(name)

    signal = evaluate_short_only_entry(
        scanner_state,
        now_ms=now_ms,
        cfg=CanaryPolicyConfig(max_state_age_seconds=max_scanner_age_seconds),
    )
    # Entry readiness is diagnostic, not a preflight requirement. A safe engine
    # may pass preflight and legitimately remain IDLE until a market qualifies.
    return CanaryPreflightResult(
        ok=all(checks.values()),
        checks=checks,
        reasons=tuple(reasons),
        entry_signal_now=signal.intent.value,
        entry_coin_now=signal.coin,
        account_value_usd=account.account_value_usd,
        withdrawable_usd=account.withdrawable_usd,
    )


def _read_json(path: Path) -> dict[str, Any] | None:
    if not path.exists():
        return None
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {"__invalid__": True}
    return payload if isinstance(payload, dict) else {"__invalid__": True}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--scanner-state", default="data/runtime/autonomous_state.json")
    parser.add_argument("--session-state", default="data/runtime/live_canary_session.json")
    parser.add_argument("--enable-file", default="data/runtime/ENABLE_LIVE_CANARY")
    parser.add_argument("--kill-file", default="data/runtime/KILL_LIVE_CANARY")
    parser.add_argument("--authority-lock", default="data/runtime/live_canary.lock")
    parser.add_argument("--minimum-account-value", type=float, default=20.0)
    args = parser.parse_args()

    account_address = os.environ.get("HL_ACCOUNT_ADDRESS", "").strip()
    if not account_address:
        raise SystemExit("HL_ACCOUNT_ADDRESS is required; no private key is needed for preflight")

    from hyperliquid.info import Info
    from hyperliquid.utils import constants

    scanner_path = Path(args.scanner_state)
    scanner = _read_json(scanner_path) or {}
    session = _read_json(Path(args.session_state))

    info = Info(constants.MAINNET_API_URL, skip_ws=True)
    account = HyperliquidAccountAdapter(
        info,
        account_address=account_address,
    ).account_snapshot()
    operator = OperatorControl(
        enable_path=args.enable_file,
        kill_path=args.kill_file,
    ).read()

    authority = SingleExecutionAuthority(
        args.authority_lock,
        owner_id="bitmomo-canary-preflight",
    )
    authority_available = authority.acquire()
    if authority_available:
        authority.release()

    result = assess_canary_preflight(
        scanner_state=scanner,
        account=account,
        operator=operator,
        session_payload=session,
        authority_available=authority_available,
        now_ms=int(time.time() * 1000),
        minimum_account_value_usd=args.minimum_account_value,
    )
    print(json.dumps(result.to_dict(), indent=2, sort_keys=True))
    raise SystemExit(0 if result.ok else 2)


if __name__ == "__main__":
    main()
