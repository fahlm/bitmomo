"""Forward-only, append-only recorder for short-retention Binance derivatives data.

One ``record_once`` call:

1. GETs each endpoint (public, unauthenticated) and stamps ``received_at``;
2. stores the raw response envelope (gzip, content-hashed) — even rejected ones;
3. parses strictly; a malformed response fails closed for that endpoint;
4. appends only *new information*: an identical re-observation of the same
   (dataset, symbol, event_time) is skipped; a changed value is appended as a
   revision with its own later ``available_at`` (the PIT loader serves the
   latest version known at the cutoff);
5. sets ``available_at = received_at`` — a value is never available before our
   process actually saw it.

It never schedules itself, never runs as a daemon, never authenticates.
"""

from __future__ import annotations

import dataclasses
import datetime as dt
import gzip
import hashlib
import json
import pathlib
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Callable, Protocol

import pyarrow as pa
import pyarrow.compute as pc
import pyarrow.parquet as pq

from bitmomo_lab.contract import SCHEMA_VERSION, TS, AvailabilityBasis, Quality
from bitmomo_lab.recorder.endpoints import Endpoint, MalformedResponse, default_endpoints, parse
from bitmomo_lab.store.parquet import write_dataset
from bitmomo_lab.timeutil import iso
from bitmomo_lab.validate.quality import grid_report

SOURCE = "binance_usdm_public_rest"
BASE_URL = "https://fapi.binance.com"
FORBIDDEN_PARAMS = frozenset({"signature", "apikey", "api_key", "timestamp", "recvwindow"})
ALLOWED_PATHS = frozenset(e.path for e in default_endpoints())
USER_AGENT = "bitmomo-lab-recorder/0.1 (research; read-only)"


class Transport(Protocol):
    def get(self, path: str, params: dict[str, str]) -> tuple[int, bytes]: ...


def build_url(path: str, params: dict[str, str]) -> str:
    bad = {k for k in params if k.lower() in FORBIDDEN_PARAMS}
    if bad:
        raise ValueError(f"authenticated/signed parameters are not allowed: {sorted(bad)}")
    if path not in ALLOWED_PATHS:
        raise ValueError(f"path not allowlisted: {path}")
    return f"{BASE_URL}{path}?{urllib.parse.urlencode(sorted(params.items()))}"


class UrllibTransport:
    """Unauthenticated HTTPS GET against the public USD-M market-data API."""

    def __init__(self, timeout: float = 15.0) -> None:
        self.timeout = timeout

    def get(self, path: str, params: dict[str, str]) -> tuple[int, bytes]:
        request = urllib.request.Request(build_url(path, params), headers={"User-Agent": USER_AGENT})
        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:
                return response.status, response.read()
        except urllib.error.HTTPError as exc:
            return exc.code, exc.read()


class FixtureTransport:
    """Replays recorded responses from ``<dir>/<endpoint name>.json`` (tests / offline)."""

    def __init__(self, directory: pathlib.Path, endpoints: list[Endpoint]) -> None:
        self.by_path_period = {(e.path, e.period): e.name for e in endpoints}
        self.directory = directory

    def get(self, path: str, params: dict[str, str]) -> tuple[int, bytes]:
        build_url(path, params)  # same safety checks as live
        name = self.by_path_period[(path, params.get("period"))]
        fixture = json.loads((self.directory / f"{name}.json").read_text())
        return int(fixture["status"]), json.dumps(fixture["body"]).encode()


@dataclasses.dataclass
class EndpointOutcome:
    endpoint: str
    status: str  # stored | unchanged | rejected | http_error
    http_status: int | None
    rows_in_response: int = 0
    rows_new: int = 0
    rows_revised: int = 0
    rows_duplicate: int = 0
    raw_path: str = ""
    error: str = ""


# Quality is compared on purpose: an in-progress period later observed complete
# (same numbers, new quality) must be stored as a new version.
VALUE_EXCLUDED = {"available_at", "received_at", "raw_ref", "request_url", "raw_response_sha256"}


def _dataset_dir(data_dir: pathlib.Path, endpoint: Endpoint, symbol: str) -> pathlib.Path:
    return data_dir / "normalized" / "recorder" / endpoint.dataset / symbol


def load_recorded(data_dir: pathlib.Path, endpoint: Endpoint, symbol: str) -> pa.Table | None:
    directory = _dataset_dir(data_dir, endpoint, symbol)
    parts = sorted(directory.glob("date=*/part-*.parquet"))
    if not parts:
        return None
    # partitioning=None: never let the date=... directory name leak in as a column.
    return pa.concat_tables([pq.read_table(p, partitioning=None) for p in parts], promote_options="default")


