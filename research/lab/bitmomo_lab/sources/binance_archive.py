"""Checksum-verified acquisition of Binance public bulk archives (data.binance.vision).

Raw bytes are cached under ``<data_dir>/raw/binance_public_archive/<archive path>``
with a ``.provenance.json`` sidecar. A file is only accepted when its SHA-256
matches the ``.CHECKSUM`` file Binance publishes next to it; otherwise the
download fails closed and nothing is cached.
"""

from __future__ import annotations

import concurrent.futures
import dataclasses
import datetime as dt
import hashlib
import json
import pathlib
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Callable, Protocol

from bitmomo_lab.sources.registry import DatasetSpec

ALLOWED_HOSTS = frozenset({"data.binance.vision", "s3-ap-northeast-1.amazonaws.com"})
USER_AGENT = "bitmomo-lab/0.1 (research; read-only)"


class NotFound(Exception):
    """The archive does not contain the requested file (a coverage gap, not a failure)."""


class ChecksumError(Exception):
    """Downloaded bytes do not match Binance's published SHA-256."""


class Fetcher(Protocol):
    def __call__(self, url: str) -> bytes: ...


def http_fetch(url: str, *, timeout: float = 60.0, attempts: int = 3) -> bytes:
    """GET a public URL. 404 -> NotFound; other failures retry then raise."""
    host = urllib.parse.urlparse(url).hostname
    if host not in ALLOWED_HOSTS:
        raise ValueError(f"host not allowlisted for archive fetch: {host}")
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    last: Exception | None = None
    for attempt in range(1, attempts + 1):
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                return response.read()
        except urllib.error.HTTPError as exc:
            if exc.code == 404:
                raise NotFound(url) from None
            last = exc
        except (urllib.error.URLError, TimeoutError, ConnectionError) as exc:
            last = exc
        time.sleep(0.5 * attempt)
    raise RuntimeError(f"fetch failed after {attempts} attempts: {url}: {last}")


@dataclasses.dataclass(frozen=True)
class ArchiveFile:
    period: str  # monthly | daily
    stamp: str  # YYYY-MM or YYYY-MM-DD
    first_day: dt.date
    last_day: dt.date


def plan_files(spec: DatasetSpec, start: dt.date, end: dt.date) -> list[ArchiveFile]:
    """Files covering [start, end] (inclusive days): monthly for whole months, daily otherwise."""
    if end < start:
        raise ValueError("end before start")
    files: list[ArchiveFile] = []
    month = dt.date(start.year, start.month, 1)
    while month <= end:
        next_month = dt.date(month.year + (month.month == 12), month.month % 12 + 1, 1)
        month_last = next_month - dt.timedelta(days=1)
        whole = start <= month and month_last <= end
        if spec.monthly and (whole or not spec.daily):
            files.append(ArchiveFile("monthly", month.strftime("%Y-%m"), month, month_last))
        else:
            day = max(start, month)
            while day <= min(end, month_last):
                files.append(ArchiveFile("daily", day.isoformat(), day, day))
                day += dt.timedelta(days=1)
        month = next_month
    return files


def _sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def _parse_checksum(text: str, filename: str) -> str:
    parts = text.split()
    if len(parts) != 2 or parts[1] != filename or len(parts[0]) != 64:
        raise ChecksumError(f"malformed CHECKSUM for {filename}: {text!r}")
    return parts[0].lower()


@dataclasses.dataclass(frozen=True)
class RawFile:
    url: str
    archive_path: str
    local_path: pathlib.Path
    sha256: str
    bytes: int
    file: ArchiveFile


def acquire(
    spec: DatasetSpec,
    symbol: str,
    file: ArchiveFile,
    data_dir: pathlib.Path,
    fetch: Fetcher = http_fetch,
    clock: Callable[[], float] = time.time,
) -> RawFile:
    """Return a verified local copy of one archive file, downloading if needed."""
    archive_path = spec.archive_path(symbol, file.period, file.stamp)
    url = spec.url(symbol, file.period, file.stamp)
    local = data_dir / "raw" / "binance_public_archive" / archive_path
    sidecar = local.with_name(local.name + ".provenance.json")

    if local.exists() and sidecar.exists():
        meta = json.loads(sidecar.read_text())
        data = local.read_bytes()
        if _sha256(data) == meta["sha256"] == meta["upstream_sha256"]:
            return RawFile(url, archive_path, local, meta["sha256"], len(data), file)
        # Cached bytes no longer match what was verified: refuse and re-fetch.
        local.unlink()
        sidecar.unlink()

    expected = _parse_checksum(fetch(url + ".CHECKSUM").decode().strip(), local.name)
    data = fetch(url)
    actual = _sha256(data)
    if actual != expected:
        raise ChecksumError(f"{url}: sha256 {actual} != published {expected}")

    local.parent.mkdir(parents=True, exist_ok=True)
    tmp = local.with_name(local.name + ".partial")
    tmp.write_bytes(data)
    tmp.replace(local)
    sidecar.write_text(
        json.dumps(
            {
                "url": url,
                "archive_path": archive_path,
                "sha256": actual,
                "upstream_sha256": expected,
                "bytes": len(data),
                "fetched_at": dt.datetime.fromtimestamp(clock(), tz=dt.timezone.utc).isoformat(),
            },
            indent=2,
            sort_keys=True,
        )
    )
    return RawFile(url, archive_path, local, actual, len(data), file)


@dataclasses.dataclass(frozen=True)
class AcquireResult:
    files: list[RawFile]
    missing: list[ArchiveFile]


def acquire_window(
    spec: DatasetSpec,
    symbol: str,
    start: dt.date,
    end: dt.date,
    data_dir: pathlib.Path,
    fetch: Fetcher = http_fetch,
    workers: int = 6,
) -> AcquireResult:
    """Acquire every planned file. Missing archive files are reported, never invented."""
    planned = plan_files(spec, start, end)
    results: dict[int, RawFile] = {}
    missing: list[ArchiveFile] = []

    def one(index_file: tuple[int, ArchiveFile]):
        index, file = index_file
        try:
            return index, acquire(spec, symbol, file, data_dir, fetch), None
        except NotFound:
            return index, None, file

    with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as pool:
        for index, raw, gap in pool.map(one, enumerate(planned)):
            if raw is not None:
                results[index] = raw
            else:
                missing.append(gap)
    ordered = [results[i] for i in sorted(results)]
    missing.sort(key=lambda f: f.first_day)
    return AcquireResult(ordered, missing)
