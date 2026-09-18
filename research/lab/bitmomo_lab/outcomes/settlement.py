"""Forward outcome settlement (labels only).

Settlement is the one component allowed to look past a decision cutoff, so it is kept
structurally apart from prediction:

* it lives in ``bitmomo_lab.outcomes``, which ``bitmomo_lab.engines`` never imports
  (enforced by tests/test_settlement.py);
* every outcome row has ``available_at = cutoff + entry_delay + longest horizon``, so a
  PIT snapshot at the cutoff can never contain the outcome of that cutoff;
* labels are joined to engine records only in ``bitmomo_lab.evaluate``.

Conventions:
entry price = close of the last closed 5m candle at ``cutoff + entry_delay``;
the path for horizon h = the 5m candles opening in [cutoff + delay, cutoff + delay + h).
A horizon is settled only if its whole path exists; otherwise every value for that
horizon is null (never zero) and ``complete_<h>`` is false.
"""

from __future__ import annotations

import math

import pyarrow as pa

from bitmomo_lab.contract import SCHEMA_VERSION, TS, AvailabilityBasis, Quality
from bitmomo_lab.store.pit import PITFrame

DATASET = "outcome.usdm_5m_path"
FIVE_MIN = 300
DIRECTIONS = ("up", "down", "ambiguous", "none")


def horizon_label(minutes: int) -> str:
    return f"{minutes}m"


def threshold_label(pct: float) -> str:
    return f"{int(round(pct * 100))}bp"


def settle(
    klines: PITFrame,
    cutoffs: list[int],
    horizons_minutes: list[int],
    thresholds_pct: list[float],
    entry_delay_seconds: int = 0,
    symbol: str = "BTCUSDT",
) -> pa.Table:
    if entry_delay_seconds % FIVE_MIN:
        raise ValueError("entry delay must be a multiple of 5 minutes")
    horizons = sorted(horizons_minutes)
    longest = horizons[-1] * 60
    # Labeling is the only place allowed to read past the cutoff: up to the last settlement time.
    table = klines.as_of((cutoffs[-1] + entry_delay_seconds + longest) * 1_000_000)
    table = table.sort_by([("event_time", "ascending")])
    opens = [int(v.timestamp()) for v in table["event_time"].to_pylist()]
    highs = table["high"].to_pylist()
    lows = table["low"].to_pylist()
    closes = table["close"].to_pylist()
    index_of = {o: i for i, o in enumerate(opens)}
    checkpoints = {h * 60 // FIVE_MIN: h for h in horizons}
    max_steps = longest // FIVE_MIN

    columns: dict[str, list] = {"entry_price": []}
    for h in horizons:
        for name in ("complete", "ret", "mfe", "mae", "range", "absexc", "rv"):
            columns[f"{name}_{horizon_label(h)}"] = []
    for pct in thresholds_pct:
        columns[f"first_{threshold_label(pct)}_dir"] = []
        columns[f"first_{threshold_label(pct)}_minutes"] = []

    for cutoff in cutoffs:
        start = cutoff + entry_delay_seconds
        entry_index = index_of.get(start - FIVE_MIN)
        first_index = index_of.get(start)
        entry = closes[entry_index] if entry_index is not None else None
        columns["entry_price"].append(entry)
        results: dict[int, dict] = {}
        first_hit: dict[float, tuple[str, int] | None] = {pct: None for pct in thresholds_pct}
        if entry is not None and first_index is not None and entry > 0:
            high = -math.inf
            low = math.inf
            sq = 0.0
            prev_close = entry
            for step in range(max_steps):
                i = first_index + step
                if i >= len(opens) or opens[i] != start + step * FIVE_MIN:
                    break  # path incomplete from here on
                high = max(high, highs[i])
                low = min(low, lows[i])
                sq += math.log(closes[i] / prev_close) ** 2
                prev_close = closes[i]
                for pct in thresholds_pct:
                    if first_hit[pct] is None:
                        up = highs[i] >= entry * (1 + pct / 100)
                        down = lows[i] <= entry * (1 - pct / 100)
                        if up or down:
                            first_hit[pct] = ("ambiguous" if up and down else "up" if up else "down", (step + 1) * 5)
                if step + 1 in checkpoints:
                    mfe, mae = high / entry - 1, low / entry - 1
                    results[checkpoints[step + 1]] = {
                        "ret": closes[i] / entry - 1, "mfe": mfe, "mae": mae, "range": (high - low) / entry,
                        "absexc": max(mfe, -mae), "rv": math.sqrt(sq),
                    }
            complete_path = len(results) == len(horizons)
        else:
            complete_path = False
        for h in horizons:
            values = results.get(h)
            label = horizon_label(h)
            columns[f"complete_{label}"].append(values is not None)
            for name in ("ret", "mfe", "mae", "range", "absexc", "rv"):
                columns[f"{name}_{label}"].append(values[name] if values else None)
        for pct in thresholds_pct:
            label = threshold_label(pct)
            hit = first_hit[pct]
            if hit is None:
                # "none" only when the full longest path was observed; otherwise unknown.
                columns[f"first_{label}_dir"].append("none" if complete_path else None)
                columns[f"first_{label}_minutes"].append(None)
            else:
                columns[f"first_{label}_dir"].append(hit[0])
                columns[f"first_{label}_minutes"].append(hit[1])

    n = len(cutoffs)
    event = pa.array([c * 1_000_000 for c in cutoffs], pa.int64()).cast(TS)
    available = pa.array([(c + entry_delay_seconds + longest) * 1_000_000 for c in cutoffs], pa.int64()).cast(TS)
    out = {
        "source": pa.array(["bitmomo_lab"] * n), "dataset": pa.array([DATASET] * n), "symbol": pa.array([symbol] * n),
        "interval": pa.array(["path"] * n), "event_time": event, "available_at": available,
        "availability_basis": pa.array([AvailabilityBasis.SETTLEMENT_COMPLETE.value] * n),
        "schema_version": pa.array([SCHEMA_VERSION] * n), "quality": pa.array([Quality.OK.value] * n),
        "raw_ref": pa.array([f"entry_delay={entry_delay_seconds}s"] * n),
    }
    for name, values in columns.items():
        if name.startswith("complete_"):
            out[name] = pa.array(values, pa.bool_())
        elif name.endswith("_dir"):
            out[name] = pa.array(values, pa.string())
        elif name.endswith("_minutes"):
            out[name] = pa.array(values, pa.int64())
        else:
            out[name] = pa.array(values, pa.float64())
    return pa.table(out)
