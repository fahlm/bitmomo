# M1 — Opportunity V1: Findings

Status: RESEARCH INTERPRETATION — not a public accuracy claim, not a methodology change.
Facts: [M1_OPPORTUNITY_V1_RESULTS.md](M1_OPPORTUNITY_V1_RESULTS.md) (generated) and
`results/opportunity-v1-eval-v1.json`. Parity: [M1_OPPORTUNITY_V1_PARITY.md](M1_OPPORTUNITY_V1_PARITY.md).

## Question

Does Opportunity V1 genuinely separate future market movement out of sample, and under what
conditions?

## Setup (pre-registered)

* Hypotheses (`research/lab/registry/hypotheses.json`) and the evaluation plan
  (`research/lab/config/opportunity_v1_evaluation.json`) were committed in `9b3389f`, **before**
  any evaluation code ran on historical data.
* Engine `opportunity-v1-py` 1.0.0 (strict), 15-minute cutoffs 2020-01-01 → 2026-09-10 18:00:
  234,697 cutoffs, 233,349 evaluable, 1,348 warm-up (`insufficient_history`), 0 other failures.
* Outcomes: USD-M 5m high/low path after the cutoff, entry = close at the cutoff, event =
  absolute excursion ≥ 0.20 / 0.30 / 0.50% within +15m…+6h.
* Segments: development 2020–2022, validation 2023-01 → 2024-06, holdout 2024-07 → 2026-09,
  with a 6h purge at boundaries. Expanding walk-forward by calendar year from 2021.
* Baselines: base rate; raw `range_60m_pct` (no adaptive normalization); fixed quartile states
  fitted on development only (and refit per walk-forward fold); naive `|60m return|`.
* Tests run: 7 hypotheses; 18 descriptive cells × 17 slices + 6 walk-forward folds; 5 declared
  outcome-definition variants in the P0.2C diagnostic.

## Answers

### 1. Yes, it separates future movement, and the ordering is robust

`HIGH > NORMAL > LOW` event rates hold in **303 of 306** descriptive cells: every segment,
every year, every volatility bucket, every horizon and threshold. The 3 exceptions are all
2021 at +4h/+6h with 0.20–0.30% thresholds, where the base rate is ≥ 99% and no state can
separate anything.

Primary cell (+1h, ≥ 0.30%), holdout (2024-07 → 2026-09, 802 days):

| State | Share | Event rate |
|---|---:|---:|
| HIGH | 25.3% | 88.8% |
| NORMAL | 48.3% | 67.5% |
| LOW | 26.4% | 41.7% |

HIGH − LOW = **47.1 pp**, day-clustered bootstrap 95% CI [44.5, 49.4] pp (validation: 47.1
[44.0, 49.9]; development: 21.0 [18.8, 23.0]). Holdout AUC of `activity_percentile`: 0.736
(H-OPP-002 PASS, threshold 0.70).

### 2. It works best where absolute volatility is moderate or low

Separation grows as markets get quieter in absolute terms, because a fixed ±0.30% target
saturates in violent regimes:

| Year | Base rate (+1h, 0.30%) | HIGH − LOW (pp) |
|---|---:|---:|
| 2021 | 96.4% | 5.7 |
| 2020 | 78.1% | 26.5 |
| 2022 | 79.0% | 30.8 |
| 2024 | 74.8% | 41.8 |
| 2026 YTD | 63.6% | 46.8 |
| 2025 | 62.9% | 50.0 |
| 2023 | 56.0% | 49.6 |

The same effect shows by horizon: holdout HIGH − LOW is 46–53 pp at +15m…+1h and shrinks to
9 pp at +6h (base rate 97%). **Opportunity is most informative for short horizons (15m–2h) and
moderate thresholds.** At +4h/+6h almost everything moves 0.30%, so the state adds little
there. Higher thresholds (0.50%) restore separation at longer horizons (holdout +6h: 24 pp).

