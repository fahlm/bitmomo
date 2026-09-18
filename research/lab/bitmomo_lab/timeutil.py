"""Explicit timestamp-unit handling.

Binance public archives switched Spot kline timestamps from milliseconds to
microseconds starting with the 2025-01 files; USD-M futures files remain in
milliseconds. Units are therefore detected per file, required to be uniform
within a file, and recorded in provenance. Anything that is neither a
13-digit (ms) nor a 16-digit (us) epoch value is rejected.
"""

from __future__ import annotations

import datetime as dt

MS = "ms"
US = "us"

INTERVAL_US = {
    "1m": 60_000_000,
    "5m": 300_000_000,
    "15m": 900_000_000,
    "1h": 3_600_000_000,
    "4h": 14_400_000_000,
    "1d": 86_400_000_000,
}


def epoch_unit(value: int) -> str:
    digits = len(str(abs(int(value))))
    if digits == 13:
        return MS
    if digits == 16:
        return US
    raise ValueError(f"unsupported epoch magnitude ({digits} digits): {value}")


def uniform_unit(values: list[int]) -> str:
    """Return the single unit used by all values, or raise if mixed."""
    if not values:
        raise ValueError("cannot infer a timestamp unit from zero values")
    units = {epoch_unit(v) for v in values}
    if len(units) != 1:
        raise ValueError(f"mixed timestamp units within one source file: {sorted(units)}")
    return units.pop()


def to_us(value: int, unit: str) -> int:
    if unit == MS:
        return int(value) * 1000
    if unit == US:
        return int(value)
    raise ValueError(f"unknown unit {unit!r}")


def parse_utc(text: str) -> int:
    """Parse an ISO-ish UTC string ('2020-09-01 00:00:00' or ISO-8601) to epoch us."""
    text = text.strip().replace("T", " ").removesuffix("Z").removesuffix("+00:00")
    parsed = dt.datetime.strptime(text, "%Y-%m-%d %H:%M:%S").replace(tzinfo=dt.timezone.utc)
    return int(parsed.timestamp()) * 1_000_000


def iso(us: int) -> str:
    return dt.datetime.fromtimestamp(us / 1_000_000, tz=dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def day_start_us(date: dt.date) -> int:
    return int(dt.datetime(date.year, date.month, date.day, tzinfo=dt.timezone.utc).timestamp()) * 1_000_000
