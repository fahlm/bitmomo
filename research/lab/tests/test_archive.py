import datetime as dt

import pytest

from bitmomo_lab.sources.binance_archive import ChecksumError, acquire, acquire_window, plan_files
from bitmomo_lab.sources.registry import get

UM = get("binance_um_klines_5m")
METRICS = get("binance_um_metrics_5m")
FUNDING = get("binance_um_funding_rate")


def test_plan_uses_monthly_for_whole_months_and_daily_for_partial():
    files = plan_files(UM, dt.date(2026, 7, 30), dt.date(2026, 9, 2))
    stamps = [(f.period, f.stamp) for f in files]
    assert stamps == [("daily", "2026-07-30"), ("daily", "2026-07-31"), ("monthly", "2026-08"),
                      ("daily", "2026-09-01"), ("daily", "2026-09-02")]


def test_plan_daily_only_and_monthly_only_datasets():
    assert all(f.period == "daily" for f in plan_files(METRICS, dt.date(2026, 8, 1), dt.date(2026, 8, 31)))
    funding = plan_files(FUNDING, dt.date(2026, 8, 15), dt.date(2026, 9, 10))
    assert [(f.period, f.stamp) for f in funding] == [("monthly", "2026-08"), ("monthly", "2026-09")]


def test_plan_full_window_file_count():
    files = plan_files(UM, dt.date(2020, 1, 1), dt.date(2026, 9, 10))
    assert sum(f.period == "monthly" for f in files) == 80  # 2020-01 .. 2026-08
    assert sum(f.period == "daily" for f in files) == 10


def test_acquire_verifies_checksum_and_caches(tmp_path, fake_archive):
    file = plan_files(UM, dt.date(2026, 8, 1), dt.date(2026, 8, 31))[0]
    url = UM.url("BTCUSDT", file.period, file.stamp)
    fake_archive.add(url, b"zip-bytes")
    raw = acquire(UM, "BTCUSDT", file, tmp_path, fake_archive)
    assert raw.local_path.read_bytes() == b"zip-bytes"
    before = len(fake_archive.requests)
    again = acquire(UM, "BTCUSDT", file, tmp_path, fake_archive)
    assert again.sha256 == raw.sha256 and len(fake_archive.requests) == before  # served from verified cache


def test_checksum_mismatch_fails_closed_and_caches_nothing(tmp_path, fake_archive):
    file = plan_files(UM, dt.date(2026, 8, 1), dt.date(2026, 8, 31))[0]
    url = UM.url("BTCUSDT", file.period, file.stamp)
    fake_archive.add(url, b"zip-bytes")
    fake_archive.tamper.add(url)
    with pytest.raises(ChecksumError):
        acquire(UM, "BTCUSDT", file, tmp_path, fake_archive)
    assert not list(tmp_path.rglob("*.zip"))


def test_tampered_cache_is_refetched(tmp_path, fake_archive):
    file = plan_files(UM, dt.date(2026, 8, 1), dt.date(2026, 8, 31))[0]
    fake_archive.add(UM.url("BTCUSDT", file.period, file.stamp), b"zip-bytes")
    raw = acquire(UM, "BTCUSDT", file, tmp_path, fake_archive)
    raw.local_path.write_bytes(b"corrupted")
    assert acquire(UM, "BTCUSDT", file, tmp_path, fake_archive).local_path.read_bytes() == b"zip-bytes"


def test_malformed_checksum_file_fails_closed(tmp_path, fake_archive):
    file = plan_files(UM, dt.date(2026, 8, 1), dt.date(2026, 8, 31))[0]
    url = UM.url("BTCUSDT", file.period, file.stamp)
    fake_archive.add(url, b"zip-bytes")
    fake_archive.files[url + ".CHECKSUM"] = b"not a checksum"
    with pytest.raises(ChecksumError):
        acquire(UM, "BTCUSDT", file, tmp_path, fake_archive)


def test_missing_archive_files_are_reported_not_invented(tmp_path, fake_archive):
    for day in (1, 3):
        fake_archive.add(METRICS.url("BTCUSDT", "daily", f"2026-08-0{day}"), b"z")
    result = acquire_window(METRICS, "BTCUSDT", dt.date(2026, 8, 1), dt.date(2026, 8, 3), tmp_path, fake_archive)
    assert [f.file.stamp for f in result.files] == ["2026-08-01", "2026-08-03"]
    assert [f.stamp for f in result.missing] == ["2026-08-02"]
