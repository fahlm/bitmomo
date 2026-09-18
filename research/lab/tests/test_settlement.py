import ast
import datetime as dt
import pathlib

import pyarrow as pa
import pytest

from bitmomo_lab.engines.contract import Mode, Snapshot
from bitmomo_lab.outcomes.settlement import settle
from bitmomo_lab.store.pit import PITFrame

from test_opportunity_parity import rows_to_table

T0 = 1_788_000_000 - 1_788_000_000 % 86400  # a UTC midnight


def candles(prices):
    """prices: list of (high, low, close) for consecutive 5m candles starting at T0."""
    rows = []
    for i, (h, l, c) in enumerate(prices):
        o = T0 + i * 300
        rows.append([o * 1000, c, h, l, c, 1.0, (o + 300) * 1000 - 1])
    return rows


def test_hand_computed_path_metrics():
    # candle 0 is the entry candle (closes at T0+5m); cutoff = T0 + 5m
    path = [(100, 100, 100), (101.2, 99.6, 100.5), (100.8, 99.0, 99.2), (100.4, 99.1, 100.0)]
    frame = PITFrame(rows_to_table(candles(path)))
    out = settle(frame, [T0 + 300], [5, 15], [0.5, 1.0]).to_pylist()[0]
    assert out["entry_price"] == 100
    assert out["ret_5m"] == pytest.approx(0.005)
    assert out["mfe_15m"] == pytest.approx(0.012) and out["mae_15m"] == pytest.approx(-0.01)
    assert out["absexc_15m"] == pytest.approx(0.012)
    assert out["range_15m"] == pytest.approx(0.022)
    assert out["ret_15m"] == pytest.approx(0.0)
    assert out["first_50bp_dir"] == "up" and out["first_50bp_minutes"] == 5  # +1.2% high, low stays above -0.5%
    assert out["first_100bp_dir"] == "up" and out["first_100bp_minutes"] == 5
    assert out["complete_15m"] is True


def test_same_candle_double_touch_is_ambiguous():
    frame = PITFrame(rows_to_table(candles([(100, 100, 100), (101, 99, 100), (100, 100, 100)])))
    out = settle(frame, [T0 + 300], [10], [0.5]).to_pylist()[0]
    assert out["first_50bp_dir"] == "ambiguous"


def test_incomplete_path_is_null_never_zero():
    rows = candles([(100, 100, 100)] * 5)
    del rows[3]  # hole inside the 15m path
    out = settle(PITFrame(rows_to_table(rows)), [T0 + 300], [5, 15], [0.3]).to_pylist()[0]
    assert out["complete_5m"] is True
    assert out["complete_15m"] is False and out["ret_15m"] is None and out["absexc_15m"] is None
    assert out["first_30bp_dir"] is None  # unknown, not "none", because the path was cut short


def test_outcomes_become_available_only_after_the_longest_horizon():
    frame = PITFrame(rows_to_table(candles([(100, 100, 100)] * 100)))
    outcomes = settle(frame, [T0 + 300], [15, 360], [0.3])
    assert outcomes["available_at"].to_pylist()[0].timestamp() == T0 + 300 + 360 * 60


def test_engine_snapshot_cannot_see_outcomes_of_its_own_cutoff():
    """Adversarial: hand the outcome table to an engine as if it were an input."""
    frame = PITFrame(rows_to_table(candles([(100, 100, 100)] * 100)))
    cutoff = T0 + 3000
    outcomes = PITFrame(settle(frame, [cutoff], [15], [0.3]))
    when = dt.datetime.fromtimestamp(cutoff, tz=dt.timezone.utc)
    snapshot = Snapshot.build(when, {"outcomes": outcomes})
    assert snapshot.inputs["outcomes"].num_rows == 0
    later = Snapshot.build(when + dt.timedelta(minutes=15), {"outcomes": outcomes})
    assert later.inputs["outcomes"].num_rows == 1


def test_engine_code_never_imports_outcomes_or_evaluation():
    engines = pathlib.Path(__file__).resolve().parents[1] / "bitmomo_lab" / "engines"
    for path in engines.glob("*.py"):
        tree = ast.parse(path.read_text())
        for node in ast.walk(tree):
            names = []
            if isinstance(node, ast.Import):
                names = [a.name for a in node.names]
            elif isinstance(node, ast.ImportFrom) and node.module:
                names = [node.module]
            for name in names:
                assert not name.startswith(("bitmomo_lab.outcomes", "bitmomo_lab.evaluate")), (path.name, name)
