"""Executable PIT invariant: at cutoff T nothing with available_at > T is ever served."""

import datetime as dt
import random

import pyarrow as pa
import pyarrow.compute as pc
import pytest

from bitmomo_lab.contract import TS
from bitmomo_lab.store.pit import LeakageError, PITFrame, assert_pit

from conftest import us, utc


def frame(rows):
    """rows: (event_time_us, available_at_us, value, quality)"""
    return PITFrame(pa.table({
        "dataset": pa.array(["d"] * len(rows)),
        "symbol": pa.array(["BTCUSDT"] * len(rows)),
        "event_time": pa.array([r[0] for r in rows], pa.int64()).cast(TS),
        "available_at": pa.array([r[1] for r in rows], pa.int64()).cast(TS),
        "value": pa.array([r[2] for r in rows], pa.float64()),
        "quality": pa.array([r[3] if len(r) > 3 else "ok" for r in rows]),
        "raw_ref": pa.array([f"r{i}" for i in range(len(rows))]),
    }))


def values(table):
    return table["value"].to_pylist()


def test_candle_not_yet_closed_is_invisible():
    # 5m candle opened 10:00, closes (available) 10:05
    f = frame([(us(2026, 9, 1, 10, 0), us(2026, 9, 1, 10, 5), 1.0)])
    assert f.as_of(utc(2026, 9, 1, 10, 4, 59)).num_rows == 0
    assert values(f.as_of(utc(2026, 9, 1, 10, 5))) == [1.0]


def test_late_arriving_row_is_invisible_until_it_arrives():
    # An 09:00 observation that only reached us at 12:00.
    f = frame([(us(2026, 9, 1, 9), us(2026, 9, 1, 12), 7.0)])
    assert f.as_of(utc(2026, 9, 1, 11, 59)).num_rows == 0
    assert values(f.as_of(utc(2026, 9, 1, 12))) == [7.0]


def test_revised_row_serves_the_version_known_at_cutoff():
    event = us(2026, 9, 1, 9)
    f = frame([(event, us(2026, 9, 1, 9, 5), 1.0), (event, us(2026, 9, 1, 13), 2.0)])
    assert values(f.as_of(utc(2026, 9, 1, 10))) == [1.0]
    assert values(f.as_of(utc(2026, 9, 1, 13))) == [2.0]


def test_non_ok_rows_are_excluded_by_default():
    f = frame([(us(2026, 9, 1, 9), us(2026, 9, 1, 9, 5), 1.0, "period_incomplete")])
    assert f.as_of(utc(2026, 9, 2)).num_rows == 0
    assert f.as_of(utc(2026, 9, 2), include_non_ok=True).num_rows == 1


def test_incomplete_then_complete_observation():
    event = us(2026, 9, 1, 9)
    f = frame([(event, us(2026, 9, 1, 9, 2), 1.0, "period_incomplete"), (event, us(2026, 9, 1, 9, 7), 1.5)])
    assert f.as_of(utc(2026, 9, 1, 9, 3)).num_rows == 0
    assert values(f.as_of(utc(2026, 9, 1, 9, 7))) == [1.5]


def test_window_bounds_event_time():
    f = frame([(us(2026, 9, 1, h), us(2026, 9, 1, h, 5), float(h)) for h in range(6)])
    # (03:30, 05:30]: the 05:00 row is available at 05:05, so it is inside the window.
    assert values(f.window(utc(2026, 9, 1, 5, 30), dt.timedelta(hours=2))) == [4.0, 5.0]
    assert values(f.window(utc(2026, 9, 1, 5, 4), dt.timedelta(hours=2))) == [4.0]


def test_assert_pit_detects_leak():
    leaked = frame([(us(2026, 9, 1), us(2026, 9, 1, 1), 1.0)])
    # Bypass as_of on purpose to prove the guard itself works.
    with pytest.raises(LeakageError):
        assert_pit(leaked._PITFrame__table, utc(2026, 9, 1, 0, 30))


def test_frame_exposes_no_unfiltered_table():
    f = frame([(us(2026, 9, 1), us(2026, 9, 1), 1.0)])
    assert not any(hasattr(f, name) for name in ("table", "_table", "data", "rows"))
    with pytest.raises(AttributeError):
        f.extra = 1  # __slots__: no ad-hoc attributes


def test_integer_cutoffs_must_be_microseconds():
    f = frame([(us(2026, 9, 1), us(2026, 9, 1), 1.0)])
    for wrong_unit in (1788220800, 1788220800000):  # seconds, milliseconds
        with pytest.raises(ValueError, match="microseconds"):
            f.as_of(wrong_unit)
    assert f.as_of(us(2026, 9, 1)).num_rows == 1


def test_naive_cutoff_is_rejected():
    f = frame([(us(2026, 9, 1), us(2026, 9, 1), 1.0)])
    with pytest.raises(ValueError):
        f.as_of(dt.datetime(2026, 9, 1))


def test_randomized_invariant_matches_brute_force():
    rng = random.Random(20260918)
    base = us(2026, 1, 1)
    rows = []
    for i in range(400):
        event = base + rng.randrange(0, 500) * 300_000_000
        # +i us keeps available_at unique so the expected version is unambiguous.
        rows.append((event, event + rng.randrange(0, 50) * 60_000_000 + i, rng.random(),
                     rng.choice(["ok", "ok", "ok", "conflicting_duplicate"])))
    f = frame(rows)
    for _ in range(200):
        t = base + rng.randrange(0, 600) * 300_000_000
        served = f.as_of(t)
        available = pc.cast(served["available_at"], pa.int64()).to_pylist()
        assert all(a <= t for a in available)
        expected = {}
        for event, avail, value, quality in rows:
            if avail <= t and quality == "ok":
                if event not in expected or avail > expected[event][0]:
                    expected[event] = (avail, value)
        got = dict(zip(pc.cast(served["event_time"], pa.int64()).to_pylist(), values(served)))
        assert got == {event: value for event, (_, value) in expected.items()}
