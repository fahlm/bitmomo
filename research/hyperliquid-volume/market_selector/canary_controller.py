from __future__ import annotations

from dataclasses import dataclass
from enum import Enum
from typing import Any

from .canary_policy import CanaryIntent, CanaryPolicyConfig, aligned_direction, evaluate_short_only_entry
from .hyperliquid_adapter import ExchangeAccountSnapshot
from .live_accounting import LiveMissionSnapshot, LiveMissionStatus
from .operator_control import OperatorControlState


class CanaryAction(str, Enum):
    IDLE = "IDLE"
    OPEN_SHORT = "OPEN_SHORT"
    HOLD_SHORT = "HOLD_SHORT"
    FLATTEN = "FLATTEN"
    BEGIN_NEXT_EPOCH = "BEGIN_NEXT_EPOCH"
    SUCCESS = "SUCCESS"
    HALT = "HALT"


@dataclass(frozen=True)
class CanaryControllerConfig:
    target_notional_usd: float = 100.0
    max_entry_slippage: float = 0.002
    max_flatten_slippage: float = 0.005
    max_hold_seconds: float = 60.0
    neutral_exit_streak: int = 3
    cooldown_seconds: float = 15.0
    minimum_order_notional_usd: float = 10.0

    def validate(self) -> None:
        if self.target_notional_usd <= 0:
            raise ValueError("target_notional_usd must be positive")
        if not 0 < self.max_entry_slippage <= 0.05:
            raise ValueError("max_entry_slippage must be in (0, 0.05]")
        if not 0 < self.max_flatten_slippage <= 0.05:
            raise ValueError("max_flatten_slippage must be in (0, 0.05]")
        if self.max_hold_seconds <= 0:
            raise ValueError("max_hold_seconds must be positive")
        if self.neutral_exit_streak < 1:
            raise ValueError("neutral_exit_streak must be >= 1")
        if self.cooldown_seconds < 0:
            raise ValueError("cooldown_seconds must be non-negative")
        if self.minimum_order_notional_usd <= 0:
            raise ValueError("minimum_order_notional_usd must be positive")


@dataclass
class CanarySessionState:
    mission_start_ms: int
    epoch_start_ms: int
    epoch: int = 1
    active_coin: str | None = None
    pending_coin: str | None = None
    opened_at_ms: int | None = None
    last_flat_ms: int | None = None
    non_bearish_streak: int = 0
    halt_reason: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return {
            "mission_start_ms": self.mission_start_ms,
            "epoch_start_ms": self.epoch_start_ms,
            "epoch": self.epoch,
            "active_coin": self.active_coin,
            "pending_coin": self.pending_coin,
            "opened_at_ms": self.opened_at_ms,
            "last_flat_ms": self.last_flat_ms,
            "non_bearish_streak": self.non_bearish_streak,
            "halt_reason": self.halt_reason,
        }

    @classmethod
    def from_dict(cls, payload: dict[str, Any]) -> "CanarySessionState":
        return cls(
            mission_start_ms=int(payload["mission_start_ms"]),
            epoch_start_ms=int(payload["epoch_start_ms"]),
            epoch=int(payload.get("epoch", 1)),
            active_coin=payload.get("active_coin"),
            pending_coin=payload.get("pending_coin"),
            opened_at_ms=(
                None if payload.get("opened_at_ms") is None else int(payload["opened_at_ms"])
            ),
            last_flat_ms=(
                None if payload.get("last_flat_ms") is None else int(payload["last_flat_ms"])
            ),
            non_bearish_streak=int(payload.get("non_bearish_streak", 0)),
            halt_reason=payload.get("halt_reason"),
        )


@dataclass(frozen=True)
class CanaryControllerDecision:
    action: CanaryAction
    coin: str | None
    target_notional_usd: float | None
    reason: str


def _single_position(account: ExchangeAccountSnapshot) -> tuple[str, float] | None:
    nonzero = [(coin, float(size)) for coin, size in account.positions.items() if float(size) != 0.0]
    if len(nonzero) != 1:
        return None
    return nonzero[0]


def _active_metrics(scanner_state: dict[str, Any], coin: str) -> dict[str, Any] | None:
    metrics = (scanner_state.get("metrics") or {}).get(coin)
    return metrics if isinstance(metrics, dict) else None