Within the holdout, the ordering holds in low, normal and high volatility-context buckets
(H-OPP-005 PASS), with the largest spread in the low-volatility bucket (50.9 pp).

### 3. The adaptive percentile trades absolute-move ranking for stability, and that is the right trade for its purpose

* **H-OPP-003a FAIL.** For *absolute* ±0.30% moves, the raw trailing 60m range ranks better
  than the 14-day adaptive percentile at every horizon and in every segment (holdout +1h AUC
  0.792 vs 0.736; full sample 0.829 vs 0.702). Absolute events depend on the absolute
  volatility level, which the adaptive normalization removes by design.
* **H-OPP-003b PASS, decisively.** Fixed quartile thresholds fitted on 2020–2022 collapse
  out of sample: holdout state shares HIGH 8.9% / LOW 50.9%. In walk-forward the fixed HIGH
  share swings from 7.7% (2023) to 48.6% (2021). Opportunity's shares stay at 24.2–25.8% HIGH and
  25.7–28.0% LOW in every year, as designed.

Implication: Opportunity V1 answers "more active than recently?" (relative, stable), not
"will BTC move X% in absolute terms?". For an absolute-move question a raw-range or
volatility-level feature is the better score, but it needs regime-aware thresholds to stay
usable. This supports the existing product semantics in `OPPORTUNITY_V1.md` ("relative
activity ranking, not a fixed probability"). It is also a concrete, pre-registered data
point for any future `opportunity-v2` discussion. Nothing was changed here.

### 4. It beats the naive recent-move baseline out of sample, but not everywhere

H-OPP-004 PASS in the holdout (+1h AUC 0.736 vs |60m return| 0.679) and in validation. In
the **development years (2020–2022) the naive |60m return| scored higher** (0.728 vs 0.682),
as it did on the full sample at short horizons. The advantage of Opportunity over the naive
baseline is therefore regime-dependent, not universal.

### 5. Reproduction of P0.2C: explained divergence

H-OPP-006 is **FAIL** as registered: 24/33 published cells are within ±1 pp, max 1.63 pp, and
every difference is positive. A declared diagnostic of 5 outcome definitions
(`results/p02c-divergence-diagnostic.json`) explains it completely. **P0.2C measured the
outcome on the Binance Spot high/low path** while states came from USD-M. With Spot-path
outcomes, all 33 cells reproduce within **0.28 pp** (mean 0.08 pp). Closes-only definitions
miss by 3–19 pp; `>` vs `≥` has no effect. The lab keeps USD-M outcomes as its canonical
definition (same source as the engine) and records this as a documented methodology
difference, not an error in either.

## Where it does not work / limitations

* **Saturated regimes and long horizons** (2021; +4h/+6h at ≤ 0.30%): the state carries
  little information because nearly everything moves.
* **Absolute-move targets**: a raw range scores better (see 3). Opportunity should not be
  presented as a calibrated probability of a given % move. Event rates per state change
  materially by year (HIGH at +1h/0.30%: 81–99%).
* **Dependence**: 15-minute cutoffs with up to 6h horizons overlap heavily. Row counts are not
  independent samples. Uncertainty is shown with day-clustered bootstrap only for the
  primary cell.
* **Direction**: nothing here says anything about direction. HIGH ≠ bullish.
* **Outcome source**: USD-M perpetual prices. Spot-based outcomes give rates ~0.3–1.6 pp lower.
* **Volatility context** is a lab feature (USD-M daily, 20d RV vs prior 180d) with 200 days of
  warm-up; 17,948 early evaluable rows have no bucket.
* **Parity**: PHP-executed golden vectors are still outstanding (no local PHP); see the parity doc.

## Suggested next research (not started)

1. Run the same framework with a **volatility-scaled** event target (e.g. excursion ≥ k × recent
   ATR). That is the target a *relative* activity state should be judged on; it is a new
   hypothesis and needs a new registry entry.
2. Forward validation: store live `opportunity-v1` records (already append-only in WordPress)
   and settle them with this lab's settlement code, once production data can be exported
   read-only.
