"""Bounded investigation: metrics taker-ratio semantics in 2025-08 .. 2026-03.

Tests a fixed, declared list of explanations. It does not force any month into a
convention; it reports which candidate (if any) matches, per month.

A. File/schema: header, column count, rows per file, timestamp seconds, decimal formatting.
B. Timestamp convention: the kline window [T + k*5m, T + (k+1)*5m) for k = -3..3.
C. Aggregation window: taker ratio over L = 10/15/30/60 min, ending at T, T+5m or starting at T.
D. Definition change: inverted ratio (sell/buy), buy/total, Spot klines instead of USD-M.
E. Corruption: constant/repeated values, non-finite or non-positive values.
"""

from __future__ import annotations

import collections
import datetime as dt
import io
import json
import pathlib
import statistics
import zipfile

from bitmomo_lab.build import DEFAULT_DATA_DIR, LAB_ROOT
from bitmomo_lab.replay.runner import load_input

FIVE = 300
TOL = 1e-3
MONTHS = ("2025-06", "2025-07", "2025-08", "2025-10", "2026-01", "2026-03", "2026-04", "2026-06")


def _month(t: int) -> str:
    return dt.datetime.fromtimestamp(t, tz=dt.timezone.utc).strftime("%Y-%m")


def _klines(manifest: str, data_dir: pathlib.Path) -> dict[int, tuple[float, float]]:
    _, frame = load_input(LAB_ROOT / manifest, data_dir)
    table = frame.as_of(2**62)
    opens = [int(v.timestamp()) for v in table["event_time"].to_pylist()]
    return dict(zip(opens, zip(table["volume"].to_pylist(), table["taker_buy_volume"].to_pylist())))