def evaluate_canary_controller(
    *,
    scanner_state: dict[str, Any],
    account: ExchangeAccountSnapshot,
    mission: LiveMissionSnapshot,
    operator: OperatorControlState,
    authority_held: bool,
    session: CanarySessionState,
    now_ms: int,
    cfg: CanaryControllerConfig | None = None,
    policy_cfg: CanaryPolicyConfig | None = None,
) -> CanaryControllerDecision:
    """Pure fail-closed controller decision for the first mainnet canary.

    V0 intentionally permits only one short position and zero resting orders. Any
    open order is therefore unexpected. Exchange account state is authoritative;
    this function never treats a submit response as proof of position state.
    """

    cfg = cfg or CanaryControllerConfig()
    cfg.validate()
    policy_cfg = policy_cfg or CanaryPolicyConfig(
        target_notional_usd=cfg.target_notional_usd,
        max_hold_seconds=cfg.max_hold_seconds,
        neutral_exit_streak=cfg.neutral_exit_streak,
        cooldown_seconds=cfg.cooldown_seconds,
    )

    has_inventory = any(abs(float(v)) > 0 for v in account.positions.values())

    if session.halt_reason:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "session_halted_with_inventory")
        return CanaryControllerDecision(CanaryAction.HALT, None, None, session.halt_reason)

    if operator.killed:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "operator_kill")
        return CanaryControllerDecision(CanaryAction.HALT, None, None, "operator_kill")

    if not authority_held:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "execution_authority_lost")
        return CanaryControllerDecision(CanaryAction.HALT, None, None, "execution_authority_not_held")

    # Canary V0 uses IOC-only. Any resting order is unexpected and blocks all new
    # risk. Do not cancel unknown orders automatically because ownership is not
    # provable from the public account snapshot.
    if account.open_order_ids:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "unexpected_open_orders")
        return CanaryControllerDecision(CanaryAction.HALT, None, None, "unexpected_open_orders")

    if mission.status == LiveMissionStatus.SUCCESS:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "mission_complete_with_inventory")
        return CanaryControllerDecision(CanaryAction.SUCCESS, None, None, "mission_complete")

    if mission.status == LiveMissionStatus.HALT:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "mission_hard_limit")
        return CanaryControllerDecision(CanaryAction.HALT, None, None, "mission_hard_limit")

    if mission.status == LiveMissionStatus.EPOCH_STOP:
        if has_inventory and session.active_coin:
            return CanaryControllerDecision(CanaryAction.FLATTEN, session.active_coin, None, "epoch_loss_limit")
        return CanaryControllerDecision(CanaryAction.BEGIN_NEXT_EPOCH, None, None, "epoch_loss_limit_flat")

    position = _single_position(account)
    if has_inventory:
        if position is None:
            return CanaryControllerDecision(CanaryAction.HALT, None, None, "multiple_exchange_positions")
        coin, size = position
        if size >= 0:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "unexpected_non_short_position")
        if session.active_coin != coin:
            return CanaryControllerDecision(CanaryAction.HALT, None, None, "unowned_exchange_position")

        if not operator.execution_enabled:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "operator_disabled")

        state_signal = evaluate_short_only_entry(scanner_state, now_ms=now_ms, cfg=policy_cfg)
        if state_signal.intent == CanaryIntent.HALT:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, state_signal.reason)

        decision = scanner_state.get("decision") or {}
        if decision.get("active_market") != coin or decision.get("allow_new_entries") is not True:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "supervisor_no_longer_supports_market")

        if session.opened_at_ms is None:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "missing_open_timestamp")
        held_seconds = max(0.0, (now_ms - session.opened_at_ms) / 1000.0)
        if held_seconds >= cfg.max_hold_seconds:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "max_hold_reached")

        metrics = _active_metrics(scanner_state, coin)
        if metrics is None:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "active_market_metrics_missing")
        direction = aligned_direction(
            metrics,
            min_abs_book_imbalance=policy_cfg.min_abs_book_imbalance,
            min_abs_aggressor_flow=policy_cfg.min_abs_aggressor_flow,
        )
        if direction == -1:
            session.non_bearish_streak = 0
            return CanaryControllerDecision(CanaryAction.HOLD_SHORT, coin, None, "bearish_microstructure_persists")

        session.non_bearish_streak += 1
        if direction == 1:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "microstructure_flipped_bullish")
        if session.non_bearish_streak >= cfg.neutral_exit_streak:
            return CanaryControllerDecision(CanaryAction.FLATTEN, coin, None, "bearish_alignment_lost")
        return CanaryControllerDecision(CanaryAction.HOLD_SHORT, coin, None, "temporary_microstructure_neutral")

    # Flat account from here onward.
    session.active_coin = None
    session.opened_at_ms = None
    session.non_bearish_streak = 0

    if session.pending_coin:
        # An IOC submit was journaled but no position exists. With zero open orders
        # the exchange is flat, so clear pending ownership before considering a new
        # attempt. The runtime persists this transition.
        session.pending_coin = None

    if not operator.execution_enabled:
        return CanaryControllerDecision(CanaryAction.IDLE, None, None, "operator_disabled")

    if session.last_flat_ms is not None:
        cooldown_ms = int(cfg.cooldown_seconds * 1000)
        if now_ms - session.last_flat_ms < cooldown_ms:
            return CanaryControllerDecision(CanaryAction.IDLE, None, None, "cooldown")

    signal = evaluate_short_only_entry(scanner_state, now_ms=now_ms, cfg=policy_cfg)
    if signal.intent == CanaryIntent.HALT:
        return CanaryControllerDecision(CanaryAction.HALT, signal.coin, None, signal.reason)
    if signal.intent != CanaryIntent.ENTER_SHORT or signal.coin is None:
        return CanaryControllerDecision(CanaryAction.IDLE, signal.coin, None, signal.reason)

    # One round trip contributes about 2x entry notional to mission volume. Bound
    # the final attempt to the remaining mission requirement, while respecting the
    # exchange's practical minimum notional.
    half_remaining = max(0.0, mission.remaining_volume_usd / 2.0)
    target_notional = min(cfg.target_notional_usd, half_remaining)
    if target_notional < cfg.minimum_order_notional_usd:
        target_notional = cfg.minimum_order_notional_usd

    return CanaryControllerDecision(
        CanaryAction.OPEN_SHORT,
        signal.coin,
        target_notional,
        signal.reason,
    )
