"""End-to-end: same raw bytes + same code -> byte-identical manifest and dataset."""

import datetime as dt
import json

from bitmomo_lab import build
from bitmomo_lab.cli import main
from bitmomo_lab.sources.registry import get

from conftest import kline_rows, make_zip, utc

UM = get("binance_um_klines_5m")


def populate(fake_archive):
    # August as a monthly file with two missing candles; Sept 1-2 as daily files.
    aug = kline_rows(utc(2026, 8, 1), 31 * 288, skip={100, 101})
    fake_archive.add(UM.url("BTCUSDT", "monthly", "2026-08"), make_zip("a.csv", "\n".join(aug)))
    for day in (1, 2):
        rows = kline_rows(utc(2026, 9, day), 288)
        fake_archive.add(UM.url("BTCUSDT", "daily", f"2026-09-0{day}"), make_zip("d.csv", "\n".join(rows)))


def run_build(tmp_path, name, fake_archive):
    return build.build_dataset("binance_um_klines_5m", "BTCUSDT", dt.date(2026, 8, 1), dt.date(2026, 9, 3),
                               tmp_path / name / "data", tmp_path / name / "manifests", fake_archive)


def test_build_is_deterministic_and_reports_gaps(tmp_path, fake_archive):
    populate(fake_archive)
    first = run_build(tmp_path, "a", fake_archive)
    second = run_build(tmp_path, "b", fake_archive)
    manifest_a = (tmp_path / "a/manifests").glob("*.json").__next__().read_bytes()
    manifest_b = (tmp_path / "b/manifests").glob("*.json").__next__().read_bytes()
    assert manifest_a == manifest_b
    assert first["normalized"]["content_sha256"] == second["normalized"]["content_sha256"]

    quality = first["quality"]
    assert quality["expected_intervals"] == 34 * 288
    assert quality["missing_intervals"] == 288 + 2  # Sept 3 file absent + two skipped candles
    assert quality["gaps"][0] == {"from": "2026-08-01T08:20:00Z", "to_exclusive": "2026-08-01T08:30:00Z",
                                  "missing_intervals": 2}
    assert first["missing_archive_files"][0]["covers"] == ["2026-09-03", "2026-09-03"]
    assert "fetched_at" not in json.dumps(first)  # no wall-clock values in the manifest


def test_verify_command_detects_manifest_edits(tmp_path, fake_archive, capsys):
    populate(fake_archive)
    run_build(tmp_path, "a", fake_archive)
    path = next((tmp_path / "a/manifests").glob("*.json"))
    assert main(["--data-dir", str(tmp_path / "a/data"), "verify", "--manifest", str(path)]) == 0
    manifest = json.loads(path.read_text())
    manifest["quality"]["missing_intervals"] = 0
    path.write_text(json.dumps(manifest))
    assert main(["--data-dir", str(tmp_path / "a/data"), "verify", "--manifest", str(path)]) == 1
