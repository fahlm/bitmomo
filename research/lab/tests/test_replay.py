"""Replay produces immutable, lineage-carrying, deterministic records; modes stay isolated."""

import datetime as dt
import json

import pyarrow.parquet as pq
import pytest

from bitmomo_lab import build
from bitmomo_lab.engines import opportunity_v1 as opp
from bitmomo_lab.engines.contract import Mode
from bitmomo_lab.replay.runner import run_replay
from bitmomo_lab.sources.registry import get
from bitmomo_lab.store.parquet import ParityContaminationError, read_dataset, write_dataset

from conftest import make_zip, utc
from test_opportunity_parity import random_walk

UM = get("binance_um_klines_5m")


@pytest.fixture
def um_manifest(tmp_path, fake_archive):
    start = utc(2026, 8, 1)
    rows = random_walk(18 * 288, int(start.timestamp()), seed=5)
    for day in range(18):
        chunk = rows[day * 288:(day + 1) * 288]
        text = "\n".join(",".join(str(v) for v in [r[0], r[1], r[2], r[3], r[4], 1.0, r[6], 1, 1, 0.5, 1, 0]) for r in chunk)
        fake_archive.add(UM.url("BTCUSDT", "daily", f"2026-08-{day + 1:02d}"), make_zip("k.csv", text))
    manifest = build.build_dataset("binance_um_klines_5m", "BTCUSDT", dt.date(2026, 8, 1), dt.date(2026, 8, 18),
                                   tmp_path / "data", tmp_path / "manifests", fake_archive)
    return tmp_path / "manifests" / (build.manifest_name("binance_um_klines_5m", "BTCUSDT", dt.date(2026, 8, 1),
                                                         dt.date(2026, 8, 18)) + ".json"), manifest


def replay(tmp_path, manifest_path, mode=Mode.STRICT, runs="runs", clock=lambda: 1.0):
    return run_replay("opportunity-v1-py", [manifest_path], utc(2026, 8, 14), utc(2026, 8, 18, 23, 45), 900, mode,
                      tmp_path / "data", tmp_path / runs, verify_sample=16, clock=clock)


def test_records_carry_lineage_and_are_deterministic(tmp_path, um_manifest):
    manifest_path, manifest = um_manifest
    first = replay(tmp_path, manifest_path, runs="r1", clock=lambda: 1000.0)
    second = replay(tmp_path, manifest_path, runs="r2", clock=lambda: 2000.0)
    assert first["run_id"] == second["run_id"]
    assert first["output"]["logical_content_sha256"] == second["output"]["logical_content_sha256"]
    assert first["manifest_body_sha256"] == second["manifest_body_sha256"]
    assert first["execution"]["computed_at"] != second["execution"]["computed_at"]
    assert first["verification"] == {"path": "batch", "sample_size": 16, "mismatches": 0,
                                     "sample_seed": first["verification"]["sample_seed"]}

    records = read_dataset(tmp_path / "data" / first["output"]["relative_path"]).to_pylist()
    row = next(r for r in records if r["status"] == "available")
    assert row["engine_id"] == "opportunity-v1-py" and row["methodology_version"] == "opportunity-v1"
    assert row["available_at"] == row["event_time"]
    assert json.loads(row["output_json"])["state"] == row["state"]
    assert first["inputs"][0]["content_sha256"] == manifest["normalized"]["content_sha256"]
    assert first["outcome_counts"].get(opp.ERR_INSUFFICIENT, 0) > 0  # warm-up is explicit, not dropped
    assert all(r["computed_at"] for r in records)


def test_parity_runs_are_isolated_from_the_canonical_store(tmp_path, um_manifest):
    manifest_path, _ = um_manifest
    strict = replay(tmp_path, manifest_path)
    parity = replay(tmp_path, manifest_path, mode=Mode.PRODUCTION_PARITY, runs="rp")
    assert parity["output"]["relative_path"].startswith("parity/")
    assert strict["output"]["relative_path"].startswith("intelligence/")
    parity_table = pq.read_table(tmp_path / "data" / parity["output"]["relative_path"], partitioning=None)
    with pytest.raises(ParityContaminationError):
        read_dataset(tmp_path / "data" / parity["output"]["relative_path"])
    with pytest.raises(ParityContaminationError):
        write_dataset(parity_table, tmp_path / "data" / "intelligence" / "x.parquet")
    # Opportunity has no compatibility fills, so logical outputs agree across modes.
    strict_rows = read_dataset(tmp_path / "data" / strict["output"]["relative_path"])
    assert strict_rows["output_sha256"].to_pylist() == parity_table["output_sha256"].to_pylist()
