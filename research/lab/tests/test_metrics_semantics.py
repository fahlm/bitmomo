import datetime as dt

import pyarrow as pa

from bitmomo_lab.contract import TS
from bitmomo_lab.features import metrics_semantics as ms


def rows(*days):
    times = [int(dt.datetime.fromisoformat(d + "T12:00:00+00:00").timestamp() * 1e6) for d in days]
    return pa.table({"event_time": pa.array(times, pa.int64()).cast(TS), "v": [1.0] * len(days)})


def test_reference_encodes_the_investigated_eras():
    labels = ms._labels()
    assert labels["2023-06-01"] == "period_end"
    assert labels["2024-03-04"] == "period_start"
    assert labels["2025-10-15"] == ms.UNVERIFIED
    assert labels["2026-06-15"] == "period_start"
    assert all(labels[d] == ms.UNVERIFIED for d in ("2025-08-06", "2026-01-01", "2026-04-06"))


def test_verified_only_excludes_and_counts_unverified_days():
    table, report = ms.with_semantics(rows("2023-06-01", "2025-10-15", "2026-06-15"), require_verified=True)
    assert table["timestamp_semantics"].to_pylist() == ["period_end", "period_start"]
    assert report == {"rows": 3, "unverified_rows": 1, "excluded_unverified": 1}


def test_unknown_day_defaults_to_unverified():
    table, report = ms.with_semantics(rows("2019-01-01"), require_verified=False)
    assert table["timestamp_semantics"].to_pylist() == [ms.UNVERIFIED] and report["unverified_rows"] == 1
