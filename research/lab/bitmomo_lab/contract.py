"""Canonical observation contract shared by every stored dataset.

Every normalized observation carries the fields in ``OBSERVATION_FIELDS``.
Derived features (M2+) additionally carry ``FEATURE_FIELDS``.

Time columns are ``timestamp[us, tz=UTC]`` everywhere. Source timestamps in
milliseconds are converted exactly (x1000); microsecond sources are kept as-is.
"""

from __future__ import annotations

import enum

import pyarrow as pa

SCHEMA_VERSION = "lab-observation-v1"

TS = pa.timestamp("us", tz="UTC")


class Quality(str, enum.Enum):
    """Row-level quality status. Only ``OK`` rows are served by default."""

    OK = "ok"
    # Same key appears more than once in the source with different values.
    CONFLICTING_DUPLICATE = "conflicting_duplicate"
    # Source close/period boundary does not match the declared interval.
    BOUNDARY_MISMATCH = "boundary_mismatch"
    # Row falls outside the period its source file claims to cover.
    OUT_OF_FILE_PERIOD = "out_of_file_period"
    # Live observation of a period that had not finished when it was received.
    PERIOD_INCOMPLETE = "period_incomplete"


class AvailabilityBasis(str, enum.Enum):
    """How ``available_at`` was derived. Recorded per row for auditability."""

    # Kline-style: row is knowable at its closing boundary (open + interval).
    CLOSE_BOUNDARY = "close_boundary"
    # Archive row whose publication latency is unobserved; a declared
    # conservative lag is added after the end of the period it describes.
    PERIOD_END_PLUS_DECLARED_LAG = "period_end_plus_declared_lag"
    # Archive event (e.g. funding settlement) plus a declared conservative lag.
    EVENT_PLUS_DECLARED_LAG = "event_plus_declared_lag"
    # Forward recorder: the moment our own process received the response.
    RECEIVED_AT = "received_at"
    # Engine output: knowable at the decision cutoff it was computed for.
    ENGINE_CUTOFF = "engine_cutoff"
    # Outcome label: knowable only once the longest settled horizon has elapsed.
    SETTLEMENT_COMPLETE = "settlement_complete"


# Columns every normalized observation table must contain.
OBSERVATION_FIELDS: dict[str, pa.DataType] = {
    "source": pa.string(),
    "dataset": pa.string(),
    "symbol": pa.string(),
    "interval": pa.string(),
    "event_time": TS,
    "available_at": TS,
    "availability_basis": pa.string(),
    "schema_version": pa.string(),
    "quality": pa.string(),
    "raw_ref": pa.string(),
}

# Additional columns derived features must carry (used from M2 onwards).
FEATURE_FIELDS: dict[str, pa.DataType] = {
    "feature_id": pa.string(),
    "feature_version": pa.string(),
    "computed_at": TS,
    "input_lineage": pa.string(),
}

# Columns that only exist in production-parity engine outputs. The canonical
# store refuses any table that carries them (see store.parquet.write_dataset).
PARITY_ONLY_FIELDS = frozenset({"imputation_flags", "production_compatibility_fill", "engine_mode"})


def require_observation_fields(table: pa.Table) -> None:
    missing = [name for name in OBSERVATION_FIELDS if name not in table.column_names]
    if missing:
        raise ValueError(f"table is missing canonical observation fields: {missing}")
    for name, dtype in OBSERVATION_FIELDS.items():
        if not table.schema.field(name).type.equals(dtype):
            raise TypeError(f"field {name!r} must be {dtype}, got {table.schema.field(name).type}")
