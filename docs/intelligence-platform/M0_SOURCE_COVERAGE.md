# M0 — Source Coverage Findings

Status: M0 EVIDENCE — research only
Date: 2026-09-18
Branch: `research/intelligence-platform-v2`
Supersedes, in part: [`docs/research/btc-mode/SOURCE_COVERAGE_AUDIT.md`](../research/btc-mode/SOURCE_COVERAGE_AUDIT.md)
(2026-09-11, "INITIAL AUDIT"). That document is kept unchanged as historical evidence.
§6 lists exactly which of its conclusions are superseded.

Every number here was produced by code in `research/lab/` and can be reproduced:

* archive listings: `bitmomo-lab coverage --prefix data/<path>/`, which reads the public S3
  bucket listing of `data.binance.vision`;
* dataset completeness: `bitmomo-lab build …` plus the committed manifests in
  `research/lab/manifests/`;
* field semantics: `bitmomo-lab equivalence …` (§4).

No REST endpoint could be queried from the authoring machine. `fapi.binance.com` and
`api.binance.com` resolve to `103.123.248.32` there, which looks like a DNS/ISP block. REST
retention statements therefore still come from Binance's documentation and are marked
**documented, not re-verified**.

## 1. Summary table

Window evaluated: 2020-01-01 → 2026-09-10 UTC (P0.2C window), symbol BTCUSDT.

| Series | Historical archive | Earliest verified | Latest verified (listing 2026-09-18) | Interval | Completeness in window | Timestamp semantics | `available_at` rule | REST retention | Multi-year replay | Forward collection |
|---|---|---|---|---|---|---|---|---|---|---|
| Spot klines | yes, monthly + daily | 2017-08 (monthly), 2017-08-17 (daily) | 2026-08 / 2026-09-17 | 5m | 703,698 rows; 470 missing grid intervals in 16 gaps (462 absent rows + 8 truncated maintenance candles) | open_time = period start. **ms until 2024-12, µs from 2025-01** | close boundary (open + 5m) | long | **yes** (sensitivity source) | not needed |
| USD-M klines | yes, monthly + daily | 2020-01 / 2019-12-31 | 2026-08 / 2026-09-16 | 5m | **704,160 rows, 0 missing** | open_time = period start, ms | close boundary | long | **yes** (canonical `opportunity-v1` source) | not needed |
| USD-M mark-price klines | yes, monthly + daily | 2020-01 / 2019-12-23 | 2026-08 / 2026-09-16 | 5m | 701,554 rows; 2,606 missing in 9 gaps (whole days: 2021-07-01, 2021-07-24..27, 2022-07-31, 2022-10-02, 2023-02-24, 2026-06-29) | period start | close boundary | long | yes, with gaps reported | not needed |
| USD-M index-price klines | yes, monthly + daily | 2020-01 | 2026-08 | 5m | 700,691 rows; 3,469 missing in 11 gaps (incl. 2022-07-24..31 fragments, 2023-04-07..08, 2026-06-29) | period start | close boundary | long | yes, with gaps reported | not needed |
| USD-M premium-index klines | yes, monthly + daily | 2020-01 / 2019-12-24 | 2026-08 / 2026-09-16 | 5m | 701,822 rows; 2,338 missing in 11 gaps | period start | close boundary | long | yes, with gaps reported | not needed |
| Funding rate | yes, **monthly only** | 2020-01 | 2026-08 | 8h events | 7,305 events, all 8h spacing, no long gaps; **2026-09 not yet archived** | `calc_time` = settlement (+1 ms jitter on some rows) | calc_time + 5m declared lag | documented: paginated, no 30d limit | **yes** | recorded forward (REST) to cover the current month |
| `metrics` (OI, top-trader L/S, global account L/S, taker vol ratio) | yes, **daily only** | **2020-09-01** | 2026-09-16 | 5m | 708,509 raw rows → **75,255 exact duplicates collapsed** (0 conflicting) → 633,254 rows; 634 missing grid intervals in 156 gaps (largest 2024-02-16 13:35 → 2024-02-17, 125 intervals); fields partly null: taker ratio null for **2022-01 → 2022-04** (37,272 rows), top-trader ratios null for most of 2022 (~92k rows), OI `0` sentinels nulled (473 / 485 rows) | `create_time` semantics **change over time** (§4.1) | create_time + 5m + 5m declared lag | n/a | **yes from 2020-09-01**, see §4 on field equivalence | yes (REST) for the 1h production semantics |
| OI history (REST `openInterestHist`) | no, except via `metrics` | — | — | 5m…1d | — | unverified | received_at (recorder) | documented: ~1 month | via `metrics` only | **yes — recorder** |
| Global account L/S (REST) | no, except via `metrics` `count_long_short_ratio` (§4) | — | — | 5m…1d | — | unverified | received_at | documented: ~30 days | via `metrics`, pending equivalence | **yes — recorder** |
| Taker buy/sell (REST `takerlongshortRatio`) | no, but **reconstructible from USD-M klines** (§4) | 2020-01 (klines) | — | 5m…1d | as USD-M klines | — | close boundary | documented: ~30 days | **yes (reconstructed)** | yes — recorder |
| Basis (REST `/futures/data/basis`) | no | — | — | 5m…1d | — | — | received_at | documented: ~30 days | reconstructible from mark − index klines (not identical to production, §5) | **yes — recorder** |
| Premium index snapshot (REST `premiumIndex`) | no (instantaneous) | — | — | snapshot | — | `time` | received_at | none (live only) | approximated by mark/index kline closes | **yes — recorder** |
| Liquidation snapshots | **listing empty** for BTCUSDT daily | — | — | — | — | — | — | — | no | out of scope |
| Book depth | yes, daily | 2023-01-01 | 2026-09-16 | — | 3 missing days (2023-02-08, 2023-02-09, 2024-04-18) | not studied | — | — | not in Wave 1 | not in Wave 1 |

