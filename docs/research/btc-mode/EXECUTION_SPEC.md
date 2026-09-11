# BTC Mode Research — Execution Spec

Status: READY FOR INTRADAY RE-EXECUTION

This is the handoff specification for the research environment. It must not modify WordPress, staging, production, or canonical live engine rules.

## Goal

Build a strict point-in-time 30-day **intraday** research dataset and evaluate whether BTC Core, Market Regime, derivatives context, and Bond Intelligence should influence `btc-mode-v1` for active short-horizon decision support.

Do not invent production weights. Do not change live thresholds. Do not optimize against the final chronological holdout.

## Canonical implementation requirement

Replay the actual repository semantics. Do not replace production indicators with similar proxies.

At minimum verify before accepting results:

- Direction scoring uses the production 4H ADX/+DI/-DI axis exactly as implemented.
- 1H/4H/1D directional alignment affects the same production component(s) it affects live; do not average them into a substitute Direction score.
- Structure, Carry, Crowding, Volatility, aggregate score, strength, and confidence reproduce the current Signal Engine.
- Market Regime metrics and classifier thresholds reproduce the current Regime implementation, including canonical momentum input and hysteresis behavior.
- Any approximation is explicitly labeled and excluded from claims of exact production replay.

## Inputs

### BTC Core
Reproduce the current canonical Bitmomo Signal Engine semantics from the repository, including:

- Direction: ADX, +DI/-DI, 1H/4H/1D bias, direction score/status
- Structure: 4H and 1D state, structure score/status
- Carry: funding, basis, carry status/score
- Crowding: 24H OI change, BTC 24H price change, global long/short, taker buy/sell, crowding status/score
- Volatility: ATR fields, ATR percentile, Bollinger width, volatility regime
- Canonical outputs: directional score, directional bias, direction strength, confidence, data quality

### Market Regime
Reproduce the current canonical Regime classifier rules from the repository:

- accumulation
- expansion
- distribution
- capitulation
- transition
- candidate/official regime distinction where hysteresis applies
- regime confidence/certainty
- scores/evidence/conflicts where feasible

Do not infer Regime from Directional Bias.

### Bond Intelligence
Use the existing Bond Intelligence service/store point-in-time outputs only where defensible:

- policy repricing
- real-rate pressure
- inflation repricing
- curve state
- bond volatility if source quality is acceptable
- underlying 2Y / 10Y / real 10Y / breakeven / 2s10s context
- source maturity/provenance/freshness
- knowledge-time status
- schema/rule version

BTC price/outcome inside Bond Intelligence is evaluation shadow only and must never feed Bond State or BTC Mode features.

## Window

Use the latest contiguous 30-day period for which the maximum synchronized feature coverage can be defended.

Report exact start/end timestamps and coverage percentage for each field family before analysis.

## Observation set A — hourly intraday replay

Generate one feature snapshot each hour using only information available at that timestamp.

Expected maximum: ~720 rows over 30 days before exclusions.

This is the **primary research sample** because current V1 production inputs are predominantly based on closed 1H/4H/D1 candles and hourly derivatives observations.

Do not create artificial 15-minute feature updates from hourly inputs.

## Observation set B — production-anchor subset

From the same PIT-safe timeline, evaluate observations around the canonical Bitmomo session anchors:

- 08:10 `America/New_York`
- 20:10 `America/New_York`

for every calendar day including weekends and US holidays.

This subset measures product-anchor behavior; it is not the primary statistical sample.

## Observation set C — state-change/event subset

Where possible, identify observations at or immediately after material changes in canonical inputs, Regime, BTC Core family, or candidate BTC Mode state.

Use this to compare the information value of a genuine state change with repeated unchanged snapshots.

## Point-in-time requirements

For decision timestamp T:

- use only closed BTC candles available by T
- derivatives observations must have defensible event/knowledge time <= T
- no future swing confirmation or future candle use
- rolling features end at T
- no future macro observation backfill
- Treasury daily values may enter only after defensible historical availability
- unknown knowledge time stays unknown/unavailable for intraday use
- outcomes are computed only after feature rows are frozen

