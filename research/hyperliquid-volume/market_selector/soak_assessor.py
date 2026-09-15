from __future__ import annotations

import argparse
import json
from dataclasses import asdict, dataclass
from pathlib import Path


@dataclass(frozen=True)
class SoakCriteria:
    min_duration_hours: float = 12.0
    min_healthy_fraction: float = 0.99
    max_sample_gap_seconds: float = 180.0
    max_unhealthy_streak_seconds: float = 180.0


@dataclass(frozen=True)
class SoakAssessment:
    passed: bool
    reasons: tuple[str, ...]
    samples: int
    duration_hours: float
    healthy_fraction: float
    max_sample_gap_seconds: float
    max_unhealthy_streak_seconds: float
    final_healthy: bool
    shadow_only_all: bool
    mainnet_submission_disabled_all: bool
    universe_nonempty_all: bool
    reconnect_events_observed: int
    universe_changes: int
    decision_transitions: int
    halt_samples: int
    book_duplicates_delta: int
    trade_duplicates_delta: int

    def to_dict(self) -> dict:
        return asdict(self)


def load_history(path: str | Path) -> list[dict]:
    target = Path(path)
    rows: list[dict] = []
    with target.open("r", encoding="utf-8") as handle:
        for line_no, line in enumerate(handle, 1):
            if not line.strip():
                continue
            try:
                row = json.loads(line)
            except json.JSONDecodeError as exc:
                raise RuntimeError(f"invalid history JSON at line {line_no}: {exc}") from exc
            if not isinstance(row, dict) or not isinstance(row.get("updated_at_ms"), int):
                raise RuntimeError(f"invalid history record at line {line_no}")
            rows.append(row)
    rows.sort(key=lambda row: row["updated_at_ms"])
    return rows


def _counter_positive_deltas(records: list[dict], section: str, field: str) -> int:
    total = 0
    previous = None
    for row in records:
        value = (row.get(section) or {}).get(field)
        if not isinstance(value, int):
            continue
        if previous is not None and value > previous:
            total += value - previous
        previous = value
    return total


def _change_count(records: list[dict], extractor) -> int:
    count = 0
    previous = None
    for row in records:
        current = extractor(row)
        if previous is not None and current != previous:
            count += 1
        previous = current
    return count


