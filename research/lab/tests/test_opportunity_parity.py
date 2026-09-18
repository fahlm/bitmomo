"""Opportunity V1 parity: PHP test vectors, documented semantics, and batch == reference.

The first block ports every check in
website/wp-content/plugins/bitmomo-ai/tests/test-bitmomo-ai-opportunity-v1.php
(git blob e0dc92fd33cf230c16918b480f03c1dde4f102c9), using the same synthetic generator.
"""

import calendar
import datetime as dt
import random

import pyarrow as pa
import pytest

from bitmomo_lab.contract import SCHEMA_VERSION, TS
from bitmomo_lab.engines import opportunity_v1 as opp
from bitmomo_lab.engines.contract import Mode, Snapshot
from bitmomo_lab.store.pit import PITFrame

CUTOFF = calendar.timegm((2026, 9, 12, 6, 0, 0))  # strtotime('2026-09-12T06:00:00Z')


def php_rows(cutoff, historical_half_spread, last_half_spread):
    """Port of opportunity_rows() from the PHP test."""
    rows = []
    first_open = cutoff - opp.REQUIRED_CANDLES * opp.INTERVAL_SECONDS
    for i in range(opp.REQUIRED_CANDLES):
        open_ = first_open + i * opp.INTERVAL_SECONDS
        spread = last_half_spread if i >= opp.REQUIRED_CANDLES - opp.TRAILING_CANDLES else historical_half_spread
        base = 50000.0
        rows.append([open_ * 1000, str(base), str(base + spread), str(base - spread), str(base), "1",
                     (open_ + opp.INTERVAL_SECONDS) * 1000 - 1])
    return rows


# --- ported PHP checks -------------------------------------------------------------

def test_php_complete_synthetic_history_is_high():
    high = opp.evaluate_rows(php_rows(CUTOFF, 20, 500), CUTOFF, CUTOFF + 60)
    assert isinstance(high, dict)
    assert high["state"] == "HIGH"
    assert high["reference_observation_count"] == 1344
    assert high["methodology_version"] == "opportunity-v1" and high["source"] == "binance_usdm_btcusdt_5m"


def test_php_quiet_current_range_is_low():
    assert opp.evaluate_rows(php_rows(CUTOFF, 500, 5), CUTOFF, CUTOFF + 60)["state"] == "LOW"


def test_php_missing_candle_is_insufficient_history():
    rows = php_rows(CUTOFF, 20, 500)
    del rows[1000]
    assert opp.evaluate_rows(rows, CUTOFF, CUTOFF + 60) == opp.Unavailable(opp.ERR_INSUFFICIENT)


def test_php_misaligned_candle_is_source_gap():
    rows = php_rows(CUTOFF, 20, 500)
    rows[2000][0] += 60000
    rows[2000][6] += 60000
    assert opp.evaluate_rows(rows, CUTOFF, CUTOFF + 60) == opp.Unavailable(opp.ERR_GAP)


def test_php_scheduler_rules():
    t = lambda s: calendar.timegm(dt.datetime.fromisoformat(s).timetuple())  # noqa: E731
    assert opp.next_run_timestamp(t("2026-09-12T06:07:00")) == t("2026-09-12T06:16:00")
    assert opp.next_run_timestamp(t("2026-09-12T06:16:01")) == t("2026-09-12T06:31:00")


def test_php_state_boundaries():
    assert (opp.state_for(50), opp.state_for(75), opp.state_for(25)) == ("NORMAL", "HIGH", "LOW")
    assert (opp.state_for(74.9999), opp.state_for(25.0001)) == ("NORMAL", "NORMAL")


# --- exact values of the PHP vectors (hand-derived) ---------------------------------

