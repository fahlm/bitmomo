"""Guard for features that depend on the exact period a metrics row describes.

The archive ``metrics`` stamp convention changes over time, and 2025-08-06 .. 2026-04-06
cannot be explained (docs/intelligence-platform/M1_METRICS_ANOMALY_INVESTIGATION.md).
The PIT availability rule (create_time + 10m) already prevents look-ahead everywhere;
this guard addresses the separate question "which market interval does the value
describe?".

Any feature that needs exact metrics-period semantics must obtain rows through
``with_semantics(..., require_verified=True)``: UNVERIFIED days are excluded and
counted, never silently coerced into a convention.
"""

from __future__ import annotations

import datetime as dt
import functools
import json
import pathlib

import pyarrow as pa
import pyarrow.compute as pc

from bitmomo_lab.build import LAB_ROOT

REFERENCE = LAB_ROOT / "registry" / "references" / "metrics_taker_semantics_by_day.json"
UNVERIFIED = "UNVERIFIED"
VERIFIED = frozenset({"period_start", "period_end"})


@functools.lru_cache(maxsize=1)
def _labels(path: str = str(REFERENCE)) -> dict[str, str]:
    return json.loads(pathlib.Path(path).read_text())["labels_by_utc_day"]


def label_for(event_time: dt.datetime) -> str:
    return _labels().get(event_time.astimezone(dt.timezone.utc).strftime("%Y-%m-%d"), UNVERIFIED)


def with_semantics(metrics_rows: pa.Table, require_verified: bool) -> tuple[pa.Table, dict]:
    """Attach ``timestamp_semantics``; optionally drop UNVERIFIED rows. Returns (table, report)."""
    labels = [label_for(t) for t in metrics_rows["event_time"].to_pylist()]
    table = metrics_rows.append_column("timestamp_semantics", pa.array(labels, pa.string()))
    report = {"rows": table.num_rows, "unverified_rows": sum(label == UNVERIFIED for label in labels)}
    if require_verified:
        table = table.filter(pc.is_in(table["timestamp_semantics"], value_set=pa.array(sorted(VERIFIED))))
        report["excluded_unverified"] = report["unverified_rows"]
    return table, report