"Missing" always means *no `ok` row on the grid*. Nothing was filled. Every gap is listed
with exact start/end in the corresponding manifest (`quality.gaps`).

## 2. Verification against previously published P0.2C numbers

| Published (P0.2C doc) | Independently derived | Verdict |
|---|---|---|
| USD-M 5m rows: 704,160, "complete" | 704,160 rows, 704,160 expected, 0 missing, 0 duplicates, 0 boundary issues | **match** |
| Spot 5m rows: 703,698 | 703,698 rows | **match** |
| Spot "462 missing 5-minute intervals" | 462 grid slots with **no row**; plus **8 rows that exist but are truncated candles** (close_time ≠ open + 5m − 1 ms) → 470 slots without an `ok` row | **explained divergence** (below) |

The 8 truncated Spot candles are all adjacent to exchange maintenance halts. Their
`close_time` is the halt moment:

| open_time (UTC) | source close_time | note |
|---|---|---|
| 2020-02-19 11:35 | 11:35:32.286 | starts the 71-interval gap |
| 2020-03-04 09:20 | 09:21:46.694 | starts a 26-interval gap |
| 2020-12-21 14:05 | **13:47:20.521** (before open), volume 0 | corrupted row |
| 2021-02-11 03:40 | 03:40:54.773, volume 0 | |
| 2021-04-25 04:00 | 04:00:58.146 | starts the 57-interval gap |
| 2021-08-13 01:55 | 01:59:59.000 (whole second, not …999 ms) | |
| 2021-12-24 04:55 | 04:59:54.362 | |
| 2023-03-24 12:35 | 12:39:41.646, volume 0 | |

They are stored with `quality = boundary_mismatch`, excluded from `as_of` by default, and
never dropped. P0.2C evidently counted only absent rows. Both counts are now reported
explicitly. This does not affect `opportunity-v1`, whose canonical source is USD-M (no such
rows).

## 3. Timestamp-unit transition (Spot)

Spot kline files use milliseconds up to and including `2024-12` and **microseconds** from
`2025-01` (e.g. first row of 2025-01: `1735689600000000`). USD-M files remain in
milliseconds. Handling (`research/lab/bitmomo_lab/normalize/archive.py`):

* the unit is detected per file from the value magnitude (13 digits = ms, 16 digits = µs; anything else is rejected);
* units must be uniform within a file, and `open_time`/`close_time` must agree; otherwise the file fails closed;
* all times are stored as `timestamp[us, UTC]`, and the source unit is kept in `source_time_unit`;
* tests pin this on real bytes from both sides of the switch
  (`tests/test_normalize.py::test_real_spot_rows_across_ms_to_us_switch_are_contiguous`).

