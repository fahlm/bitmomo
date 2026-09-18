"""Acquire -> normalize -> assemble -> validate -> store -> manifest, for one dataset window.

Manifests are deterministic: they contain no wall-clock timestamps, so the same
raw bytes + same code commit + same parameters produce a byte-identical manifest.
"""

from __future__ import annotations

import dataclasses
import datetime as dt
import hashlib
import json
import pathlib
import platform
import subprocess

import pyarrow as pa

import bitmomo_lab
from bitmomo_lab.contract import SCHEMA_VERSION
from bitmomo_lab.normalize.archive import NORMALIZERS, assemble
from bitmomo_lab.sources import binance_archive, registry
from bitmomo_lab.store.parquet import write_dataset
from bitmomo_lab.timeutil import INTERVAL_US, day_start_us
from bitmomo_lab.validate.quality import event_report, grid_report, out_of_order_rows

LAB_ROOT = pathlib.Path(__file__).resolve().parents[1]
DEFAULT_DATA_DIR = LAB_ROOT / ".data"
DEFAULT_MANIFEST_DIR = LAB_ROOT / "manifests"
MANIFEST_VERSION = "lab-manifest-v1"


def code_identity() -> dict:
    def git(*args: str) -> str:
        return subprocess.run(["git", *args], cwd=LAB_ROOT, capture_output=True, text=True, check=True).stdout.strip()

    try:
        commit = git("rev-parse", "HEAD")
        dirty = bool(git("status", "--porcelain", "--", "bitmomo_lab", "pyproject.toml"))
    except (subprocess.CalledProcessError, FileNotFoundError):
        commit, dirty = "unknown", True
    return {
        "code_commit": commit,
        "code_dirty": dirty,
        "lab_version": bitmomo_lab.__version__,
        "python": platform.python_version(),
        "pyarrow": pa.__version__,
    }


def manifest_name(dataset: str, symbol: str, start: dt.date, end: dt.date) -> str:
    return f"{dataset}__{symbol}__{start.isoformat()}__{end.isoformat()}"


def body_sha256(manifest: dict) -> str:
    # "execution" holds per-run facts (wall clock, duration) and is deliberately unhashed.
    body = {k: v for k, v in manifest.items() if k not in ("manifest_body_sha256", "execution")}
    return hashlib.sha256(json.dumps(body, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def build_dataset(
    dataset: str,
    symbol: str,
    start: dt.date,
    end: dt.date,
    data_dir: pathlib.Path = DEFAULT_DATA_DIR,
    manifest_dir: pathlib.Path = DEFAULT_MANIFEST_DIR,
    fetch: binance_archive.Fetcher = binance_archive.http_fetch,
) -> dict:
    spec = registry.get(dataset)
    acquired = binance_archive.acquire_window(spec, symbol, start, end, data_dir, fetch)
    normalize = NORMALIZERS[spec.parser]
    tables = [normalize(spec, symbol, raw) for raw in acquired.files]
    out_of_order = sum(out_of_order_rows(t) for t in tables if t.num_rows)

    window_start = day_start_us(start)
    window_end = day_start_us(end) + INTERVAL_US["1d"]
    table, assembly = assemble(tables, window_start, window_end)

    name = manifest_name(dataset, symbol, start, end)
    parquet_path = data_dir / "normalized" / dataset / symbol / f"{name}.parquet"
    content_hash, file_hash = write_dataset(table, parquet_path)

    if spec.period_us:
        quality = grid_report(table, window_start, window_end, spec.period_us)
    else:
        quality = event_report(table, nominal_gap_us=INTERVAL_US["1h"] * 8)
    quality["out_of_order_rows_in_source_order"] = out_of_order

    manifest = {
        "manifest_version": MANIFEST_VERSION,
        "dataset": dataset,
        "source": registry.SOURCE,
        "symbol": symbol,
        "interval": spec.interval,
        "window": {"start": start.isoformat(), "end_inclusive": end.isoformat()},
        "schema_version": SCHEMA_VERSION,
        "availability_rule": {
            "basis": spec.availability_basis.value,
            "declared_lag_us": spec.declared_lag_us,
            "period_us": spec.period_us,
            "note": spec.availability_note,
        },
        "raw_files": [
            {
                "archive_path": raw.archive_path,
                "url": raw.url,
                "sha256": raw.sha256,
                "upstream_checksum_verified": True,
                "bytes": raw.bytes,
                "covers": [raw.file.first_day.isoformat(), raw.file.last_day.isoformat()],
            }
            for raw in acquired.files
        ],
        "missing_archive_files": [
            {"archive_path": spec.archive_path(symbol, f.period, f.stamp), "covers": [f.first_day.isoformat(), f.last_day.isoformat()]}
            for f in acquired.missing
        ],
        "normalized": {
            "relative_path": str(parquet_path.relative_to(data_dir)),
            "rows": table.num_rows,
            "content_sha256": content_hash,
            "parquet_file_sha256": file_hash,
            "columns": [f"{field.name}:{field.type}" for field in table.schema],
        },
        "assembly": dataclasses.asdict(assembly),
        "quality": quality,
        "build": code_identity(),
    }
    manifest["manifest_body_sha256"] = body_sha256(manifest)
    manifest_dir.mkdir(parents=True, exist_ok=True)
    (manifest_dir / f"{name}.json").write_text(json.dumps(manifest, indent=2, sort_keys=True) + "\n")
    return manifest
