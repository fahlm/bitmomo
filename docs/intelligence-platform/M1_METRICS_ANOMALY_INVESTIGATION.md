# Metrics archive anomaly 2025-08 → 2026-03 — Bounded Investigation

Status: CLOSED FOR WAVE 1 — cause outside the archive's observable data; era remains
`timestamp_semantics = UNVERIFIED`.
Code: `research/lab/bitmomo_lab/validate/metrics_anomaly.py`
Evidence: `results/metrics-anomaly-investigation.json`,
`research/lab/registry/references/metrics_taker_semantics_by_day.json`
Guard: `research/lab/bitmomo_lab/features/metrics_semantics.py`

## Question

In M0 the archive `metrics.sum_taker_long_short_vol_ratio` stamped T stopped matching any
USD-M kline interval between roughly 2025-08 and 2026-03. Is this caused by a schema change,
a timestamp convention change, an aggregation-window change, a symbol/file-format change,
corrupted rows, or something else identifiable?

## Method (declared list; nothing forced into a convention)

| Check | What was tested | Result |
|---|---|---|
| A. File/schema/format | header text, column count, rows per file, non-zero seconds in `create_time`, decimal places of the taker field; 8 months across all three regimes | **No change.** Identical 8-column header, 288 rows/day (one 285-row day in 2025-08), `:00` seconds, 8 decimals in every month |
| B. Timestamp convention | USD-M kline `[T + k·5m, T + (k+1)·5m)` for k = −3…+3 | Good regimes (2025-06, 2025-07, 2026-06): 73–75% match at k = 0; transition month 2026-04: 59%. Anomalous months (2025-08 partial, 2025-10, 2026-01, 2026-03): best is still k = 0, but only **6–20%** |
| C. Aggregation window | taker ratio over 10 / 15 / 30 / 60 min ending at T, ending at T+5m, or starting at T | ≤ 0.4% match in every month: **not a window change** |
| D. Definition change | inverted ratio (sell/buy), buy/total, Spot klines instead of USD-M | ≤ 0.4% match: **not a simple redefinition** |
| E. Corruption | consecutive identical values, medians, ranges | No repeats (0.00–0.01%); medians 0.97–1.02 as in good months: **no sign of corruption** |
| F. Which side moved? | rebuilt 5m taker buy/sell volume from raw **aggTrades** (checksum-verified) for 2025-06-15, 2025-10-15, 2026-06-15; compared with USD-M klines and with metrics | USD-M klines equal aggTrades to ~1e-15 relative error on **all three days**. Metrics matches aggTrades on 74.7% / **15.6%** / 64.9% of buckets |

## Day-level boundaries

`classify_days` labels each UTC day `period_end`, `period_start` or `UNVERIFIED`, using a
≥ 50% match at 1e-3 relative tolerance against USD-M klines (which equal aggTrades):

| Period | Label |
|---|---|
| 2020-09-01 → 2024-03-03 | `period_end` (isolated UNVERIFIED days, plus 2021-12-31 → 2022-05-26 where the taker ratio is largely null) |
| 2024-03-04 → 2025-08-05 | `period_start` (a few isolated UNVERIFIED days in 2024-03, 2024-08, 2024-11/12) |
| **2025-08-06 → 2026-04-06** | **UNVERIFIED (contiguous)** |
| 2026-04-07 → 2026-09-10 | `period_start` |

Totals: 1,129 `period_end`, 666 `period_start`, 406 `UNVERIFIED` days.

## Conclusion

* The anomaly is **inside the archive's metrics taker ratio**. The kline/trade data are
  consistent with raw trades throughout.
* It is **not** explained by schema, file format, timestamp shift, window length, ratio
  inversion, Spot substitution or corrupted values. It is a clean, contiguous block with sharp
  start and end days, which points to a change in how Binance computed or sampled that field.
  Public archive data cannot identify the mechanism.
* Even in good regimes only ~65–75% of rows match within 1e-3. The metrics ratio is an
  approximation of the trade-derived value, never an exact copy.

## Decision applied

* `timestamp_semantics = UNVERIFIED` for 2025-08-06 → 2026-04-06, plus the isolated days,
  permanently unless Binance documentation or recorded live data explains it.
* The PIT availability rule (`create_time + 10m`) is unchanged and still prevents look-ahead
  in every era.
* Any feature that needs the exact metrics period must read rows through
  `with_semantics(..., require_verified=True)`. UNVERIFIED rows are excluded and counted, never
  coerced into a convention (tested in `tests/test_metrics_semantics.py`).
* For taker flow specifically, **USD-M klines (equal to aggTrades) are the recommended
  research source**. The archive metrics taker ratio should not be used where klines can
  answer the same question.
* Scope of the label: evidence comes from the taker field only. OI and long/short fields
  share the file and stamp, but their period semantics cannot be checked against trades.
  They inherit the same day labels as a conservative default.
