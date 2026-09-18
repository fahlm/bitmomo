import datetime as dt

import pyarrow as pa
import pyarrow.compute as pc
import pytest

from bitmomo_lab.contract import require_observation_fields
from bitmomo_lab.normalize.archive import (
    assemble,
    normalize_funding_file,
    normalize_kline_file,
    normalize_metrics_file,
)
from bitmomo_lab.sources.registry import get

from conftest import FIXTURES, KLINE_HEADER, kline_rows, make_zip, raw_file, us, utc

SPOT = get("binance_spot_klines_5m")
UM = get("binance_um_klines_5m")
METRICS = get("binance_um_metrics_5m")
FUNDING = get("binance_um_funding_rate")
GOLDEN = FIXTURES / "binance_archive"


def _ints(table, column):
    return pc.cast(table[column], pa.int64()).to_pylist()


def test_real_spot_rows_across_ms_to_us_switch_are_contiguous(tmp_path):
    dec = raw_file(tmp_path, "spot-2024-12.zip",
                   make_zip("a.csv", (GOLDEN / "spot-BTCUSDT-5m-2024-12-tail.csv").read_text()),
                   dt.date(2024, 12, 1), dt.date(2024, 12, 31))
    jan = raw_file(tmp_path, "spot-2025-01.zip",
                   make_zip("b.csv", (GOLDEN / "spot-BTCUSDT-5m-2025-01-head.csv").read_text()),
                   dt.date(2025, 1, 1), dt.date(2025, 1, 31))
    a = normalize_kline_file(SPOT, "BTCUSDT", dec)
    b = normalize_kline_file(SPOT, "BTCUSDT", jan)
    assert a["source_time_unit"].to_pylist() == ["ms", "ms"]
    assert b["source_time_unit"].to_pylist() == ["us", "us"]
    events = _ints(a, "event_time") + _ints(b, "event_time")
    assert events == [us(2024, 12, 31, 23, 50), us(2024, 12, 31, 23, 55), us(2025, 1, 1, 0, 0), us(2025, 1, 1, 0, 5)]
    # Boundary is identical regardless of source unit: available at open + 5m.
    assert _ints(b, "available_at")[0] == us(2025, 1, 1, 0, 5)
    assert _ints(a, "available_at")[-1] == us(2025, 1, 1, 0, 0)
    assert set(a["quality"].to_pylist()) | set(b["quality"].to_pylist()) == {"ok"}
    require_observation_fields(a)
    require_observation_fields(b)


def test_real_um_file_with_header_parses(tmp_path):
    raw = raw_file(tmp_path, "um.zip", make_zip("u.csv", (GOLDEN / "um-BTCUSDT-5m-2026-08-head.csv").read_text()),
                   dt.date(2026, 8, 1), dt.date(2026, 8, 31))
    table = normalize_kline_file(UM, "BTCUSDT", raw)
    assert table.num_rows == 2
    assert table["close"].to_pylist() == [62928.9, 62922.0]
    assert table["taker_buy_volume"].to_pylist() == [295.29, 48.084]


def test_mixed_units_inside_one_file_fail_closed(tmp_path):
    rows = kline_rows(utc(2025, 1, 1), 1, "ms") + kline_rows(utc(2025, 1, 1, 0, 5), 1, "us")
    raw = raw_file(tmp_path, "mixed.zip", make_zip("m.csv", "\n".join(rows)), dt.date(2025, 1, 1), dt.date(2025, 1, 31))
    with pytest.raises(ValueError, match="mixed|different units"):
        normalize_kline_file(SPOT, "BTCUSDT", raw)


def test_unexpected_header_fails_closed(tmp_path):
    text = "open_time,open,high\n" + "\n".join(kline_rows(utc(2025, 1, 1), 2))
    raw = raw_file(tmp_path, "bad.zip", make_zip("b.csv", text), dt.date(2025, 1, 1), dt.date(2025, 1, 31))
    with pytest.raises(ValueError, match="unexpected CSV header"):
        normalize_kline_file(UM, "BTCUSDT", raw)


def test_boundary_and_file_period_violations_are_flagged_not_dropped(tmp_path):
    good = kline_rows(utc(2025, 1, 1), 1)
    off_grid = [f"{us(2025, 1, 1, 0, 7) // 1000},1,2,0,1,1,{us(2025, 1, 1, 0, 12) // 1000 - 1},1,1,1,1,0"]
    bad_close = [f"{us(2025, 1, 1, 0, 10) // 1000},1,2,0,1,1,{us(2025, 1, 1, 0, 15) // 1000},1,1,1,1,0"]
    other_month = kline_rows(utc(2025, 2, 1), 1)
    text = KLINE_HEADER + "\n" + "\n".join(good + off_grid + bad_close + other_month)
    raw = raw_file(tmp_path, "q.zip", make_zip("q.csv", text), dt.date(2025, 1, 1), dt.date(2025, 1, 31))
    table = normalize_kline_file(UM, "BTCUSDT", raw)
    assert table["quality"].to_pylist() == ["ok", "boundary_mismatch", "boundary_mismatch", "out_of_file_period"]


