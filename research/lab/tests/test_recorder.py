import gzip
import json
import shutil

import pyarrow as pa
import pyarrow.compute as pc
import pytest

from bitmomo_lab.recorder import recorder
from bitmomo_lab.recorder.endpoints import MalformedResponse, default_endpoints, parse
from bitmomo_lab.store.pit import PITFrame

from conftest import FIXTURES, utc

CLOCK = utc(2026, 9, 18, 10, 7, 30).timestamp()
ENDPOINTS = {e.name: e for e in default_endpoints()}


@pytest.fixture
def fixtures(tmp_path):
    directory = tmp_path / "fixtures"
    shutil.copytree(FIXTURES / "recorder", directory)
    return directory


def run(fixtures, data_dir, clock=CLOCK, names=None):
    endpoints = [ENDPOINTS[n] for n in names] if names else default_endpoints()
    transport = recorder.FixtureTransport(fixtures, endpoints)
    return {o.endpoint: o for o in recorder.record_once("BTCUSDT", transport, data_dir, endpoints, lambda: clock)}


def edit(fixtures, name, mutate):
    path = fixtures / f"{name}.json"
    data = json.loads(path.read_text())
    mutate(data)
    path.write_text(json.dumps(data))


def test_every_fixture_endpoint_records(fixtures, tmp_path):
    outcomes = run(fixtures, tmp_path)
    assert {o.status for o in outcomes.values()} == {"stored"}
    assert len(outcomes) == len(ENDPOINTS)


def test_available_at_is_received_at(fixtures, tmp_path):
    run(fixtures, tmp_path, names=["taker_long_short_ratio_5m"])
    table = recorder.load_recorded(tmp_path, ENDPOINTS["taker_long_short_ratio_5m"], "BTCUSDT")
    received = int(CLOCK * 1e6)
    assert set(pc.cast(table["available_at"], pa.int64()).to_pylist()) == {received}
    assert table["buyVol"].to_pylist() == [410.5, 411.5, 412.5]


def test_rerun_is_idempotent(fixtures, tmp_path):
    run(fixtures, tmp_path)
    second = run(fixtures, tmp_path, clock=CLOCK + 60)
    assert {o.status for o in second.values()} == {"unchanged"}
    parts = list((tmp_path / "normalized").rglob("part-*.parquet"))
    assert len(parts) == len(ENDPOINTS)


def test_changed_value_is_appended_as_revision_and_pit_serves_by_time(fixtures, tmp_path):
    name = "open_interest_hist_5m"
    run(fixtures, tmp_path, names=[name])
    edit(fixtures, name, lambda d: d["body"][-1].update(sumOpenInterestValue="999.0"))
    outcome = run(fixtures, tmp_path, clock=CLOCK + 120, names=[name])[name]
    assert (outcome.status, outcome.rows_revised, outcome.rows_duplicate) == ("stored", 1, 2)
    frame = PITFrame(recorder.load_recorded(tmp_path, ENDPOINTS[name], "BTCUSDT"))
    before = frame.as_of(int(CLOCK * 1e6) + 60_000_000)["sumOpenInterestValue"].to_pylist()
    after = frame.as_of(int(CLOCK * 1e6) + 120_000_000)["sumOpenInterestValue"].to_pylist()
    assert before[-1] != 999.0 and after[-1] == 999.0


def test_in_progress_period_is_flagged_then_completed(fixtures, tmp_path):
    name = "taker_long_short_ratio_1h"  # last row 10:00 period, received 10:07:30 -> incomplete
    run(fixtures, tmp_path, names=[name])
    table = recorder.load_recorded(tmp_path, ENDPOINTS[name], "BTCUSDT")
    assert table["quality"].to_pylist() == ["ok", "ok", "period_incomplete"]
    later = utc(2026, 9, 18, 11, 1).timestamp()
    outcome = run(fixtures, tmp_path, clock=later, names=[name])[name]
    assert outcome.rows_revised == 1  # same numbers, now complete -> new version
    frame = PITFrame(recorder.load_recorded(tmp_path, ENDPOINTS[name], "BTCUSDT"))
    assert frame.as_of(int(CLOCK * 1e6)).num_rows == 2
    assert frame.as_of(int(later * 1e6)).num_rows == 3


@pytest.mark.parametrize("mutate", [
    lambda d: d.update(body={"code": -1121, "msg": "Invalid symbol."}),
    lambda d: d["body"][0].pop("buySellRatio"),
    lambda d: d["body"][0].update(buySellRatio="NaN"),
    lambda d: d["body"][0].update(timestamp="1789725600000"),
    lambda d: d["body"][1].update(timestamp=d["body"][0]["timestamp"]),
    lambda d: d.update(body=[]),
])
def test_malformed_payload_fails_closed_but_keeps_raw(fixtures, tmp_path, mutate):
    name = "taker_long_short_ratio_5m"
    edit(fixtures, name, mutate)
    outcome = run(fixtures, tmp_path, names=[name])[name]
    assert outcome.status == "rejected"
    assert recorder.load_recorded(tmp_path, ENDPOINTS[name], "BTCUSDT") is None
    envelope = json.loads(gzip.decompress((tmp_path / outcome.raw_path).read_bytes()))
    assert envelope["verdict"] == "rejected"


def test_http_error_is_recorded_not_parsed(fixtures, tmp_path):
    edit(fixtures, "premium_index", lambda d: d.update(status=418, body="teapot"))
    outcome = run(fixtures, tmp_path, names=["premium_index"])["premium_index"]
    assert (outcome.status, outcome.http_status) == ("http_error", 418)


def test_signed_or_keyed_requests_are_impossible():
    for bad in ({"signature": "x"}, {"apiKey": "x"}, {"timestamp": "1"}):
        with pytest.raises(ValueError):
            recorder.build_url("/fapi/v1/premiumIndex", bad)
    with pytest.raises(ValueError):
        recorder.build_url("/fapi/v1/order", {"symbol": "BTCUSDT"})


def test_parse_rejects_wrong_container():
    with pytest.raises(MalformedResponse):
        parse(ENDPOINTS["open_interest_hist_5m"], {"not": "a list"})


def test_recorded_gaps_reports_missing_periods(fixtures, tmp_path):
    run(fixtures, tmp_path, names=["global_long_short_account_ratio_5m"])
    report = recorder.recorded_gaps(tmp_path, ENDPOINTS["global_long_short_account_ratio_5m"], "BTCUSDT",
                                    utc(2026, 9, 18, 9, 40), utc(2026, 9, 18, 10, 5))
    assert report["expected_intervals"] == 5
    assert report["missing_intervals"] == 2
    assert report["gaps"][0]["from"] == "2026-09-18T09:40:00Z"