The full-window build confirms continuity across 2024-12-31 23:55 → 2025-01-01 00:00
(no gap, no duplicate at the boundary).

## 4. `metrics` archive semantics and equivalence with production fields

### 4.1 `create_time` semantics change over time (look-ahead hazard)

Method (`bitmomo-lab equivalence`, full 2020-09-01 → 2026-09-10 history, 595,944 comparable
rows): compare the metrics `sum_taker_long_short_vol_ratio` stamped T with the USD-M kline
taker ratio `taker_buy / (volume − taker_buy)` for the kline opening at T−5m, T and T+5m.
The share of rows matching within 1e-3 relative error, per year:

| Year | kline [T−5m, T) — stamp = period END | kline [T, T+5m) — stamp = period START | kline [T+5m, T+10m) |
|---|---:|---:|---:|
| 2020 | **99.98%** | 0.12% | 0.09% |
| 2021 | **99.08%** | 0.18% | 0.16% |
| 2022 | **79.55%** | 0.13% | 0.13% |
| 2023 | **83.00%** | 0.12% | 0.12% |
| 2024 | 13.27% | **60.97%** | 0.12% |
| 2025 | 0.11% | 46.51% | 0.10% |
| 2026 | 0.11% | 49.94% | 0.10% |

The monthly breakdown (in the report JSON) localizes three regimes:

| Period | Meaning of `create_time = T` |
|---|---|
| 2020-09 → 2024-02 | **end** of the 5m period it describes |
| 2024-03 → 2025-07 and 2026-04 → 2026-09 | **start** of the 5m period (60–82% match per month) |
| 2025-08 → 2026-03 | **unexplained**: no alignment matches more than 20% |

(2022-02 → 2022-04 has no comparable rows because the archive taker ratio is null there.)

Consequences:

1. In the post-2024-03 regime, a metrics row stamped T contains information that only exists
   at T + 5m. A replay that treats `create_time` as knowledge time leaks five minutes of the
   future. This is very likely the mechanism behind the "same-timestamp 5-minute derivatives
   edge" that [INTRADAY_FINDINGS_02](../research/btc-mode/INTRADAY_FINDINGS_02.md) found and
   rejected (its August-2026 window is in a "start" regime).
2. The lab's rule `available_at = create_time + 5m + 5m declared lag` is at or after the true
   period end under **both** explained conventions. It is PIT-safe everywhere, and 5 minutes
   more conservative than necessary before 2024-03.
3. `event_time` is stored as the stamp (`create_time`), not as a derived period start, because
   the convention is not constant. Any feature that needs the period must account for the
   regime explicitly.
4. The unexplained 2025-08 → 2026-03 window must not be used for taker-ratio claims until it is
   understood. The M0 single-day check (2026-08-15) that first suggested "start" semantics
   was correct for its date but did not generalize. It was superseded by this full-history run.