def assess_history(
    records: list[dict],
    *,
    criteria: SoakCriteria = SoakCriteria(),
) -> SoakAssessment:
    if not records:
        return SoakAssessment(
            passed=False,
            reasons=("no_samples",),
            samples=0,
            duration_hours=0.0,
            healthy_fraction=0.0,
            max_sample_gap_seconds=float("inf"),
            max_unhealthy_streak_seconds=float("inf"),
            final_healthy=False,
            shadow_only_all=False,
            mainnet_submission_disabled_all=False,
            universe_nonempty_all=False,
            reconnect_events_observed=0,
            universe_changes=0,
            decision_transitions=0,
            halt_samples=0,
            book_duplicates_delta=0,
            trade_duplicates_delta=0,
        )

    records = sorted(records, key=lambda row: row["updated_at_ms"])
    timestamps = [row["updated_at_ms"] for row in records]
    duration_seconds = max(0.0, (timestamps[-1] - timestamps[0]) / 1000.0)

    gaps = [
        max(0.0, (timestamps[i + 1] - timestamps[i]) / 1000.0)
        for i in range(len(timestamps) - 1)
    ]
    max_gap = max(gaps, default=0.0)

    healthy_samples = sum(
        1 for row in records if (row.get("transport") or {}).get("healthy") is True
    )
    healthy_fraction = healthy_samples / len(records)
    final_healthy = (records[-1].get("transport") or {}).get("healthy") is True

    # Attribute each interval to the health state reported at its beginning. The
    # final sample has no following duration and therefore contributes no seconds.
    current_unhealthy = 0.0
    max_unhealthy = 0.0
    for index, gap in enumerate(gaps):
        healthy = (records[index].get("transport") or {}).get("healthy") is True
        if healthy:
            current_unhealthy = 0.0
        else:
            current_unhealthy += gap
            max_unhealthy = max(max_unhealthy, current_unhealthy)

    shadow_only_all = all(row.get("mode") == "SHADOW_ONLY" for row in records)
    mainnet_disabled_all = all(
        row.get("mainnet_order_submission") is False for row in records
    )
    universe_nonempty_all = all(bool(row.get("universe")) for row in records)

    reasons: list[str] = []
    duration_hours = duration_seconds / 3600.0
    if duration_hours < criteria.min_duration_hours:
        reasons.append(
            f"duration_below_min:{duration_hours:.2f}h<{criteria.min_duration_hours:.2f}h"
        )
    if healthy_fraction < criteria.min_healthy_fraction:
        reasons.append(
            f"healthy_fraction_below_min:{healthy_fraction:.4f}<{criteria.min_healthy_fraction:.4f}"
        )
    if max_gap > criteria.max_sample_gap_seconds:
        reasons.append(
            f"sample_gap_exceeded:{max_gap:.1f}s>{criteria.max_sample_gap_seconds:.1f}s"
        )
    if max_unhealthy > criteria.max_unhealthy_streak_seconds:
        reasons.append(
            f"unhealthy_streak_exceeded:{max_unhealthy:.1f}s>{criteria.max_unhealthy_streak_seconds:.1f}s"
        )
    if not final_healthy:
        reasons.append("final_transport_unhealthy")
    if not shadow_only_all:
        reasons.append("non_shadow_mode_observed")
    if not mainnet_disabled_all:
        reasons.append("mainnet_submission_enabled_observed")
    if not universe_nonempty_all:
        reasons.append("empty_universe_observed")

    halt_samples = sum(
        1 for row in records if (row.get("decision") or {}).get("state") == "HALT"
    )

    return SoakAssessment(
        passed=not reasons,
        reasons=tuple(reasons),
        samples=len(records),
        duration_hours=duration_hours,
        healthy_fraction=healthy_fraction,
        max_sample_gap_seconds=max_gap,
        max_unhealthy_streak_seconds=max_unhealthy,
        final_healthy=final_healthy,
        shadow_only_all=shadow_only_all,
        mainnet_submission_disabled_all=mainnet_disabled_all,
        universe_nonempty_all=universe_nonempty_all,
        reconnect_events_observed=_counter_positive_deltas(
            records, "transport", "reconnects"
        ),
        universe_changes=_change_count(records, lambda row: tuple(row.get("universe") or [])),
        decision_transitions=_change_count(
            records,
            lambda row: (
                (row.get("decision") or {}).get("state"),
                (row.get("decision") or {}).get("active_market"),
                (row.get("decision") or {}).get("action"),
            ),
        ),
        halt_samples=halt_samples,
        book_duplicates_delta=_counter_positive_deltas(
            records, "event_integrity", "book_duplicates"
        ),
        trade_duplicates_delta=_counter_positive_deltas(
            records, "event_integrity", "trade_duplicates"
        ),
    )


def main() -> None:
    parser = argparse.ArgumentParser(description="Assess autonomous shadow soak history.")
    parser.add_argument("history_jsonl")
    parser.add_argument("--min-hours", type=float, default=12.0)
    parser.add_argument("--min-healthy-fraction", type=float, default=0.99)
    parser.add_argument("--max-sample-gap", type=float, default=180.0)
    parser.add_argument("--max-unhealthy-streak", type=float, default=180.0)
    args = parser.parse_args()

    assessment = assess_history(
        load_history(args.history_jsonl),
        criteria=SoakCriteria(
            min_duration_hours=args.min_hours,
            min_healthy_fraction=args.min_healthy_fraction,
            max_sample_gap_seconds=args.max_sample_gap,
            max_unhealthy_streak_seconds=args.max_unhealthy_streak,
        ),
    )
    print(json.dumps(assessment.to_dict(), indent=2, sort_keys=True))
    raise SystemExit(0 if assessment.passed else 2)


if __name__ == "__main__":
    main()
