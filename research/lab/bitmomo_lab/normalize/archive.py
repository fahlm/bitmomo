"""Normalize Binance archive CSVs into canonical PIT observation tables.

Rules (all executable, all tested):

* timestamp units are detected per file and must be uniform (ms or us);
* CSV header presence is detected per file (Binance adds headers to some files only);
* each row's ``available_at`` follows the dataset's declared availability rule;
* boundary/period violations are flagged in ``quality``, never silently dropped;
* exact duplicate rows collapse to one; conflicting duplicates are all kept and
  flagged ``conflicting_duplicate`` (the loader serves only ``ok`` rows);
* missing values stay null — nothing is filled.
"""

from __future__ import annotations

import dataclasses
import io
import zipfile

import pyarrow as pa
import pyarrow.compute as pc
import pyarrow.csv as pacsv

from bitmomo_lab.contract import SCHEMA_VERSION, TS, Quality
from bitmomo_lab.sources.binance_archive import RawFile
from bitmomo_lab.sources.registry import SOURCE, DatasetSpec
from bitmomo_lab.timeutil import INTERVAL_US, MS, US, day_start_us

KLINE_COLUMNS = [
    "open_time", "open", "high", "low", "close", "volume", "close_time",
    "quote_volume", "count", "taker_buy_volume", "taker_buy_quote_volume", "ignore",
]
KLINE_TYPES = {
    "open_time": pa.int64(), "close_time": pa.int64(), "count": pa.int64(),
    **{c: pa.float64() for c in ("open", "high", "low", "close", "volume", "quote_volume",
                                 "taker_buy_volume", "taker_buy_quote_volume")},
    "ignore": pa.string(),
}
METRICS_COLUMNS = [
    "create_time", "symbol", "sum_open_interest", "sum_open_interest_value",
    "count_toptrader_long_short_ratio", "sum_toptrader_long_short_ratio",
    "count_long_short_ratio", "sum_taker_long_short_vol_ratio",
]
METRICS_TYPES = {
    "create_time": pa.timestamp("s"), "symbol": pa.string(),
    **{c: pa.float64() for c in METRICS_COLUMNS[2:]},
}
FUNDING_COLUMNS = ["calc_time", "funding_interval_hours", "last_funding_rate"]
FUNDING_TYPES = {"calc_time": pa.int64(), "funding_interval_hours": pa.int64(), "last_funding_rate": pa.float64()}

# Columns that identify provenance rather than the observed value.
NON_VALUE_COLUMNS = {"raw_ref", "quality", "source_time_unit"}


def _csv_bytes(raw: bytes) -> bytes:
    with zipfile.ZipFile(io.BytesIO(raw)) as archive:
        names = [n for n in archive.namelist() if n.endswith(".csv")]
        if len(names) != 1:
            raise ValueError(f"expected exactly one CSV in archive, found {names}")
        return archive.read(names[0])


def _read_csv(data: bytes, columns: list[str], types: dict[str, pa.DataType]) -> pa.Table:
    first_line = data.split(b"\n", 1)[0].strip()
    if not first_line:
        return pa.table({c: pa.array([], type=types[c]) for c in columns})
    has_header = not first_line[:1].isdigit()
    if has_header:
        header = [h.strip() for h in first_line.decode().split(",")]
        if header != columns:
            raise ValueError(f"unexpected CSV header {header}; expected {columns}")
    return pacsv.read_csv(
        io.BytesIO(data),
        read_options=pacsv.ReadOptions(column_names=columns, skip_rows=1 if has_header else 0),
        convert_options=pacsv.ConvertOptions(
            column_types=types, timestamp_parsers=["%Y-%m-%d %H:%M:%S"], strings_can_be_null=True
        ),
    )


def _unit_of(column: pa.ChunkedArray) -> str:
    lo, hi = pc.min(column).as_py(), pc.max(column).as_py()
    if lo is None:
        raise ValueError("timestamp column is empty or null")
    if 10**12 <= lo and hi < 10**13:
        return MS
    if 10**15 <= lo and hi < 10**16:
        return US
    raise ValueError(f"mixed or unsupported timestamp magnitudes in file: min={lo} max={hi}")


def _us(column: pa.ChunkedArray, unit: str) -> pa.ChunkedArray:
    return pc.multiply(column, 1000) if unit == MS else column


def _ts(values) -> pa.Array:
    return pc.cast(values, pa.int64()).cast(TS)