Implied price `sum_open_interest_value / sum_open_interest` vs USD-M klines (632,769 rows):
median relative error 1.30e-4 against the price at the stamp instant (kline open at T, or the
previous kline's close), vs 2.2e-4 against the close 5m later. The OI snapshot is taken at
(or very near) the stamped instant.

### 4.2 Field-by-field equivalence with what production consumes

Production (`class-bitmomo-ai-binance.php`, read-only reference) consumes:

| Production input | Endpoint / params | Field used | Closest archive field | Equivalent? |
|---|---|---|---|---|
| `crowding.taker_buy_sell_ratio` | `takerlongshortRatio`, period **1h**, last row | `buySellRatio` | `metrics.sum_taker_long_short_vol_ratio` (**5m**) | **No, not directly.** A 1h ratio is Σbuy/Σsell over 12 periods, and a ratio of ratios cannot be re-aggregated. Instead, USD-M klines reproduce the 5m ratio exactly (§4.1), so the 1h `buySellRatio` can be **reconstructed from klines** as Σtaker_buy / Σ(volume − taker_buy). Exact-match check against live REST is pending (recorder stores `buyVol`/`sellVol` at 5m and 1h). |
| `crowding.oi_change_24h_pct` | `openInterestHist`, period **1h**, limit **30** | `sumOpenInterestValue` first→last | `metrics.sum_open_interest_value` (5m) | **Plausible, unverified.** The name matches, but REST 1h snapshot time vs archive 5m `create_time` is unverified. The implied price `value/OI` places the archive snapshot at the stamped instant (§4.1). Note: production's "24h" change actually spans the first and last of **30 hourly rows ≈ 29 h** (a production-semantics fact to carry into Wave 1.5 parity, not to fix here). |
| `crowding.global_long_short_ratio` | `globalLongShortAccountRatio`, period **1h**, last row | `longShortRatio` | `metrics.count_long_short_ratio` (5m) | **Unverified.** "count" suggests an account-count ratio (= global *account* ratio), but whether the 1h REST value equals the 5m archive value at the hour cannot be tested offline. The recorder stores both periods so the comparison can be run once live data exists. **Not to be treated as equivalent until then.** |
| — | — | — | `metrics.count_toptrader_long_short_ratio`, `sum_toptrader_long_short_ratio` | Not consumed by production. Likely top-trader *account* and *position* ratios respectively; the recorder records both REST counterparts to check this. |
| `carry.funding_rate` | `fundingRate`, limit 21, last row | `fundingRate` | `fundingRate` archive `last_funding_rate` | **Equivalent in meaning** (settled rate at settlement time). The archive is monthly only, so the current month needs REST/recorder. |
| `carry.basis_pct` | `premiumIndex` (instantaneous) | (markPrice − indexPrice)/indexPrice × 100 | mark-price & index-price 5m klines | **Approximation only** (see §5). |

## 5. Basis reconstruction

Production computes basis from an instantaneous `premiumIndex` snapshot. The archive has 5m
mark-price and index-price klines. Their closes give `(mark_close − index_close)/index_close`
at each 5m boundary. This is the same approximation P0.2B used ("hourly mark/index closes"),
now available at 5m. It is not the production value at an arbitrary instant; the difference
is bounded by intra-5m movement. The gaps in §1 (whole missing days) mean basis is
unavailable, never zero, on those days. `premiumIndexKlines` is a different quantity (the
funding premium index) and must not be substituted for mark − index basis.

## 6. What this supersedes in `SOURCE_COVERAGE_AUDIT.md`

| Old conclusion (2026-09-11) | New evidence | Status |
|---|---|---|
| OI / long-short / taker history beyond ~30 days needs "an already-owned archive, a vendor, an official downloadable archive if verified, or exclusion" (Tier B) | The official `metrics` archive exists with **complete daily files 2020-09-01 → 2026-09-16** (0 missing days). Taker ratio is additionally reconstructible from USD-M klines back to 2020-01. | **Superseded:** Tier B option 3 is verified to exist. Field equivalence is only partially verified (§4.2). |
| Historical basis only via REST (~30 days) | 5m mark/index klines exist from 2020-01 with reported gaps | **Superseded** (as an approximation, §5) |
| Funding: "verify earliest retrievable timestamp" | Archive funding from 2020-01-01 00:00 (monthly files), 8h spacing throughout | **Answered** |
| "Confirm historical kline acquisition path and futures-vs-spot choice" | Both archives verified; USD-M complete; Spot has 462 absent + 8 truncated | **Answered** (consistent with the OPPORTUNITY_V1 decision) |
| REST 30-day retention statements | Not re-verified (REST blocked on authoring machine) | **Still documented-only** |
| Liquidation pressure unavailable | `liquidationSnapshot` listing for BTCUSDT is empty | **Confirmed** |

## 7. Open items

1. ~~Explain the 2025-08 → 2026-03 metrics taker divergence.~~ Investigated in
   [M1_METRICS_ANOMALY_INVESTIGATION.md](M1_METRICS_ANOMALY_INVESTIGATION.md): the divergence is
   inside the archive metrics field (klines equal raw aggTrades); 2025-08-06 → 2026-04-06
   stays `UNVERIFIED` and is guarded in code.
2. Run the recorder from a host with legitimate API access. Capture real fixtures, measure
   publication latency (`received_at − period end`), and replace the declared 5m lags with
   measured, documented values (new dataset/schema version, never a silent change).
3. Settle the `count_long_short_ratio` ↔ `globalLongShortAccountRatio` (1h) equivalence and
   REST `timestamp` semantics from recorded overlap.
4. Decide in M1 whether the reconstructed 1h taker ratio or archive metrics should feed
   Wave 1.5 Signal Engine parity.