def test_exact_values_of_high_vector():
    high = opp.evaluate_rows(php_rows(CUTOFF, 20, 500), CUTOFF, CUTOFF + 60)
    # current: (50500 - 49500) / 49500 * 100
    assert high["range_60m_pct"] == opp.php_round(1000 / 49500 * 100, 8) == 2.02020202
    assert high["activity_percentile"] == 100.0
    assert high["knowledge_time"] == "2026-09-12T06:00:00+00:00"
    assert high["evaluated_at"] == "2026-09-12T06:01:00+00:00"
    assert high["source_last_close_time"] == "2026-09-12T05:59:59+00:00"
    assert high["source_age_seconds"] == 60 and high["source_integrity"] == "complete"


def test_exact_values_of_low_vector():
    low = opp.evaluate_rows(php_rows(CUTOFF, 500, 5), CUTOFF, CUTOFF + 60)
    # Reference windows ending 3, 6, 9 candles back still include some of the quiet
    # candles but also historical ones, so their range is the historical 1000/49500;
    # every reference value is > current (10/49995), so the percentile is 0.
    assert low["activity_percentile"] == 0.0
    assert low["range_60m_pct"] == opp.php_round(10 / 49995 * 100, 8)


# --- documented semantics that are reproduced, not "fixed" --------------------------

def test_ties_count_as_below_so_a_flat_market_is_high():
    flat = opp.evaluate_rows(php_rows(CUTOFF, 20, 20), CUTOFF)
    assert flat["activity_percentile"] == 100.0 and flat["state"] == "HIGH"


def test_extra_older_rows_are_ignored_and_duplicates_keep_last():
    rows = php_rows(CUTOFF, 20, 500)
    older = [[rows[0][0] - 300000, "1", "2", "0.5", "1", "1", rows[0][0] - 1]]
    duplicate_last = list(rows[-1])
    duplicate_last[2] = str(50000 + 900)  # later duplicate wins
    result = opp.evaluate_rows(older + rows + [duplicate_last], CUTOFF)
    assert result["range_60m_pct"] == opp.php_round(1400 / 49500 * 100, 8)


def test_open_candle_at_cutoff_is_ignored():
    rows = php_rows(CUTOFF, 20, 500)
    in_progress = [CUTOFF * 1000, "1", "99999", "1", "1", "1", (CUTOFF + 300) * 1000 - 1]
    assert opp.evaluate_rows(rows + [in_progress], CUTOFF) == opp.evaluate_rows(rows, CUTOFF)


def test_non_positive_high_or_low_rows_are_dropped_then_fail_closed():
    rows = php_rows(CUTOFF, 20, 500)
    rows[3000][3] = "0"
    assert opp.evaluate_rows(rows, CUTOFF) == opp.Unavailable(opp.ERR_INSUFFICIENT)


def test_close_time_inside_cutoff_second_is_cutoff_mismatch():
    rows = php_rows(CUTOFF, 20, 500)
    rows[-1][6] = CUTOFF * 1000 + 500  # < (cutoff+1)*1000 passes the filter, > cutoff*1000 fails
    assert opp.evaluate_rows(rows, CUTOFF) == opp.Unavailable(opp.ERR_CUTOFF)


@pytest.mark.parametrize("value,places,expected", [
    (2.5, 0, 3.0), (-2.5, 0, -3.0), (0.285, 2, 0.29), (1.005, 2, 1.01), (1.23456785, 7, 1.2345679),
    (100 * 336 / 1344, 4, 25.0), (100 * 1 / 1344, 4, 0.0744),
])
def test_php_round_half_away_from_zero(value, places, expected):
    assert opp.php_round(value, places) == expected


def test_cutoff_for_matches_evaluate_latest():
    assert opp.cutoff_for(CUTOFF + 29) == CUTOFF - 900
    assert opp.cutoff_for(CUTOFF + 30) == CUTOFF


# --- batch path == reference path ---------------------------------------------------