def _not_on_grid(us_values, period_us: int):
    """True where a non-negative int64 epoch-us value is not a multiple of ``period_us``."""
    return pc.not_equal(pc.multiply(pc.divide(us_values, period_us), period_us), us_values)


def _file_bounds(raw: RawFile) -> tuple[int, int]:
    return day_start_us(raw.file.first_day), day_start_us(raw.file.last_day) + INTERVAL_US["1d"]


def _base(spec: DatasetSpec, symbol: str, raw: RawFile, n: int) -> dict[str, pa.Array]:
    return {
        "source": pa.array([SOURCE] * n, pa.string()),
        "dataset": pa.array([spec.dataset] * n, pa.string()),
        "symbol": pa.array([symbol] * n, pa.string()),
        "interval": pa.array([spec.interval] * n, pa.string()),
        "availability_basis": pa.array([spec.availability_basis.value] * n, pa.string()),
        "schema_version": pa.array([SCHEMA_VERSION] * n, pa.string()),
        "raw_ref": pa.array([f"{raw.archive_path}@{raw.sha256[:16]}"] * n, pa.string()),
    }


def _quality(n: int, *flags: tuple[pa.Array, Quality]) -> pa.Array:
    quality = pa.array([Quality.OK.value] * n, pa.string())
    for mask, flag in flags:
        quality = pc.if_else(mask, pa.scalar(flag.value), quality)
    return quality


def normalize_kline_file(spec: DatasetSpec, symbol: str, raw: RawFile) -> pa.Table:
    table = _read_csv(_csv_bytes(raw.local_path.read_bytes()), KLINE_COLUMNS, KLINE_TYPES)
    n = table.num_rows
    if n == 0:
        return empty_table(spec)
    unit = _unit_of(table["open_time"])
    if _unit_of(table["close_time"]) != unit:
        raise ValueError(f"{raw.archive_path}: open_time and close_time use different units")
    open_us = _us(table["open_time"], unit)
    close_us = _us(table["close_time"], unit)
    period = spec.period_us
    boundary = pc.add(open_us, period)
    # Binance close_time is the last tick of the period: boundary - 1 source unit.
    expected_close = pc.subtract(boundary, 1000 if unit == MS else 1)
    misaligned = pc.or_(_not_on_grid(open_us, period), pc.not_equal(close_us, expected_close))
    lo, hi = _file_bounds(raw)
    outside = pc.or_(pc.less(open_us, lo), pc.greater_equal(open_us, hi))
    columns = _base(spec, symbol, raw, n)
    columns.update(
        event_time=_ts(open_us),
        available_at=_ts(pc.add(boundary, spec.declared_lag_us)),
        quality=_quality(n, (misaligned, Quality.BOUNDARY_MISMATCH), (outside, Quality.OUT_OF_FILE_PERIOD)),
        source_close_time=_ts(close_us),
        source_time_unit=pa.array([unit] * n, pa.string()),
        open=table["open"], high=table["high"], low=table["low"], close=table["close"],
        volume=table["volume"], quote_volume=table["quote_volume"], trade_count=table["count"],
        taker_buy_volume=table["taker_buy_volume"], taker_buy_quote_volume=table["taker_buy_quote_volume"],
    )
    return pa.table(columns)


def normalize_metrics_file(spec: DatasetSpec, symbol: str, raw: RawFile) -> pa.Table:
    table = _read_csv(_csv_bytes(raw.local_path.read_bytes()), METRICS_COLUMNS, METRICS_TYPES)
    n = table.num_rows
    if n == 0:
        return empty_table(spec)
    if pc.any(pc.not_equal(table["symbol"], symbol)).as_py():
        raise ValueError(f"{raw.archive_path}: rows for a symbol other than {symbol}")
    start_us = pc.multiply(pc.cast(table["create_time"], pa.int64()), 1_000_000)
    period = spec.period_us
    misaligned = _not_on_grid(start_us, period)
    lo, hi = _file_bounds(raw)
    outside = pc.or_(pc.less(start_us, lo), pc.greater_equal(start_us, hi))
    columns = _base(spec, symbol, raw, n)
    columns.update(
        event_time=_ts(start_us),
        available_at=_ts(pc.add(pc.add(start_us, period), spec.declared_lag_us)),
        quality=_quality(n, (misaligned, Quality.BOUNDARY_MISMATCH), (outside, Quality.OUT_OF_FILE_PERIOD)),
        **{c: table[c] for c in METRICS_COLUMNS[2:]},
    )
    return pa.table(columns)