Create an explicit leakage-audit report before accepting the dataset.

## Outcomes

For every valid observation compute from a consistent high-resolution BTC reference source.

Primary horizons:

- +15m
- +30m
- +1h
- +2h
- +4h
- +6h

Secondary context:

- +12h
- +24h

For each horizon calculate where defensible:

- forward return
- MAE
- MFE
- time to MAE
- time to MFE
- realized volatility
- first meaningful excursion direction/path ordering where useful

Do not use +72h as a primary BTC Mode optimization target.

## Required analyses

### EXP-001 — Canonical Direction baseline
Group by the exact production direction strength/state and report all primary intraday path outcomes.

### EXP-002 — Canonical BTC Core
Evaluate whether the exact combined BTC Core improves intraday return and path-risk discrimination versus Direction alone.

### EXP-003 — Market Regime increment
Condition BTC Core results on exact canonical Regime. Inspect whether Regime changes MFE/MAE asymmetry or directional path outcomes at different horizons.

### EXP-004 — Derivatives context
Within BTC Core, inspect whether OI change, funding, crowding, long/short, taker imbalance, and carry materially change 15m–6h path distributions. Keep missing fields missing.

### EXP-005 — State-change information
Compare observations where state materially changes versus repeated unchanged observations. Test whether change events contain stronger intraday information than static labels.

### EXP-006 — Bond increment
Only after A–C and derivatives/state-change analyses are canonical, condition the strongest relationships on Bond axes. Evaluate separately by horizon; do not assume Bond should matter equally at 15m and 6h.

### EXP-007 — Candidate BTC Mode rules
Propose simple deterministic confirmation/conflict/downgrade rules only after the earlier experiments.

For each candidate report:

- Risk-On / Wait & See / Risk-Off frequency
- return distribution by horizon
- MAE/MFE and MFE:MAE asymmetry
- time-to-MFE / time-to-MAE
- state-change subset behavior
- production-anchor subset behavior
- final chronological holdout behavior
- low-n/dependence warnings

## Tradability sensitivity

Report whether observed intraday differences remain meaningful under representative round-trip friction bands. Do not optimize to a specific venue or assume zero fees/slippage.

## Validation

Use chronological, not random, validation.

For the 30-day window:

- development/calibration: first ~70%
- final chronological holdout: last ~30%
- purge observations whose forward label window crosses the development/holdout boundary
- apply an embargo appropriate to the tested horizon when comparing adjacent samples
- compare day-level and session-level stability
- where feasible use day-clustered/bootstrap uncertainty rather than treating every overlapping hourly row as independent

Do not repeatedly tune on the holdout.

## Output artifacts

Persist/reproduce:

1. frozen input/raw-data manifest with checksums
2. canonical replay parity report against repository formulas
3. field coverage/missingness report
4. leakage-audit report
5. hourly observation dataset
6. intraday outcome/path dataset
7. conditional result tables including `n`
8. dependence-aware validation summary
9. candidate-rule comparison table
10. machine-readable result export
11. concise findings for `RESULTS_V1.md`
12. every attempted experiment in the experiment log
13. exact replay/analysis code under `research/btc-mode/`

## Stop conditions

Stop and report rather than guessing if:

- production formulas cannot be reproduced exactly
- a source timestamp is unsafe
- a required field is replaced with a semantically different proxy
- an apparent result depends on only a handful of independent days/events
- a candidate rule works only because overlapping observations are counted as independent evidence

## Definition of done

Research is ready for CTO/product review when we can answer, with PIT-safe intraday path evidence:

1. Does canonical Direction separate favorable versus hostile 15m–6h environments?
2. Does canonical BTC Core improve on Direction?
3. Does Market Regime add horizon-specific information after BTC Core?
4. Which derivatives variables materially improve intraday path discrimination?
5. Are state changes more informative than repeated static states?
6. Does Bond add incremental value, and at which horizons?
7. What simple deterministic Risk-On / Wait & See / Risk-Off rule is defensible for `btc-mode-v1`?
8. What recomputation cadence is justified independently from the twice-daily deep-context publication cadence?

No website deployment or WordPress integration is part of this task.
