"""Data-quality report derived independently from the data itself.

Nothing here knows the historically published row counts; those are only
compared afterwards, in documentation, as verification targets.
"""

from __future__ import annotations

import collections
import datetime as dt

import pyarrow as pa
import pyarrow.compute as pc

from bitmomo_lab.contract import Quality
from bitmomo_lab.timeutil import iso


def out_of_order_rows(table: pa.Table) -> int:
    """Rows whose event_time is earlier than the previous row, in source order."""
    if table.num_rows < 2:
        return 0
    events = pc.cast(table["event_time"], pa.int64()).to_pylist()
    return sum(1 for a, b in zip(events, events[1:]) if b < a)


def _gaps(missing: list[int], period: int) -> list[dict]:
    gaps: list[dict] = []
    for value in missing:
        if gaps and value == gaps[-1]["_last"] + period:
            gaps[-1]["_last"] = value
            gaps[-1]["missing_intervals"] += 1
        else:
            gaps.append({"_first": value, "_last": value, "missing_intervals": 1})
    return [
        {"from": iso(g["_first"]), "to_exclusive": iso(g["_last"] + period), "missing_intervals": g["missing_intervals"]}
        for g in gaps
    ]


def grid_report(table: pa.Table, window_start_us: int, window_end_us: int, period_us: int) -> dict:
    """Completeness of a fixed-cadence dataset over [window_start, window_end)."""
    quality_counts = collections.Counter(table["quality"].to_pylist())
    ok = table.filter(pc.equal(table["quality"], Quality.OK.value))
    events = pc.cast(table["event_time"], pa.int64()).to_pylist()
    ok_events = set(pc.cast(ok["event_time"], pa.int64()).to_pylist())
    expected = range(window_start_us, window_end_us, period_us)
    missing = [t for t in expected if t not in ok_events]
    off_grid = sum(1 for t in ok_events if (t - window_start_us) % period_us)

    per_year: dict[str, dict] = {}
    missing_set = set(missing)
    for t in expected:
        year = dt.datetime.fromtimestamp(t / 1e6, tz=dt.timezone.utc).strftime("%Y")
        bucket = per_year.setdefault(year, {"expected": 0, "present": 0})
        bucket["expected"] += 1
        bucket["present"] += t not in missing_set
    for bucket in per_year.values():
        bucket["coverage_pct"] = round(100 * bucket["present"] / bucket["expected"], 4)

    return {
        "rows": table.num_rows,
        "rows_by_quality": dict(sorted(quality_counts.items())),
        "first_event_time": iso(min(events)) if events else None,
        "last_event_time": iso(max(events)) if events else None,
        "period_us": period_us,
        "expected_intervals": len(expected),
        "present_ok_intervals": len(expected) - len(missing),
        "missing_intervals": len(missing),
        "off_grid_ok_rows": off_grid,
        "gaps": _gaps(missing, period_us),
        "coverage_by_year": dict(sorted(per_year.items())),
    }


def event_report(table: pa.Table, nominal_gap_us: int) -> dict:
    """Irregular event series (funding): spacing statistics instead of a strict grid."""
    ok = table.filter(pc.equal(table["quality"], Quality.OK.value))
    events = sorted(pc.cast(ok["event_time"], pa.int64()).to_pylist())
    spacings = [b - a for a, b in zip(events, events[1:])]
    long_gaps = [
        {"after": iso(a), "before": iso(b), "gap_hours": round((b - a) / 3.6e9, 3)}
        for a, b in zip(events, events[1:])
        if b - a > nominal_gap_us * 1.5
    ]
    return {
        "rows": table.num_rows,
        "rows_by_quality": dict(sorted(collections.Counter(table["quality"].to_pylist()).items())),
        "first_event_time": iso(events[0]) if events else None,
        "last_event_time": iso(events[-1]) if events else None,
        "spacing_hours_histogram": dict(sorted(
            collections.Counter(round(s / 3.6e9) for s in spacings).items())),
        "gaps_longer_than_1_5x_nominal": long_gaps,
    }
