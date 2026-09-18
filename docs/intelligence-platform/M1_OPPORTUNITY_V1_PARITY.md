# M1 — Opportunity V1 Parity Port (`opportunity-v1-py`)

Status: M1 — research only. Production PHP is unchanged and was only read.
Code: `research/lab/bitmomo_lab/engines/opportunity_v1.py`
Tests: `research/lab/tests/test_opportunity_parity.py` (24 tests)

## Source of truth

| Item | Reference |
|---|---|
| Production calculator | `website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-opportunity.php`, git blob `d6e53044b5d989e04dcf7506e59c4480ca4c89a7` (last changed in `e8a6e60`) |
| Production tests | `website/wp-content/plugins/bitmomo-ai/tests/test-bitmomo-ai-opportunity-v1.php`, git blob `e0dc92fd33cf230c16918b480f03c1dde4f102c9` |
| Methodology | `docs/intelligence/OPPORTUNITY_V1.md` (`opportunity-v1`, frozen) |

## What is reproduced exactly

| Behavior | PHP | Python port |
|---|---|---|
| Input rows | Binance kline arrays; skip if `< 7` fields, `open ≤ 0`, `close ≤ 0`, `close_ms ≥ (cutoff+1)·1000`, `high ≤ 0` or `low ≤ 0` | identical |
| Duplicates | `$normalized[$open_ms] = $row` (later row wins) | identical |
| History requirement | `REQUIRED_CANDLES = 4044` = 12 + 1344 × 3 | identical |
| Fail-closed codes | `insufficient_history` (count < 4044), `source_gap` (any window row ≠ expected open), `cutoff_mismatch`, `reference_failed` | identical codes, same check order |
| Range | `((max(0.0, highs) − min(lows)) / min(lows)) · 100` over 12 candles | identical float operations |
| Reference | ranges ending 3·k candles earlier, k = 1..1344 | identical |
| Percentile | `100 · #{ref ≤ current} / 1344` (ties count as below) | identical |
| States | `≥ 75` HIGH, `≤ 25` LOW, else NORMAL | identical |
| Rounding | `round(range, 8)`, `round(percentile, 4)` (half away from zero) | `php_round`: half-up on the shortest decimal repr (`round(1.005, 2) = 1.01`, `round(0.285, 2) = 0.29`, `round(-2.5) = -3`) |
| Timestamps | `gmdate('c', …)`, `source_last_close_time = floor(close_ms/1000)` | identical strings |
| Scheduler | cutoff = floor to 15m, previous one if < 30 s past; next run = boundary + 60 s | `cutoff_for`, `next_run_timestamp` |
| Live fetch semantics | latest 4044 closed candles **by count** (paged, `endTime = cutoff − 1 ms`) | PIT equivalent: rows visible at T, last 4044 by event time |

### Tolerances

States and error codes: **exact**. `activity_percentile`: exact (100·k/1344 never lands on a
4-decimal half tie; `k ≡ 0 mod 21` gives terminating decimals of ≤ 4 digits). `range_60m_pct`:
exact up to PHP rounding. The only possible difference is a last-digit half tie at 8 decimals
between PHP versions that pre-round (≤ 8.3) and those that don't (≥ 8.4). **Declared
tolerance: 1e-8 absolute.** None was observed in the tests.

### Golden vectors

* The PHP test file has 12 checks. All 10 calculator/scheduler checks are ported with the
  same synthetic generator: valid record, HIGH, 1344-observation reference, frozen
  methodology/source, LOW, missing candle → `insufficient_history`, misaligned candle →
  `source_gap`, scheduler ×2, and state boundaries. The other 2 are WordPress-store freshness
  checks (`public_latest`); they are store behavior, not calculator behavior, and out of
  scope for the lab.
* Exact values of those vectors are hand-derived and asserted (e.g. HIGH: `range_60m_pct =
  2.02020202`, percentile `100.0`, `source_last_close_time = 05:59:59+00:00`).
* Additional boundary vectors: open candle at the cutoff ignored, older extra rows ignored,
  later duplicate wins, non-positive low dropped → fail closed, close time inside the
  cutoff second → `cutoff_mismatch`, rounding cases.

**Not yet done:** vectors emitted by running the PHP class itself (`php-harness/`). PHP is not
installed locally by decision. The harness is the one parity item still open. It needs a
PHP CLI (or a CI job) to execute the class read-only and diff against Python on randomized
inputs.

## Two execution paths, one answer

* `evaluate_rows` is the line-by-line port. The engine's `compute(snapshot)` uses it through a
  PIT `Snapshot`.
* `BatchEvaluator` is an incremental version for 234k-cutoff replays (sliding max/min for the
  ranges, one sorted window per residue class mod 3 for the ranks). It only runs if the input
  satisfies the close-boundary invariant (all rows `ok`, `available_at = open + 5m`, one
  version per key). Under that invariant, indexing by position reads exactly what
  `as_of(T)` would serve; otherwise it raises `BatchNotApplicable` and replay falls back to
  the per-cutoff path.
* Equality is tested on synthetic data with gaps, ties and warm-up. Every real replay also
  re-computes 64 random cutoffs through the per-cutoff PIT path and aborts on any mismatch
  (0 mismatches in all runs).

## Strict vs production-parity

Opportunity V1 has **no neutral fill** in production (every missing input fails closed), so
both modes produce identical outputs and zero imputation flags. Verified on the full replay:
the parity run `3d3ae43d4081a373` has output hashes identical to the strict run
`2cb83ed716ea8724`. It is stored only under `.data/parity/`, stamped `production_parity`, and
refused by the canonical reader and writer.

## Behaviors reproduced but questionable (documented, NOT changed)

1. **Flat market → HIGH.** Because ties count as "below", a window where every reference
   range equals the current range scores percentile 100 → HIGH
   (`test_ties_count_as_below_so_a_flat_market_is_high`). Real BTC data essentially never
   produces exact ties at this scale, but a halted or stale feed repeating identical candles
   would read as maximal activity. Suggest a fail-closed rule for zero-range reference
   windows in a future `opportunity-v2`.
2. **Count-based fetch shifts the error code.** Live PHP fetches the last 4044 candles by
   count, so a gap reports `source_gap` (window extends earlier) rather than
   `insufficient_history`. The port reproduces this. It only matters for diagnostics.
3. **No freshness bound inside the calculator.** `evaluate_rows` accepts any cutoff. Staleness
   is enforced separately by the store (`MAX_LATEST_AGE_SECONDS = 30 min`). Replay is
   unaffected.
4. **Malformed-but-positive rows.** A misaligned candle with a close time inside the cutoff
   second is kept by PHP and causes `source_gap`. The lab normalizer flags such rows as
   `boundary_mismatch` and `as_of` excludes them, so on corrupted input the lab and live PHP
   can differ. This cannot occur on the canonical USD-M dataset (0 such rows); it can on Spot
   (8 truncated maintenance candles). Spot is not an `opportunity-v1` source.
5. **Rounding is version-dependent in PHP itself** (see Tolerances).

## Warm-up count

The replay starting 2020-01-01 00:00 reports **1,348** warm-up cutoffs as
`insufficient_history`. P0.2C reported 1,347. The first valid cutoff is 2020-01-15 01:00
(4044 × 5m after the first candle, = cutoff #1348). The one-row difference is consistent with
P0.2C's first cutoff being 00:15 rather than 00:00.