def random_walk(n, start_open, seed, skip=frozenset()):
    rng = random.Random(seed)
    price = 30000.0
    rows = []
    for i in range(n):
        open_ = start_open + i * 300
        price *= 1 + rng.gauss(0, 0.001)
        wiggle = abs(rng.gauss(0, 0.0015)) * price
        high, low = round(price + wiggle * rng.random(), 2), round(price - wiggle * rng.random(), 2)
        if rng.random() < 0.02:
            high, low = round(price, 2), round(price, 2)  # zero-range candles create ties
        if i not in skip:
            rows.append([open_ * 1000, price, high, low, price, 1.0, (open_ + 300) * 1000 - 1])
    return rows


def rows_to_table(rows):
    n = len(rows)
    return pa.table({
        "source": ["t"] * n, "dataset": [opp.INPUT_DATASET] * n, "symbol": ["BTCUSDT"] * n, "interval": ["5m"] * n,
        "event_time": pa.array([r[0] * 1000 for r in rows], pa.int64()).cast(TS),
        "available_at": pa.array([(r[0] + 300_000) * 1000 for r in rows], pa.int64()).cast(TS),
        "availability_basis": ["close_boundary"] * n, "schema_version": [SCHEMA_VERSION] * n,
        "quality": ["ok"] * n, "raw_ref": ["r"] * n,
        "source_close_time": pa.array([r[6] * 1000 for r in rows], pa.int64()).cast(TS),
        "open": [float(r[1]) for r in rows], "high": [float(r[2]) for r in rows], "low": [float(r[3]) for r in rows],
        "close": [float(r[4]) for r in rows], "volume": [1.0] * n,
    })


def test_batch_equals_reference_including_gaps_and_warmup():
    start = CUTOFF - 20 * 86400
    n = 20 * 288
    rows = random_walk(n, start, seed=7, skip={4500, 4501, 5200})
    table = rows_to_table(rows)
    cutoffs = list(range(start + 13 * 86400, start + n * 300 + 1, 900))
    batch = opp.BatchEvaluator(table).evaluate(cutoffs)
    reference_rows = opp.table_to_rows(table)
    statuses = set()
    for cutoff, got in zip(cutoffs, batch):
        visible = [r for r in reference_rows if r[0] <= (cutoff - 300) * 1000]
        expected = opp.normalize_output(opp.evaluate_rows(visible[-opp.REQUIRED_CANDLES:], cutoff), cutoff)
        assert got == expected, cutoff
        statuses.add(got["error_code"] or got["state"])
    assert {opp.ERR_INSUFFICIENT, opp.ERR_GAP}.issubset(statuses)
    assert {"HIGH", "NORMAL", "LOW"}.issubset(statuses)


def test_engine_through_pit_snapshot_equals_reference():
    start = CUTOFF - 16 * 86400
    rows = random_walk(16 * 288, start, seed=11)
    frame = PITFrame(rows_to_table(rows))
    engine = opp.OpportunityV1()
    for cutoff in (CUTOFF - 3600, CUTOFF - 900, CUTOFF):
        when = dt.datetime.fromtimestamp(cutoff, tz=dt.timezone.utc)
        result = engine.run(Snapshot.build(when, {opp.INPUT_DATASET: frame}), Mode.STRICT)
        visible = [r for r in rows if r[0] <= (cutoff - 300) * 1000]
        assert result.output == opp.normalize_output(opp.evaluate_rows(visible, cutoff), cutoff)
        parity = engine.run(Snapshot.build(when, {opp.INPUT_DATASET: frame}), Mode.PRODUCTION_PARITY)
        assert parity.output == result.output and parity.imputation_flags == ()


def test_batch_refuses_inputs_that_break_the_pit_invariant():
    rows = random_walk(100, CUTOFF - 100 * 300, seed=3)
    table = rows_to_table(rows)
    late = table.set_column(table.column_names.index("available_at"), "available_at",
                            pa.array([(r[0] + 3_600_000) * 1000 for r in rows], pa.int64()).cast(TS))
    with pytest.raises(opp.BatchNotApplicable):
        opp.BatchEvaluator(late)
    revised = pa.concat_tables([table, table.slice(10, 1)])
    with pytest.raises(opp.BatchNotApplicable):
        opp.BatchEvaluator(revised)
