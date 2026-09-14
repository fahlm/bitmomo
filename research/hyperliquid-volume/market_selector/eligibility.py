from __future__ import annotations

from .config import SelectorConfig
from .models import EligibilityResult, RollingMetrics, Verdict


def _known(value) -> bool:
    return value is not None


def _score_component(ok: bool, weight: float) -> float:
    return weight if ok else 0.0


def evaluate_market(metrics: RollingMetrics, cfg: SelectorConfig) -> EligibilityResult:
    """Deterministic, fail-closed market eligibility evaluation.

    QUALIFIED requires both current structural market prerequisites and sufficiently
    fresh empirical execution economics. Missing/stale empirical metrics never get
    substituted with optimistic values.
    """

    reasons: list[str] = []

    if metrics.hard_fault:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.REJECT,
            score=0.0,
            reasons=[f"hard_fault:{metrics.hard_fault}"],
            hard_fail=True,
            metrics=metrics,
        )

    if metrics.data_stale or metrics.book_age_seconds > cfg.max_book_age_seconds:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.REJECT,
            score=0.0,
            reasons=["stale_book_feed"],
            hard_fail=True,
            metrics=metrics,
        )

    if metrics.observation_seconds < cfg.warmup_seconds:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.WARMUP,
            score=0.0,
            reasons=["warmup_time"],
            metrics=metrics,
        )

    if (
        metrics.book_observations < cfg.min_book_observations
        or metrics.trade_observations < cfg.min_trade_observations
    ):
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.WARMUP,
            score=0.0,
            reasons=["insufficient_market_observations"],
            metrics=metrics,
        )

    structural_checks = {
        "day_volume": (
            _known(metrics.day_volume_usd)
            and metrics.day_volume_usd >= cfg.min_day_volume_usd
        ),
        "spread_floor": (
            _known(metrics.spread_median_bps)
            and metrics.spread_median_bps >= cfg.min_spread_bps
        ),
        "spread_ceiling": (
            _known(metrics.spread_median_bps)
            and metrics.spread_median_bps <= cfg.max_spread_bps
        ),
        "queue_access": (
            _known(metrics.bbo_queue_multiple)
            and metrics.bbo_queue_multiple <= cfg.max_bbo_queue_multiple
        ),
        "trade_activity": (
            _known(metrics.trade_rate_per_minute)
            and metrics.trade_rate_per_minute >= cfg.min_trade_rate_per_minute
        ),
    }

    unknown_structural = [
        name
        for name, value in {
            "day_volume": metrics.day_volume_usd,
            "spread": metrics.spread_median_bps,
            "queue": metrics.bbo_queue_multiple,
            "trade_activity": metrics.trade_rate_per_minute,
        }.items()
        if value is None
    ]

    if unknown_structural:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.UNKNOWN,
            score=0.0,
            reasons=["unknown:" + ",".join(unknown_structural)],
            metrics=metrics,
        )

    structural_failures = [
        name for name, ok in structural_checks.items() if not ok
    ]

    score = 0.0
    score += _score_component(structural_checks["day_volume"], 10.0)
    score += _score_component(structural_checks["spread_floor"], 12.0)
    score += _score_component(structural_checks["spread_ceiling"], 8.0)
    score += _score_component(structural_checks["queue_access"], 15.0)
    score += _score_component(structural_checks["trade_activity"], 10.0)

    if structural_failures:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.REJECT,
            score=score,
            reasons=["structural_fail:" + ",".join(structural_failures)],
            metrics=metrics,
        )

    if metrics.execution_samples < cfg.min_execution_samples:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.WATCH,
            score=score,
            reasons=["insufficient_execution_samples"],
            metrics=metrics,
        )

    if metrics.execution_age_seconds > cfg.max_execution_age_seconds:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.WATCH,
            score=score,
            reasons=["stale_execution_evidence"],
            metrics=metrics,
        )

    execution_values = {
        "fill_rate": metrics.fill_rate,
        "maker_ratio": metrics.maker_ratio,
        "markout_5s": metrics.markout_5s_bps,
        "t10k": metrics.t10k_hours,
        "p10k": metrics.p10k_usd,
    }

    unknown_execution = [
        name for name, value in execution_values.items() if value is None
    ]

    if unknown_execution:
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.WATCH,
            score=score,
            reasons=["unknown_execution:" + ",".join(unknown_execution)],
            metrics=metrics,
        )

    execution_checks = {
        "fill_rate": metrics.fill_rate >= cfg.min_fill_rate,
        "maker_ratio": metrics.maker_ratio >= cfg.min_maker_ratio,
        "markout_5s": metrics.markout_5s_bps >= cfg.min_markout_5s_bps,
        "t10k": metrics.t10k_hours <= cfg.max_t10k_hours,
        "p10k": metrics.p10k_usd >= cfg.min_p10k_usd,
    }

    score += _score_component(execution_checks["fill_rate"], 8.0)
    score += _score_component(execution_checks["maker_ratio"], 12.0)
    score += _score_component(execution_checks["markout_5s"], 10.0)
    score += _score_component(execution_checks["t10k"], 7.0)
    score += _score_component(execution_checks["p10k"], 8.0)

    execution_failures = [
        name for name, ok in execution_checks.items() if not ok
    ]

    if execution_failures:
        reasons.append("execution_fail:" + ",".join(execution_failures))
        return EligibilityResult(
            coin=metrics.coin,
            verdict=Verdict.DEGRADED,
            score=score,
            reasons=reasons,
            metrics=metrics,
        )

    return EligibilityResult(
        coin=metrics.coin,
        verdict=Verdict.QUALIFIED,
        score=score,
        reasons=["all_required_gates_pass"],
        metrics=metrics,
    )
