"""Diagnostic (not a hypothesis test): which excursion definition did P0.2C use?

H-OPP-006 is judged only on the registered definition. This module evaluates a small,
declared set of alternative *outcome* definitions against the published P0.2C cells to
explain the systematic divergence. It never changes Opportunity states or the
registered evaluation. Variants tested (all declared up front, counted in the report):

  registered   USD-M 5m high/low path, entry = close at cutoff, event if excursion >= 0.30%
  strict_gt    as registered, but excursion > 0.30%
  closes_only  USD-M 5m closes only (no intra-candle highs/lows)
  spot_path    Binance Spot 5m high/low path and Spot entry close
  spot_closes  Spot 5m closes only
"""

from __future__ import annotations

import json
import pathlib

from bitmomo_lab.build import DEFAULT_DATA_DIR, LAB_ROOT
from bitmomo_lab.replay.runner import load_input
from bitmomo_lab.store.parquet import read_dataset

VARIANTS = ("registered", "strict_gt", "closes_only", "spot_path", "spot_closes")
HORIZONS = (15, 30, 60, 120)
THRESHOLD = 0.003


def _year(cutoff: int) -> int:
    import datetime as dt

    return dt.datetime.fromtimestamp(cutoff, tz=dt.timezone.utc).year


def _series(frame, until_s):
    table = frame.as_of(until_s * 1_000_000)
    opens = [int(v.timestamp()) for v in table["event_time"].to_pylist()]
    return {o: (h, l, c) for o, h, l, c in zip(opens, table["high"].to_pylist(), table["low"].to_pylist(),
                                                table["close"].to_pylist())}


def _event(candles, cutoff, minutes, variant):
    entry = candles.get(cutoff - 300)
    if entry is None:
        return None
    base = entry[2]
    worst = 0.0
    for step in range(minutes // 5):
        c = candles.get(cutoff + step * 300)
        if c is None:
            return None
        if variant in ("closes_only", "spot_closes"):
            worst = max(worst, abs(c[2] / base - 1))
        else:
            worst = max(worst, c[0] / base - 1, 1 - c[1] / base)
    return worst > THRESHOLD if variant == "strict_gt" else worst >= THRESHOLD


def run(run_manifest: pathlib.Path, reference_path: pathlib.Path, data_dir: pathlib.Path = DEFAULT_DATA_DIR) -> dict:
    run = json.loads(run_manifest.read_text())
    records = read_dataset(data_dir / run["output"]["relative_path"]).sort_by([("event_time", "ascending")])
    cutoffs = [int(v.timestamp()) for v in records["event_time"].to_pylist()]
    states = records["state"].to_pylist()
    until = cutoffs[-1] + 7200
    _, um = load_input(LAB_ROOT / "manifests/binance_um_klines_5m__BTCUSDT__2020-01-01__2026-09-10.json", data_dir)
    _, spot = load_input(LAB_ROOT / "manifests/binance_spot_klines_5m__BTCUSDT__2020-01-01__2026-09-10.json", data_dir)
    series = {"um": _series(um, until), "spot": _series(spot, until)}
    reference = json.loads(reference_path.read_text())["full_sample_pct"]
    results = {}
    for variant in VARIANTS:
        candles = series["spot" if variant.startswith("spot") else "um"]
        cells = {}
        for h in HORIZONS:
            for state in ("HIGH", "NORMAL", "LOW"):
                hits = n = 0
                for cutoff, s in zip(cutoffs, states):
                    if s != state:
                        continue
                    e = _event(candles, cutoff, h, variant)
                    if e is None:
                        continue
                    n += 1
                    hits += e
                ours = 100 * hits / n
                cells[f"{state}@{h}m"] = {"platform_pct": round(ours, 2), "published_pct": reference[state][str(h)],
                                          "diff_pp": round(ours - reference[state][str(h)], 2), "n": n}
        if variant in ("registered", "spot_path"):
            yearly = json.loads(reference_path.read_text())["yearly_60m_pct"]
            for year, by_state in yearly.items():
                for state, published in by_state.items():
                    hits = n = 0
                    for cutoff, s in zip(cutoffs, states):
                        if s != state or str(_year(cutoff)) != year:
                            continue
                        e = _event(candles, cutoff, 60, variant)
                        if e is None:
                            continue
                        n += 1
                        hits += e
                    ours = 100 * hits / n
                    cells[f"{year}:{state}@60m"] = {"platform_pct": round(ours, 2), "published_pct": published,
                                                    "diff_pp": round(ours - published, 2), "n": n}
        diffs = [abs(c["diff_pp"]) for c in cells.values()]
        results[variant] = {"cells": cells, "mean_abs_diff_pp": round(sum(diffs) / len(diffs), 3),
                            "max_abs_diff_pp": max(diffs), "within_1pp": sum(d <= 1.0 for d in diffs)}
    return {"variants_tested": len(VARIANTS), "results": results}