def _store_raw(data_dir: pathlib.Path, endpoint: Endpoint, symbol: str, received_us: int, url: str,
               status: int, body: bytes, verdict: str) -> tuple[pathlib.Path, str]:
    body_sha = hashlib.sha256(body).hexdigest()
    day = iso(received_us)[:10]
    path = (data_dir / "raw" / "recorder" / endpoint.dataset / symbol / day
            / f"{received_us}-{body_sha[:12]}.json.gz")
    envelope = {
        "source": SOURCE,
        "endpoint": endpoint.name,
        "request_url": url,
        "received_at": iso(received_us),
        "received_at_us": received_us,
        "http_status": status,
        "body_sha256": body_sha,
        "verdict": verdict,
        "body": body.decode("utf-8", errors="replace"),
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    with gzip.GzipFile(path, "wb", mtime=0) as handle:
        handle.write(json.dumps(envelope, sort_keys=True).encode())
    return path, body_sha


def _rows_table(endpoint: Endpoint, symbol: str, rows: list[dict], received_us: int, url: str,
                body_sha: str, raw_ref: str) -> pa.Table:
    n = len(rows)
    event = [r["event_time_us"] for r in rows]
    incomplete = [endpoint.period_us > 0 and e + endpoint.period_us > received_us for e in event]
    columns = {
        "source": pa.array([SOURCE] * n, pa.string()),
        "dataset": pa.array([endpoint.dataset] * n, pa.string()),
        "symbol": pa.array([symbol] * n, pa.string()),
        "interval": pa.array([endpoint.period or "snapshot"] * n, pa.string()),
        "event_time": pa.array(event, pa.int64()).cast(TS),
        "available_at": pa.array([received_us] * n, pa.int64()).cast(TS),
        "availability_basis": pa.array([AvailabilityBasis.RECEIVED_AT.value] * n, pa.string()),
        "schema_version": pa.array([SCHEMA_VERSION] * n, pa.string()),
        "quality": pa.array([Quality.PERIOD_INCOMPLETE.value if i else Quality.OK.value for i in incomplete], pa.string()),
        "raw_ref": pa.array([raw_ref] * n, pa.string()),
        "received_at": pa.array([received_us] * n, pa.int64()).cast(TS),
        "request_url": pa.array([url] * n, pa.string()),
        "raw_response_sha256": pa.array([body_sha] * n, pa.string()),
    }
    for key in endpoint.numeric_keys:
        columns[key] = pa.array([r[key] for r in rows], pa.float64())
    return pa.table(columns)


def _value_tuple(row: dict) -> tuple:
    return tuple(sorted((k, v) for k, v in row.items() if k not in VALUE_EXCLUDED))


def _latest_by_key(existing: pa.Table | None) -> dict:
    if existing is None:
        return {}
    ordered = existing.sort_by([("event_time", "ascending"), ("available_at", "ascending")])
    latest: dict = {}
    for row in ordered.to_pylist():
        latest[row["event_time"]] = row
    return latest


def record_endpoint(endpoint: Endpoint, symbol: str, transport: Transport, data_dir: pathlib.Path,
                    clock: Callable[[], float] = time.time) -> EndpointOutcome:
    params = {k: v.format(symbol=symbol) for k, v in endpoint.params}
    url = build_url(endpoint.path, params)
    status, body = transport.get(endpoint.path, params)
    received_us = int(clock() * 1_000_000)
    outcome = EndpointOutcome(endpoint.name, "stored", status)

    if status != 200:
        raw_path, _ = _store_raw(data_dir, endpoint, symbol, received_us, url, status, body, "http_error")
        outcome.status, outcome.raw_path, outcome.error = "http_error", str(raw_path), f"HTTP {status}"
        return outcome
    try:
        rows = parse(endpoint, json.loads(body))
    except (MalformedResponse, json.JSONDecodeError, UnicodeDecodeError) as exc:
        raw_path, _ = _store_raw(data_dir, endpoint, symbol, received_us, url, status, body, "rejected")
        outcome.status, outcome.raw_path, outcome.error = "rejected", str(raw_path), str(exc)
        return outcome

    raw_path, body_sha = _store_raw(data_dir, endpoint, symbol, received_us, url, status, body, "accepted")
    raw_ref = str(raw_path.relative_to(data_dir))
    candidate = _rows_table(endpoint, symbol, rows, received_us, url, body_sha, raw_ref)
    outcome.rows_in_response = candidate.num_rows

    latest = _latest_by_key(load_recorded(data_dir, endpoint, symbol))
    keep: list[int] = []
    for index, row in enumerate(candidate.to_pylist()):
        previous = latest.get(row["event_time"])
        if previous is None:
            outcome.rows_new += 1
            keep.append(index)
        elif _value_tuple(previous) == _value_tuple(row):
            outcome.rows_duplicate += 1
        else:
            outcome.rows_revised += 1
            keep.append(index)
    outcome.raw_path = raw_ref
    if not keep:
        outcome.status = "unchanged"
        return outcome

    new_rows = candidate.take(pa.array(keep, pa.int64()))
    day = iso(received_us)[:10]
    part = _dataset_dir(data_dir, endpoint, symbol) / f"date={day}" / f"part-{received_us}-{body_sha[:12]}.parquet"
    write_dataset(new_rows, part)
    return outcome


def record_once(symbol: str, transport: Transport, data_dir: pathlib.Path,
                endpoints: list[Endpoint] | None = None,
                clock: Callable[[], float] = time.time) -> list[EndpointOutcome]:
    return [record_endpoint(e, symbol, transport, data_dir, clock) for e in (endpoints or default_endpoints())]


def recorded_gaps(data_dir: pathlib.Path, endpoint: Endpoint, symbol: str,
                  start: dt.datetime, end: dt.datetime) -> dict:
    """Missing periods in the recorded (ok) series over [start, end)."""
    if not endpoint.period:
        raise ValueError(f"{endpoint.name} is not a fixed-period series")
    table = load_recorded(data_dir, endpoint, symbol)
    if table is None:
        table = pa.table({"event_time": pa.array([], TS), "quality": pa.array([], pa.string())})
    start_us, end_us = int(start.timestamp() * 1e6), int(end.timestamp() * 1e6)
    event = pc.cast(table["event_time"], pa.int64())
    table = table.filter(pc.and_(pc.greater_equal(event, start_us), pc.less(event, end_us)))
    report = grid_report(table, start_us, end_us, endpoint.period_us)
    report.pop("coverage_by_year", None)
    return report