def normalize_funding_file(spec: DatasetSpec, symbol: str, raw: RawFile) -> pa.Table:
    table = _read_csv(_csv_bytes(raw.local_path.read_bytes()), FUNDING_COLUMNS, FUNDING_TYPES)
    n = table.num_rows
    if n == 0:
        return empty_table(spec)
    unit = _unit_of(table["calc_time"])
    event_us = _us(table["calc_time"], unit)
    minute = INTERVAL_US["1m"]
    lo, hi = _file_bounds(raw)
    outside = pc.or_(pc.less(event_us, lo), pc.greater_equal(event_us, hi))
    columns = _base(spec, symbol, raw, n)
    columns.update(
        event_time=_ts(event_us),
        available_at=_ts(pc.add(event_us, spec.declared_lag_us)),
        quality=_quality(n, (outside, Quality.OUT_OF_FILE_PERIOD)),
        settlement_time_nominal=_ts(pc.multiply(pc.divide(event_us, minute), minute)),
        source_time_unit=pa.array([unit] * n, pa.string()),
        funding_interval_hours=table["funding_interval_hours"],
        funding_rate=table["last_funding_rate"],
    )
    return pa.table(columns)


NORMALIZERS = {
    "kline": normalize_kline_file,
    "metrics": normalize_metrics_file,
    "funding": normalize_funding_file,
}


def empty_table(spec: DatasetSpec) -> pa.Table:
    return pa.table({"event_time": pa.array([], TS)})


@dataclasses.dataclass(frozen=True)
class AssemblyReport:
    rows_in: int
    rows_out: int
    outside_window_dropped: int
    exact_duplicates_collapsed: int
    conflicting_duplicate_keys: int


def assemble(tables: list[pa.Table], window_start_us: int, window_end_us: int) -> tuple[pa.Table, AssemblyReport]:
    """Concatenate per-file tables, restrict to the window, resolve duplicates, sort."""
    tables = [t for t in tables if t.num_rows]
    if not tables:
        raise ValueError("no rows to assemble")
    table = pa.concat_tables(tables, promote_options="none")
    rows_in = table.num_rows
    event_us = pc.cast(table["event_time"], pa.int64())
    in_window = pc.and_(pc.greater_equal(event_us, window_start_us), pc.less(event_us, window_end_us))
    table = table.filter(in_window)
    outside = rows_in - table.num_rows

    counts = table.group_by("event_time").aggregate([("event_time", "count")])
    dup_keys = counts.filter(pc.greater(counts["event_time_count"], 1))["event_time"]
    exact = conflicting = 0
    if len(dup_keys):
        is_dup = pc.is_in(table["event_time"], value_set=dup_keys.combine_chunks())
        clean = table.filter(pc.invert(is_dup))
        dups = table.filter(is_dup).sort_by([("event_time", "ascending"), ("raw_ref", "ascending")])
        value_cols = [c for c in dups.column_names if c not in NON_VALUE_COLUMNS]
        keep: list[int] = []
        conflict_rows: list[int] = []
        groups: dict[object, list[int]] = {}
        for index, key in enumerate(dups["event_time"].to_pylist()):
            groups.setdefault(key, []).append(index)
        values = dups.select(value_cols).to_pylist()
        for key in sorted(groups):
            members = groups[key]
            distinct = {tuple(sorted(values[i].items())) for i in members}
            if len(distinct) == 1:
                keep.append(members[0])
                exact += len(members) - 1
            else:
                conflict_rows.extend(members)
                conflicting += 1
        selected = sorted(keep + conflict_rows)
        resolved = dups.take(pa.array(selected, pa.int64()))
        conflict_set = set(conflict_rows)
        quality = [
            Quality.CONFLICTING_DUPLICATE.value if index in conflict_set else current
            for index, current in zip(selected, resolved["quality"].to_pylist())
        ]
        resolved = resolved.set_column(
            resolved.column_names.index("quality"), "quality", pa.array(quality, pa.string()))
        table = pa.concat_tables([clean, resolved])
    table = table.sort_by([("event_time", "ascending"), ("raw_ref", "ascending")])
    report = AssemblyReport(rows_in, table.num_rows, outside, exact, conflicting)
    return table, report
