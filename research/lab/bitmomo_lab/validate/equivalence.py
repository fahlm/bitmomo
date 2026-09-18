"""Empirical semantics checks between archive datasets (no assumption from field names).

1. Metrics ``sum_taker_long_short_vol_ratio`` at create_time T is compared with the
   USD-M kline taker ratio taker_buy / (volume - taker_buy) for the kline opening at
   T - 5m, T and T + 5m. The best-matching alignment reveals which period the metrics
   row describes.
2. Metrics ``sum_open_interest_value / sum_open_interest`` (implied price) is compared
   with kline prices around T, which shows when the OI snapshot was taken.

Inputs are the verified canonical datasets named by committed manifests.
"""

from __future__ import annotations

import collections
import datetime as dt
import json
import pathlib
import statistics

from bitmomo_lab.store.pit import load_verified
from bitmomo_lab.timeutil import INTERVAL_US

FIVE = INTERVAL_US["5m"]


def _load(manifest_path: pathlib.Path, data_dir: pathlib.Path):
    manifest = json.loads(manifest_path.read_text())
    frame = load_verified(data_dir / manifest["normalized"]["relative_path"],
                          manifest["normalized"]["content_sha256"], manifest["dataset"])
    # Full history: every row is available well before this far-future cutoff.
    return frame.as_of(2**62)


def _rel(a: float, b: float) -> float:
    return abs(a - b) / abs(b) if b else float("inf")


def _summary(errors: list[float]) -> dict:
    if not errors:
        return {"n": 0}
    return {
        "n": len(errors),
        "median_rel_err": statistics.median(errors),
        "share_rel_err_lt_1e-4": round(sum(e < 1e-4 for e in errors) / len(errors), 6),
        "share_rel_err_lt_1e-3": round(sum(e < 1e-3 for e in errors) / len(errors), 6),
        "share_rel_err_lt_1e-2": round(sum(e < 1e-2 for e in errors) / len(errors), 6),
    }


def metrics_vs_klines(metrics_manifest: pathlib.Path, klines_manifest: pathlib.Path,
                      data_dir: pathlib.Path) -> dict:
    metrics = _load(metrics_manifest, data_dir)
    klines = _load(klines_manifest, data_dir)
    k_time = [int(t.timestamp() * 1e6) for t in klines["event_time"].to_pylist()]
    k_by_open = {
        t: (o, c, v, b)
        for t, o, c, v, b in zip(k_time, klines["open"].to_pylist(), klines["close"].to_pylist(),
                                 klines["volume"].to_pylist(), klines["taker_buy_volume"].to_pylist())
    }
    m_time = [int(t.timestamp() * 1e6) for t in metrics["event_time"].to_pylist()]
    taker = metrics["sum_taker_long_short_vol_ratio"].to_pylist()
    oi = metrics["sum_open_interest"].to_pylist()
    oi_value = metrics["sum_open_interest_value"].to_pylist()

    shifts = {"kline_open=T-5m": -FIVE, "kline_open=T": 0, "kline_open=T+5m": FIVE}
    taker_err = {name: [] for name in shifts}
    taker_err_by_year = {name: collections.defaultdict(list) for name in shifts}
    price_err = {"kline_open_price_at_T": [], "kline_close_price_at_T(prev close)": [],
                 "kline_close_price_at_T+5m": []}
    for t, ratio, q, qv in zip(m_time, taker, oi, oi_value):
        year = str(dt.datetime.fromtimestamp(t / 1e6, tz=dt.timezone.utc).year)
        if ratio is not None and ratio > 0:
            for name, shift in shifts.items():
                row = k_by_open.get(t + shift)
                if row and row[2] - row[3] > 0:
                    err = _rel(row[3] / (row[2] - row[3]), ratio)
                    taker_err[name].append(err)
                    taker_err_by_year[name][year].append(err)
        if q and qv:
            implied = qv / q
            here, prev = k_by_open.get(t), k_by_open.get(t - FIVE)
            if here:
                price_err["kline_open_price_at_T"].append(_rel(here[0], implied))
                price_err["kline_close_price_at_T+5m"].append(_rel(here[1], implied))
            if prev:
                price_err["kline_close_price_at_T(prev close)"].append(_rel(prev[1], implied))

    return {
        "metrics_rows": metrics.num_rows,
        "taker_ratio_alignment": {name: _summary(errs) for name, errs in taker_err.items()},
        "taker_ratio_alignment_by_year_kline_open=T": {
            year: _summary(errs) for year, errs in sorted(taker_err_by_year["kline_open=T"].items())},
        "oi_implied_price_vs_kline": {name: _summary(errs) for name, errs in price_err.items()},
    }