def test_real_metrics_create_time_is_period_start_and_availability_is_conservative(tmp_path):
    raw = raw_file(tmp_path, "m.zip", make_zip("m.csv", (GOLDEN / "um-BTCUSDT-metrics-2020-09-01-head.csv").read_text()),
                   dt.date(2020, 9, 1), dt.date(2020, 9, 1), period="daily")
    table = normalize_metrics_file(METRICS, "BTCUSDT", raw)
    assert _ints(table, "event_time")[0] == us(2020, 9, 1, 0, 0)
    # period end (00:05) + declared 5m lag
    assert _ints(table, "available_at")[0] == us(2020, 9, 1, 0, 10)
    merged, report = assemble([table], us(2020, 9, 1), us(2020, 9, 2))
    assert report.exact_duplicates_collapsed == 1
    assert merged.num_rows == 2
    assert merged["count_long_short_ratio"].to_pylist() == [1.35731217, 1.35924435]


def test_conflicting_duplicates_are_kept_and_flagged(tmp_path):
    header = (GOLDEN / "um-BTCUSDT-metrics-2020-09-01-head.csv").read_text().splitlines()[0]
    text = "\n".join([header,
                      "2020-09-01 00:00:00,BTCUSDT,1,2,3,4,5,6",
                      "2020-09-01 00:00:00,BTCUSDT,1,2,3,4,5,7",
                      "2020-09-01 00:05:00,BTCUSDT,1,2,3,4,5,6"])
    raw = raw_file(tmp_path, "c.zip", make_zip("c.csv", text), dt.date(2020, 9, 1), dt.date(2020, 9, 1), "daily")
    merged, report = assemble([normalize_metrics_file(METRICS, "BTCUSDT", raw)], us(2020, 9, 1), us(2020, 9, 2))
    assert report.conflicting_duplicate_keys == 1
    assert merged["quality"].to_pylist() == ["conflicting_duplicate", "conflicting_duplicate", "ok"]


def test_metrics_missing_values_stay_null(tmp_path):
    header = (GOLDEN / "um-BTCUSDT-metrics-2020-09-01-head.csv").read_text().splitlines()[0]
    text = header + "\n2020-09-01 00:00:00,BTCUSDT,1,2,,4,5,6\n"
    raw = raw_file(tmp_path, "n.zip", make_zip("n.csv", text), dt.date(2020, 9, 1), dt.date(2020, 9, 1), "daily")
    table = normalize_metrics_file(METRICS, "BTCUSDT", raw)
    assert table["count_toptrader_long_short_ratio"].to_pylist() == [None]


def test_real_funding_jitter_is_preserved_and_lag_applied(tmp_path):
    raw = raw_file(tmp_path, "f.zip", make_zip("f.csv", (GOLDEN / "um-BTCUSDT-fundingRate-2026-08-head.csv").read_text()),
                   dt.date(2026, 8, 1), dt.date(2026, 8, 31))
    table = normalize_funding_file(FUNDING, "BTCUSDT", raw)
    events = _ints(table, "event_time")
    assert events[0] == us(2026, 8, 1) + 1000  # +1 ms jitter kept verbatim
    assert _ints(table, "settlement_time_nominal")[0] == us(2026, 8, 1)
    assert _ints(table, "available_at")[0] == events[0] + 300_000_000
    assert table["funding_rate"].to_pylist() == [0.00004123, 0.00003163]


def test_assemble_restricts_to_window_and_sorts(tmp_path):
    rows = kline_rows(utc(2025, 1, 31, 23, 50), 4)  # crosses into Feb
    raw = raw_file(tmp_path, "w.zip", make_zip("w.csv", "\n".join(reversed(rows))), dt.date(2025, 1, 1), dt.date(2025, 2, 28))
    table = normalize_kline_file(UM, "BTCUSDT", raw)
    merged, report = assemble([table], us(2025, 1, 1), us(2025, 2, 1))
    assert report.outside_window_dropped == 2
    assert _ints(merged, "event_time") == [us(2025, 1, 31, 23, 50), us(2025, 1, 31, 23, 55)]