def _agg(k: dict, start: int, minutes: int) -> tuple[float, float] | None:
    vol = buy = 0.0
    for step in range(minutes // 5):
        row = k.get(start + step * FIVE)
        if row is None:
            return None
        vol += row[0]
        buy += row[1]
    return vol, buy


def _candidates(um: dict, spot: dict, t: int) -> dict[str, float | None]:
    out: dict[str, float | None] = {}

    def ratio(pair, kind="buy/sell"):
        if pair is None:
            return None
        vol, buy = pair
        sell = vol - buy
        if kind == "buy/sell":
            return buy / sell if sell > 0 else None
        if kind == "sell/buy":
            return sell / buy if buy > 0 else None
        return buy / vol if vol > 0 else None

    for k in range(-3, 4):
        out[f"B:um[T{k:+d}*5m]"] = ratio(_agg(um, t + k * FIVE, 5))
    for minutes in (10, 15, 30, 60):
        out[f"C:um_{minutes}m_ending_T"] = ratio(_agg(um, t - minutes * 60, minutes))
        out[f"C:um_{minutes}m_ending_T+5m"] = ratio(_agg(um, t + FIVE - minutes * 60, minutes))
        out[f"C:um_{minutes}m_starting_T"] = ratio(_agg(um, t, minutes))
    for k in (-1, 0):
        out[f"D:um_inverted[T{k:+d}*5m]"] = ratio(_agg(um, t + k * FIVE, 5), "sell/buy")
        out[f"D:um_buy_over_total[T{k:+d}*5m]"] = ratio(_agg(um, t + k * FIVE, 5), "buy/total")
        out[f"D:spot[T{k:+d}*5m]"] = ratio(_agg(spot, t + k * FIVE, 5))
    return out


def raw_file_facts(data_dir: pathlib.Path) -> dict:
    """A. Per-month facts read directly from the verified raw archive files."""
    root = data_dir / "raw" / "binance_public_archive" / "futures/um/daily/metrics/BTCUSDT"
    facts: dict[str, dict] = {}
    for path in sorted(root.glob("*.zip")):
        month = path.name.split("-metrics-")[1][:7]
        if month not in MONTHS:
            continue
        with zipfile.ZipFile(path) as archive:
            text = archive.read(archive.namelist()[0]).decode()
        lines = [line for line in text.splitlines() if line.strip()]
        header = lines[0] if not lines[0][:1].isdigit() else None
        body = lines[1:] if header else lines
        f = facts.setdefault(month, {"files": 0, "headers": collections.Counter(), "rows_per_file": collections.Counter(),
                                     "columns": collections.Counter(), "nonzero_seconds": 0,
                                     "taker_decimals": collections.Counter()})
        f["files"] += 1
        f["headers"][header or "<none>"] += 1
        f["rows_per_file"][len(body)] += 1
        for line in body:
            cells = line.split(",")
            f["columns"][len(cells)] += 1
            f["nonzero_seconds"] += not cells[0].endswith(":00")
            taker = cells[-1]
            f["taker_decimals"][len(taker.split(".")[1]) if "." in taker else 0] += 1
    return {m: {k: (dict(v) if isinstance(v, collections.Counter) else v) for k, v in f.items()} for m, f in facts.items()}


def investigate(data_dir: pathlib.Path = DEFAULT_DATA_DIR) -> dict:
    _, metrics_frame = load_input(LAB_ROOT / "manifests/binance_um_metrics_5m__BTCUSDT__2020-09-01__2026-09-10.json",
                                  data_dir)
    metrics = metrics_frame.as_of(2**62)
    um = _klines("manifests/binance_um_klines_5m__BTCUSDT__2020-01-01__2026-09-10.json", data_dir)
    spot = _klines("manifests/binance_spot_klines_5m__BTCUSDT__2020-01-01__2026-09-10.json", data_dir)
    times = [int(v.timestamp()) for v in metrics["event_time"].to_pylist()]
    taker = metrics["sum_taker_long_short_vol_ratio"].to_pylist()

    per_month: dict[str, dict] = {}
    for t, value in zip(times, taker):
        month = _month(t)
        if month not in MONTHS or value is None:
            continue
        slot = per_month.setdefault(month, {"rows": 0, "match": collections.Counter(), "values": []})
        slot["rows"] += 1
        slot["values"].append(value)
        for name, candidate in _candidates(um, spot, t).items():
            if candidate is not None and abs(candidate - value) / value < TOL:
                slot["match"][name] += 1

    summary = {}
    for month, slot in sorted(per_month.items()):
        shares = {name: round(count / slot["rows"], 4) for name, count in slot["match"].most_common()}
        values = slot["values"]
        repeats = sum(1 for a, b in zip(values, values[1:]) if a == b)
        summary[month] = {
            "rows": slot["rows"],
            "best_candidates": dict(list(shares.items())[:5]),
            "E:consecutive_identical_values_share": round(repeats / max(1, len(values) - 1), 4),
            "E:median": statistics.median(values),
            "E:min": min(values), "E:max": max(values),
        }
    return {"tolerance_rel": TOL, "months": summary, "A:raw_files": raw_file_facts(data_dir),
            "candidates_per_row": len(_candidates(um, spot, times[0]))}



AGG_DAYS = ("2025-06-15", "2025-10-15", "2026-06-15")


def aggtrades_check(data_dir: pathlib.Path = DEFAULT_DATA_DIR, days: tuple[str, ...] = AGG_DAYS) -> dict:
    """F. Rebuild 5m taker buy/sell volume from raw aggTrades and compare it with BOTH the
    USD-M klines and the metrics ratio, to locate which side diverges in the anomalous regime."""
    import csv

    from bitmomo_lab.contract import AvailabilityBasis
    from bitmomo_lab.sources.binance_archive import ArchiveFile, acquire
    from bitmomo_lab.sources.registry import DatasetSpec

    spec = DatasetSpec("binance_um_aggtrades", "futures/um", "aggTrades", "trade", "none",
                       AvailabilityBasis.RECEIVED_AT, 0, 0, False, True, "diagnostic only; not a canonical dataset")
    um = _klines("manifests/binance_um_klines_5m__BTCUSDT__2020-01-01__2026-09-10.json", data_dir)
    _, metrics_frame = load_input(LAB_ROOT / "manifests/binance_um_metrics_5m__BTCUSDT__2020-09-01__2026-09-10.json",
                                  data_dir)
    metrics = metrics_frame.as_of(2**62)
    ratio_at = dict(zip((int(v.timestamp()) for v in metrics["event_time"].to_pylist()),
                        metrics["sum_taker_long_short_vol_ratio"].to_pylist()))
    report = {}
    for day in days:
        date = dt.date.fromisoformat(day)
        raw = acquire(spec, "BTCUSDT", ArchiveFile("daily", day, date, date), data_dir)
        with zipfile.ZipFile(raw.local_path) as archive:
            text = io.TextIOWrapper(archive.open(archive.namelist()[0]), encoding="utf-8")
            buckets: dict[int, list[float]] = collections.defaultdict(lambda: [0.0, 0.0])
            for row in csv.reader(text):
                if not row or not row[0][:1].isdigit():
                    continue
                ts = int(row[5])
                ts = ts // 1000 if ts > 10**14 else ts  # us -> ms if needed
                bucket = (ts // 1000) // FIVE * FIVE
                taker_buy = row[6].strip().lower() == "false"  # buyer is taker when buyer is not maker
                buckets[bucket][0 if taker_buy else 1] += float(row[2])
        kline_vol_err, kline_buy_err, metric_start, metric_end = [], [], 0, 0
        n = 0
        for bucket, (buy, sell) in buckets.items():
            k = um.get(bucket)
            if k is None or buy + sell == 0:
                continue
            n += 1
            kline_vol_err.append(abs(k[0] - (buy + sell)) / (buy + sell))
            kline_buy_err.append(abs(k[1] - buy) / max(buy, 1e-12))
            agg_ratio = buy / sell if sell > 0 else None
            for stamp, key in ((bucket, "start"), (bucket + FIVE, "end")):
                m = ratio_at.get(stamp)
                if m and agg_ratio and abs(m - agg_ratio) / m < TOL:
                    if key == "start":
                        metric_start += 1
                    else:
                        metric_end += 1
        report[day] = {
            "buckets": n,
            "klines_volume_vs_aggtrades_median_rel_err": statistics.median(kline_vol_err),
            "klines_taker_buy_vs_aggtrades_median_rel_err": statistics.median(kline_buy_err),
            "metrics_ratio_matches_aggtrades_bucket_starting_at_T": round(metric_start / n, 4),
            "metrics_ratio_matches_aggtrades_bucket_ending_at_T": round(metric_end / n, 4),
        }
    return report


def classify_days(data_dir: pathlib.Path = DEFAULT_DATA_DIR, threshold: float = 0.5) -> dict:
    """Per UTC day: which stamp convention the archive taker ratio follows.

    period_end   : >= threshold of the day's rows match the USD-M kline [T-5m, T)
    period_start : >= threshold match the kline [T, T+5m)
    UNVERIFIED   : neither (or no usable taker data that day)
    USD-M klines are the reference because they reproduce raw aggTrades exactly (check F).
    """
    _, metrics_frame = load_input(LAB_ROOT / "manifests/binance_um_metrics_5m__BTCUSDT__2020-09-01__2026-09-10.json",
                                  data_dir)
    metrics = metrics_frame.as_of(2**62)
    um = _klines("manifests/binance_um_klines_5m__BTCUSDT__2020-01-01__2026-09-10.json", data_dir)
    counts: dict[str, list[int]] = collections.defaultdict(lambda: [0, 0, 0])
    times = [int(v.timestamp()) for v in metrics["event_time"].to_pylist()]
    for t, value in zip(times, metrics["sum_taker_long_short_vol_ratio"].to_pylist()):
        day = dt.datetime.fromtimestamp(t, tz=dt.timezone.utc).strftime("%Y-%m-%d")
        counts[day][0] += 1
        if value is None:
            continue
        for slot, start in ((1, t - FIVE), (2, t)):
            k = um.get(start)
            if k and k[0] - k[1] > 0 and abs(k[1] / (k[0] - k[1]) - value) / value < TOL:
                counts[day][slot] += 1
    labels = {}
    for day, (rows, end, start) in sorted(counts.items()):
        labels[day] = ("period_end" if end / rows >= threshold else
                       "period_start" if start / rows >= threshold else "UNVERIFIED")
    return labels


if __name__ == "__main__":
    print(json.dumps({"investigate": investigate(), "aggtrades": aggtrades_check()}, indent=1, sort_keys=True,
                     default=str))
