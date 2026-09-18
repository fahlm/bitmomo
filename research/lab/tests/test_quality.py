import pyarrow as pa

from bitmomo_lab.contract import TS
from bitmomo_lab.validate.quality import event_report, grid_report, out_of_order_rows

from conftest import us

FIVE_MIN = 300_000_000


def table(events, qualities=None):
    return pa.table({
        "event_time": pa.array(events, pa.int64()).cast(TS),
        "quality": pa.array(qualities or ["ok"] * len(events)),
    })


def test_gap_detection_counts_and_ranges():
    start = us(2020, 1, 1)
    grid = [start + i * FIVE_MIN for i in range(288)]
    missing = {10, 11, 12, 200}
    present = [t for i, t in enumerate(grid) if i not in missing]
    report = grid_report(table(present), start, start + 288 * FIVE_MIN, FIVE_MIN)
    assert report["expected_intervals"] == 288
    assert report["missing_intervals"] == 4
    assert report["gaps"] == [
        {"from": "2020-01-01T00:50:00Z", "to_exclusive": "2020-01-01T01:05:00Z", "missing_intervals": 3},
        {"from": "2020-01-01T16:40:00Z", "to_exclusive": "2020-01-01T16:45:00Z", "missing_intervals": 1},
    ]
    assert report["coverage_by_year"]["2020"]["present"] == 284


def test_non_ok_rows_count_as_missing_but_are_reported():
    start = us(2020, 1, 1)
    report = grid_report(table([start, start + FIVE_MIN], ["ok", "conflicting_duplicate"]),
                         start, start + 2 * FIVE_MIN, FIVE_MIN)
    assert report["missing_intervals"] == 1
    assert report["rows_by_quality"] == {"conflicting_duplicate": 1, "ok": 1}


def test_coverage_by_year_splits_calendar_years():
    start = us(2020, 12, 31, 23, 50)
    events = [start + i * FIVE_MIN for i in range(4)]
    report = grid_report(table(events), start, start + 4 * FIVE_MIN, FIVE_MIN)
    assert report["coverage_by_year"]["2020"]["expected"] == 2
    assert report["coverage_by_year"]["2021"]["expected"] == 2


def test_out_of_order_rows():
    assert out_of_order_rows(table([3, 1, 2, 5, 4])) == 2
    assert out_of_order_rows(table([1, 2, 3])) == 0


def test_event_report_flags_long_gaps():
    h = 3_600_000_000
    report = event_report(table([0, 8 * h, 16 * h, 40 * h]), nominal_gap_us=8 * h)
    assert report["spacing_hours_histogram"] == {8: 2, 24: 1}
    assert len(report["gaps_longer_than_1_5x_nominal"]) == 1
