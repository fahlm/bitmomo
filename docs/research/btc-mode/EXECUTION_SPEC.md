# BTC Mode Research — Execution Spec

Status: READY FOR RESEARCH EXECUTION

This is the handoff specification for the research engineer/environment. It must not modify WordPress, staging, production, or the canonical live engine rules.

## Goal

Build a strict point-in-time 30-day full-feature research dataset and evaluate whether BTC Core, Market Regime, and Bond Intelligence should influence `btc-mode-v1` for short-horizon decision support.

Do not invent production weights. Do not change live thresholds. Do not optimize against the final chronological holdout.

## Inputs

### BTC Core
Reproduce the current canonical Bitmomo Signal Engine semantics from the repository, including:

- Direction: ADX, +DI/-DI, 1H/4H/1D bias, direction score/status
- Structure: 4H and 1D state, structure score/status
- Carry: funding, basis, carry status/score
- Crowding: 24H OI change, BTC 24H price change, global long/short, taker buy/sell, crowding status/score
- Volatility: ATR fields, ATR percentile, Bollinger width, volatility regime
- Canonical outputs: directional score, directional bias, direction strength, confidence, data quality

Use the same formulas/rules as production. If a historical field cannot be reconstructed faithfully, mark it unavailable and record the exclusion; never silently substitute a different indicator.

### Market Regime
Reproduce the current canonical Regime classifier rules from the repository:

- accumulation
- expansion
- distribution
- capitulation
- transition
- regime confidence/certainty
- scores/evidence/conflicts where feasible

Do not infer Regime from Directional Bias.

### Bond Intelligence
Use the existing Replit Bond Intelligence service/store point-in-time outputs where defensible:

- policy repricing
- real-rate pressure
- inflation repricing
- curve state
- bond volatility if available
- underlying 2Y / 10Y / real 10Y / breakeven / 2s10s context
- source maturity/provenance/freshness
- knowledge-time status
- schema/rule version

BTC price/outcome inside Bond Intelligence is evaluation shadow only and must never feed Bond State or BTC Mode features.

## Window

Use the latest contiguous 30-day period for which the maximum synchronized feature coverage can be defended.

Report the exact start/end timestamps and coverage percentage for each source/field family before analysis.

## Observation set A — production aligned

Generate observations at:

- 08:10 America/New_York
- 20:10 America/New_York

for every calendar day, including weekends and US holidays.

Expected maximum: ~60 rows before exclusions.

Set `sample_type = production_aligned` and the proper session identity.

## Observation set B — robustness

Generate observations every 4 hours across the same window using only information available by each decision timestamp.

Expected maximum: ~180 rows before exclusions.

Set `sample_type = robustness_4h`. This is research-only and does not change Bitmomo production cadence.

## Point-in-time requirements

For decision timestamp T:

- use only closed BTC candles available by T
- derivatives observations must have event/knowledge time <= T
- no future swing confirmation or future candle use
- rolling features end at T
- no future macro observation backfill
- Treasury daily values may enter only after defensible historical availability
- unknown knowledge time stays unknown/unavailable for intraday use
- outcomes are computed only after feature rows are frozen

Create an explicit leakage-audit report before accepting the dataset.

## Outcomes

For every observation compute from a consistent BTC reference source:

Primary:
- return +6h
- return +12h
- return +24h
- MAE +6h/+12h/+24h
- MFE +6h/+12h/+24h
- realized volatility +6h/+12h/+24h where defensible

Secondary:
- return +72h
- MAE/MFE +72h
- realized volatility +72h

Document exact price convention (e.g. first closed candle at/after target timestamp) and use it consistently.

## Required analyses

### EXP-001 — Direction baseline
Group by canonical direction strength and report all primary outcomes.

### EXP-002 — BTC Core
Evaluate whether the combined canonical BTC Core state improves return/path-risk discrimination versus Direction alone.

### EXP-003 — Market Regime increment
Condition BTC Core results on Regime. At minimum inspect:

- Bullish/Strong Bullish x Expansion
- Bullish/Strong Bullish x Distribution
- Bearish/Strong Bearish x Accumulation
- Bearish/Strong Bearish x Capitulation
- Transition x each directional family where sample exists

### EXP-004 — Bond increment
Condition the strongest BTC Core + Regime relationships on:

- real-rate pressure rising/stable/falling or canonical equivalent
- policy repricing state
- curve state
- bond-volatility state only if source quality is acceptable

Determine whether Bond confirms, conflicts with, or adds no reliable information.

### EXP-005 — Derivatives context
Within BTC Core, inspect whether elevated/crowded versus cleaner derivatives conditions materially change +12h/+24h MAE/MFE and forward-return distributions.

### EXP-006 — Candidate BTC Mode rules
Propose simple deterministic confirmation/conflict/downgrade rules. Do not fit precise weights unless a simple rule clearly fails and the evidence for weighting is unusually strong.

For each candidate rule report:

- Risk-On / Wait & See / Risk-Off frequency
- outcome distributions by state
- MAE/MFE by state
- production-aligned results
- 4-hour robustness results
- chronological holdout behavior
- low-n conditions

## Validation

Use chronological, not random, validation.

Recommended structure for the 30-day window:

- development/calibration: first ~70% of time
- final chronological holdout: last ~30%

Do not repeatedly tune rules on the holdout.

Also compare first-half versus second-half relationship direction and production-aligned versus 4-hour robustness samples.

## Output artifacts

Do not return only prose in chat. Persist/reproduce:

1. frozen dataset manifest with checksum
2. field coverage/missingness report
3. leakage-audit report
4. conditional result tables including `n`
5. candidate-rule comparison table
6. machine-readable result export where useful
7. concise findings for `RESULTS_V1.md`
8. every attempted experiment appended to `EXPERIMENT_LOG.md`, including rejected/failed ones
9. exact code/scripts used for replay and analysis under `research/btc-mode/`

## Stop conditions

Stop and report rather than guessing if:

- full-feature synchronization cannot be reconstructed for a meaningful portion of 30 days
- production formulas cannot be reproduced exactly
- timestamps/knowledge time make an input unsafe
- an apparent result depends on only a handful of rows
- source semantics differ materially from the production input

## Definition of done

Research is ready for CTO/product review when we can answer, with sample counts and path-risk evidence:

1. Does BTC Core separate favorable versus hostile short-horizon BTC environments?
2. Does Market Regime add useful context after BTC Core?
3. Does Bond materially improve the classification, and under which conditions?
4. Which variables should have decision power versus explanation-only status?
5. What simple deterministic Risk-On / Wait & See / Risk-Off rule is defensible for `btc-mode-v1`?

No website deployment or WordPress integration is part of this task.
