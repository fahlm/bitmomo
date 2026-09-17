from __future__ import annotations

from dataclasses import dataclass
from enum import Enum
from typing import Any


class MacroPrior(str, Enum):
    BEARISH = "BEARISH"
    NEUTRAL = "NEUTRAL"
    BULLISH = "BULLISH"


class CanaryIntent(str, Enum):
    ENTER_SHORT = "ENTER_SHORT"
    HOLD_SHORT = "HOLD_SHORT"
    EXIT_SHORT = "EXIT_SHORT"
    IDLE = "IDLE"
    HALT = "HALT"


@dataclass(frozen=True)
class CanaryPolicyConfig:
    macro_prior: MacroPrior = MacroPrior.BEARISH
    target_notional_usd: float = 100.0
    min_abs_book_imbalance: float = 0.05
    min_abs_aggressor_flow: float = 0.05
    max_state_age_seconds: float = 90.0
    max_hold_seconds: float = 60.0
    neutral_exit_streak: int = 3
    cooldown_seconds: float = 15.0

    def validate(self) -> None:
        if self.target_notional_usd <= 0:
            raise ValueError("target_notional_usd must be positive")
        if self.min_abs_book_imbalance < 0:
            raise ValueError("min_abs_book_imbalance must be non-negative")
        if self.min_abs_aggressor_flow < 0:
            raise ValueError("min_abs_aggressor_flow must be non-negative")
        if self.max_state_age_seconds <= 0:
            raise ValueError("max_state_age_seconds must be positive")
        if self.max_hold_seconds <= 0:
            raise ValueError("max_hold_seconds must be positive")
        if self.neutral_exit_streak < 1:
            raise ValueError("neutral_exit_streak must be >= 1")
        if self.cooldown_seconds < 0:
            raise ValueError("cooldown_seconds must be non-negative")


@dataclass(frozen=True)
class CanarySignal:
    intent: CanaryIntent
    coin: str | None
    direction: int
    reason: str


def aligned_direction(
    metrics: dict[str, Any],
    *,
    min_abs_book_imbalance: float = 0.05,
    min_abs_aggressor_flow: float = 0.05,
) -> int:
    """Return +1 long, -1 short, 0 none using the frozen 3/3 definition."""

    imbalance = metrics.get("book_imbalance")
    micro = metrics.get("microprice_edge_bps")
    flow = metrics.get("aggressor_flow")
    if imbalance is None or micro is None or flow is None:
        return 0

    imbalance = float(imbalance)
    micro = float(micro)
    flow = float(flow)
    if (
        imbalance >= min_abs_book_imbalance
        and micro > 0
        and flow >= min_abs_aggressor_flow
    ):
        return 1
    if (
        imbalance <= -min_abs_book_imbalance
        and micro < 0
        and flow <= -min_abs_aggressor_flow
    ):
        return -1
    return 0


def _ranked_verdict(state: dict[str, Any], coin: str) -> str | None:
    for row in state.get("ranking") or []:
        if str(row.get("coin")) == coin:
            verdict = row.get("verdict")
            return None if verdict is None else str(verdict)
    return None


def evaluate_short_only_entry(
    state: dict[str, Any],
    *,
    now_ms: int,
    cfg: CanaryPolicyConfig | None = None,
) -> CanarySignal:
    """Evaluate a NEW-entry signal from the read-only scanner state.

    The macro prior is only a direction filter. It never overrides market quality,
    transport health, supervisor authority, or the current 3/3 microstructure
    signal. V0 deliberately has no long entry path.
    """

    cfg = cfg or CanaryPolicyConfig()
    cfg.validate()

    if cfg.macro_prior is not MacroPrior.BEARISH:
        return CanarySignal(CanaryIntent.IDLE, None, 0, "macro_prior_not_bearish")

    if state.get("mode") != "SHADOW_ONLY" or state.get("mainnet_order_submission") is not False:
        return CanarySignal(CanaryIntent.HALT, None, 0, "scanner_mode_not_fail_closed")

    updated_at_ms = int(state.get("updated_at_ms", 0) or 0)
    if updated_at_ms <= 0 or now_ms < updated_at_ms:
        return CanarySignal(CanaryIntent.HALT, None, 0, "invalid_scanner_timestamp")
    age_seconds = (now_ms - updated_at_ms) / 1000.0
    if age_seconds > cfg.max_state_age_seconds:
        return CanarySignal(CanaryIntent.HALT, None, 0, "scanner_state_stale")

    transport = state.get("transport") or {}
    if transport.get("healthy") is not True:
        return CanarySignal(CanaryIntent.HALT, None, 0, "transport_unhealthy")

    decision = state.get("decision") or {}
    if decision.get("allow_new_entries") is not True:
        return CanarySignal(CanaryIntent.IDLE, None, 0, "supervisor_blocks_entry")

    coin = decision.get("active_market")
    if not coin:
        return CanarySignal(CanaryIntent.IDLE, None, 0, "no_active_market")
    coin = str(coin)

    if _ranked_verdict(state, coin) != "QUALIFIED":
        return CanarySignal(CanaryIntent.IDLE, coin, 0, "active_market_not_qualified")

    metrics = (state.get("metrics") or {}).get(coin)
    if not isinstance(metrics, dict):
        return CanarySignal(CanaryIntent.HALT, coin, 0, "active_market_metrics_missing")
    if metrics.get("data_stale") is True:
        return CanarySignal(CanaryIntent.HALT, coin, 0, "active_market_data_stale")

    direction = aligned_direction(
        metrics,
        min_abs_book_imbalance=cfg.min_abs_book_imbalance,
        min_abs_aggressor_flow=cfg.min_abs_aggressor_flow,
    )
    if direction != -1:
        return CanarySignal(CanaryIntent.IDLE, coin, direction, "microstructure_not_bearish_3of3")

    return CanarySignal(CanaryIntent.ENTER_SHORT, coin, -1, "bearish_macro_and_micro_aligned")
