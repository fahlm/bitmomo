"""Point-in-time access. ``as_of(T)`` is the only public way to read observations.

Invariant (executable, see ``assert_pit`` and tests/test_pit.py):

    For a replay cutoff T, no engine or feature may consume any observation
    whose ``available_at > T``.

Semantics of ``as_of(T)``:

* only rows with ``available_at <= T`` are visible (late-arriving rows and
  candles that have not closed by T are invisible);
* only ``quality == ok`` rows are served unless explicitly requested;
* when a key (dataset, symbol, event_time) has several versions (a revision
  observed later), the latest version *known at T* is served.
"""

from __future__ import annotations

import datetime as dt
import pathlib

import pyarrow as pa
import pyarrow.compute as pc

from bitmomo_lab.contract import TS, Quality
from bitmomo_lab.store.parquet import content_sha256, read_dataset

PIT_CUTOFF_KEY = b"bitmomo_lab.pit_cutoff_us"
KEY_COLUMNS = ("dataset", "symbol", "event_time")


class LeakageError(AssertionError):
    """An observation with available_at > cutoff reached a consumer."""


class IntegrityError(ValueError):
    """Stored data does not match its manifest."""


def _cutoff_us(cutoff: dt.datetime | int) -> int:
    if isinstance(cutoff, dt.datetime):
        if cutoff.tzinfo is None:
            raise ValueError("cutoff must be timezone-aware (UTC)")
        return int(cutoff.timestamp() * 1_000_000)
    return int(cutoff)


def assert_pit(table: pa.Table, cutoff: dt.datetime | int) -> None:
    """Raise LeakageError if any row became available after the cutoff."""
    t = _cutoff_us(cutoff)
    if table.num_rows == 0:
        return
    latest = pc.max(pc.cast(table["available_at"], pa.int64())).as_py()
    if latest is not None and latest > t:
        raise LeakageError(f"row available at {latest} consumed at cutoff {t}")


class PITFrame:
    """A dataset that can only be read through ``as_of``."""

    __slots__ = ("__table", "name")

    def __init__(self, table: pa.Table, name: str = "") -> None:
        for column in (*KEY_COLUMNS, "available_at", "quality"):
            if column not in table.column_names:
                raise ValueError(f"PIT data requires column {column!r}")
        if not table.schema.field("available_at").type.equals(TS):
            raise TypeError("available_at must be timestamp[us, UTC]")
        self.__table = table
        self.name = name

    @property
    def num_rows(self) -> int:
        return self.__table.num_rows

    def as_of(self, cutoff: dt.datetime | int, *, include_non_ok: bool = False) -> pa.Table:
        t = _cutoff_us(cutoff)
        table = self.__table
        visible = pc.less_equal(pc.cast(table["available_at"], pa.int64()), t)
        if not include_non_ok:
            visible = pc.and_(visible, pc.equal(table["quality"], Quality.OK.value))
        table = table.filter(visible)
        if table.num_rows:
            table = _latest_version(table)
        assert_pit(table, t)
        metadata = dict(table.schema.metadata or {})
        metadata[PIT_CUTOFF_KEY] = str(t).encode()
        return table.replace_schema_metadata(metadata)

    def window(self, cutoff: dt.datetime | int, lookback: dt.timedelta) -> pa.Table:
        """Rows visible at cutoff whose event_time lies in (cutoff - lookback, cutoff]."""
        t = _cutoff_us(cutoff)
        table = self.as_of(t)
        start = t - int(lookback.total_seconds() * 1_000_000)
        event = pc.cast(table["event_time"], pa.int64())
        return table.filter(pc.and_(pc.greater(event, start), pc.less_equal(event, t)))


def _latest_version(table: pa.Table) -> pa.Table:
    """Keep, per key, the row with the greatest available_at (ties broken by raw_ref)."""
    order = [(c, "ascending") for c in KEY_COLUMNS] + [("available_at", "descending")]
    if "raw_ref" in table.column_names:
        order.append(("raw_ref", "descending"))
    ordered = table.sort_by(order)
    keys = list(zip(*(ordered[c].to_pylist() for c in KEY_COLUMNS)))
    first = [0] + [i for i in range(1, len(keys)) if keys[i] != keys[i - 1]]
    if len(first) == ordered.num_rows:
        return ordered
    return ordered.take(pa.array(first, pa.int64()))


def load_verified(path: pathlib.Path, expected_content_sha256: str, name: str = "") -> PITFrame:
    """Load a canonical dataset, refusing it if its content differs from the manifest."""
    table = read_dataset(path)
    actual = content_sha256(table)
    if actual != expected_content_sha256:
        raise IntegrityError(f"{path}: content sha256 {actual} != manifest {expected_content_sha256}")
    return PITFrame(table, name or path.stem)
