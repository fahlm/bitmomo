"""Evaluation-side features: a volatility context and a naive baseline score.

These are lab features (not production methodology) used to slice and benchmark
Opportunity V1. Both are computed point-in-time from USD-M 5m klines:

* ``abs_return_60m`` v1 — |close(T) / close(T - 60m) - 1| from closed candles only.
* ``vol_context_v1`` — daily log-return realized volatility over 20 closed UTC days,
  compared with the prior 180 valid daily values: low if <= 33rd percentile, high if
  >= 67th, otherwise normal (linear-interpolated quantiles). A day's value becomes
  available at the end of that day (00:00 UTC next day). Mirrors the P0.2C volatility
  context definition, but on USD-M rather than Spot daily candles.
"""

from __future__ import annotations

import bisect
import math
import statistics

from bitmomo_lab.store.pit import PITFrame

ABS_RETURN_60M = ("abs_return_60m", "v1")
VOL_CONTEXT = ("vol_context_v1", "v1")
DAY = 86_400


def _closes(frame: PITFrame, until: int) -> dict[int, float]:
    table = frame.as_of(until * 1_000_000)
    opens = [int(v.timestamp()) for v in table["event_time"].to_pylist()]
    return dict(zip(opens, table["close"].to_pylist()))


def abs_return_60m(frame: PITFrame, cutoffs: list[int]) -> list[float | None]:
    close_by_open = _closes(frame, cutoffs[-1])
    out = []
    for cutoff in cutoffs:
        now, then = close_by_open.get(cutoff - 300), close_by_open.get(cutoff - 3900)
        out.append(abs(now / then - 1) if now and then else None)
    return out


def _quantile(sorted_values: list[float], q: float) -> float:
    position = (len(sorted_values) - 1) * q
    lower = math.floor(position)
    upper = min(lower + 1, len(sorted_values) - 1)
    return sorted_values[lower] + (sorted_values[upper] - sorted_values[lower]) * (position - lower)


def daily_vol_context(frame: PITFrame, until: int, window: int = 20, reference: int = 180) -> list[tuple[int, str]]:
    """[(available_at_unix, label)] per UTC day; label is low/normal/high."""
    close_by_open = _closes(frame, until)
    days = sorted({o - o % DAY for o in close_by_open})
    daily = [(d, close_by_open.get(d + DAY - 300)) for d in days]  # close of the 23:55 candle
    returns: list[tuple[int, float | None]] = []
    for (d0, c0), (d1, c1) in zip(daily, daily[1:]):
        valid = c0 and c1 and d1 - d0 == DAY
        returns.append((d1, math.log(c1 / c0) if valid else None))
    vols: list[tuple[int, float]] = []
    for i in range(window - 1, len(returns)):
        chunk = [r for _, r in returns[i - window + 1:i + 1]]
        if all(r is not None for r in chunk):
            vols.append((returns[i][0], statistics.stdev(chunk)))
    labels = []
    for i in range(reference, len(vols)):
        prior = sorted(v for _, v in vols[i - reference:i])
        current = vols[i][1]
        label = "low" if current <= _quantile(prior, 0.33) else "high" if current >= _quantile(prior, 0.67) else "normal"
        labels.append((vols[i][0] + DAY, label))  # known once the day has closed
    return labels


def vol_context(frame: PITFrame, cutoffs: list[int]) -> list[str | None]:
    labels = daily_vol_context(frame, cutoffs[-1])
    times = [t for t, _ in labels]
    out = []
    for cutoff in cutoffs:
        i = bisect.bisect_right(times, cutoff) - 1
        out.append(labels[i][1] if i >= 0 and cutoff - times[i] < 2 * DAY else None)
    return out
