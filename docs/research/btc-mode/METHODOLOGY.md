# BTC Mode Research — Methodology

Status: DRAFT FOR INTRADAY RE-EXECUTION

## Objective

Determine whether BTC Core, Market Regime, derivatives context, and Bond Intelligence provide stable, incremental information about BTC's **intraday risk environment** and use that evidence to design an explainable deterministic `btc-mode-v1` classification.

BTC Mode is intended for active short-horizon decision support. The research target is therefore not buy-and-hold performance over one to three days and not maximum in-sample directional accuracy.

The primary questions are:

- Is upside or downside path asymmetry favorable over the next minutes to hours?
- How large is favorable versus adverse excursion before the state changes?
- How quickly does the opportunity or invalidation appear?
- Does a richer context layer improve those intraday distinctions after BTC-native information is already known?

Do not derive arbitrary weights.

## Calibration window

V1 primary research window remains the latest defensible **30 contiguous days** for which the intended feature stack can be synchronized point-in-time.

A 30-day window is acceptable for intraday calibration because the research uses many point-in-time observations inside each day. The limitation is not calendar length alone; it is dependence between overlapping observations and the number of distinct market regimes represented. These limitations must be disclosed and followed by append-only forward validation.

## Observation sets

### Primary intraday replay

Replay the canonical feature stack at the highest cadence that is faithful to the underlying production inputs.

For the current V1 engine, **1-hour decision snapshots are the primary replay cadence** because Direction/Structure and several derivatives inputs are based on closed 1H/4H/D1 or hourly observations. Expected order of magnitude: ~720 observations over 30 days before exclusions.

Do not manufacture 15-minute feature updates from inputs that only update hourly. Higher-frequency feature sampling may be studied only for components whose source timestamps genuinely support it.

### Production-anchor subset

Retain the canonical Bitmomo session anchors as a product-specific subset:

- 08:10 `America/New_York`
- 20:10 `America/New_York`

These observations test how the intraday model behaves at the two deep-context publication anchors. They are no longer the primary statistical sample for BTC Mode calibration.

### State-change/event subset

Where defensible, identify observations around material changes in canonical inputs or candidate BTC Mode state. This subset is used to study whether state changes contain more information than repeated unchanged snapshots.

BTC remains a 24/7 market. Weekend and US-holiday observations remain included; US cash-equity calendar state is context, not a generation kill switch.

## Point-in-time safety

For every feature at decision time `T`:

1. `event_time` must not be after `T`.
2. `knowledge_time` must be known and `<= T`, or the feature must be excluded from intraday point-in-time claims.
3. Rolling statistics must use only observations available by `T`.
4. Swing/structure logic must use only closed candles available by `T`.
5. Forward BTC outcomes are labels only and may never influence feature construction.
6. Any daily macro observation without defensible intraday knowledge time may enter only after a documented availability rule; never back-project the day's final observation into earlier timestamps.
7. Missing data stays missing; do not silently convert missing fields to neutral/zero unless that behavior exactly matches the canonical production engine and is documented.
8. Replay code must reproduce the repository's production formulas exactly. A semantically similar substitute indicator is not canonical evidence.

## Outcomes

### Primary intraday horizons

For every valid decision snapshot calculate:

- +15m
- +30m
- +1h
- +2h
- +4h
- +6h

### Secondary context horizons

- +12h
- +24h

Longer horizons such as +72h are optional persistence context only and must not drive V1 intraday Mode calibration.

### Path metrics

For each horizon calculate where defensible:

- forward return
- maximum favorable excursion (MFE)
- maximum adverse excursion (MAE)
- time to MFE
- time to MAE
- realized volatility
- path ordering where useful: whether meaningful favorable or adverse movement occurred first

Report distributions, not only averages. Include sample count, median, relevant quantiles, positive-return rate, MAE/MFE summaries, and low-tail behavior.

Because observations overlap heavily, raw row count must never be presented as independent sample size.

## Tradability / cost sensitivity

BTC Mode is not an execution bot, but an intraday classification is only useful if the detected move is economically meaningful.

Evaluate outcome sensitivity to plausible trading-friction bands without assuming a specific exchange or strategy. Report whether observed MFE/MAE differences remain material after representative fee/slippage thresholds. Do not optimize the Mode rule to one fee schedule.

## Baselines and model families

Evaluate in this order:

### A — Direction only
Use the canonical production directional output/strength as a minimal baseline.

### B — BTC Core
Use the exact existing Signal Engine outputs and canonical quality/confidence semantics, including available derivatives inputs.

### C — BTC Core + Market Regime
Test whether regime context changes intraday return/path-risk distributions conditional on BTC Core state. Do not equate regime labels directly with Risk-On/Risk-Off.

### D — BTC Core + Market Regime + Bond
Test whether Bond axes add incremental information after BTC-native information and regime are already known. Bond may matter more at 4h–12h than at 15m–1h; evaluate by horizon rather than forcing one universal effect.

Experimental cross-asset fields such as DXY/Nasdaq/MOVE may be studied separately but must not silently become required V1 inputs.

## Conditional analysis before weighting

Do not optimize precise weights from this sample. First inspect conditional intraday outcome distributions, including:

- canonical direction strength versus 15m/30m/1h/2h/4h/6h path outcomes
- BTC Core state versus MFE/MAE asymmetry
- bullish BTC Core during expansion versus distribution
- bearish BTC Core during accumulation versus capitulation
- crowded versus cleaner positioning
- rising versus falling OI conditional on price direction
- taker imbalance conditional on structure
- high/extreme volatility versus normal/low volatility
- supportive versus tightening real-rate context by horizon
- state-change observations versus unchanged repeated observations

The purpose is to discover when a variable is informative, redundant, contradictory, horizon-dependent, or context-dependent.

## BTC Mode research interpretation

Candidate state semantics to test empirically:

- **Risk-On:** upside/favorable excursion is meaningfully dominant relative to adverse path risk over the relevant intraday horizon.
- **Wait & See:** path asymmetry is weak, mixed, unstable, or too small to be useful after realistic friction.
- **Risk-Off:** downside/adverse excursion is meaningfully dominant or the environment is hostile to long risk over the relevant intraday horizon.

These are hypotheses for empirical testing, not frozen production definitions.

## Validation for overlapping intraday observations

Do not use random train/test splits.

Use:

1. strict chronological development and final holdout segments
2. purging/embargo where forward outcome windows overlap the split boundary
3. hourly primary replay versus production-anchor subset comparison
4. state-change/event subset versus repeated unchanged-state observations
5. first-part versus later-part stability checks
6. sensitivity checks that small threshold changes do not radically flip conclusions
7. minimum-sample labeling for every conditional cell
8. cluster/day-level bootstrap or equivalent dependence-aware uncertainty where feasible
9. no repeated tuning against the final chronological holdout

## Complexity rule

A richer model is accepted only if it provides meaningful improvement in at least one important dimension without materially degrading the others:

- intraday directional/path discrimination
- downside-risk discrimination
- upside participation
- MFE/MAE asymmetry
- time-to-opportunity / time-to-invalidation
- stability across time and market conditions
- data availability and operational reliability

If Bond, Regime, or another pillar does not add clear information at a given horizon, it remains context/explanation for that horizon rather than receiving decision power.

## From research to production rule

Research may use richer descriptive statistics, but the customer-facing production rule must remain deterministic, explainable, versioned, and auditable.

Before freezing `btc-mode-v1`:

- reproduce canonical production semantics exactly
- document accepted evidence and rejected alternatives
- record sample sizes and dependence limitations
- compare hourly intraday replay, production anchors, and state-change subsets
- define fail-closed behavior for stale/missing required inputs
- define exact lineage/version fields
- explicitly state which pillars have decision power at which horizon versus explanation-only status
- define the production recomputation cadence separately from the 08:10/20:10 deep-context publication cadence

After freeze, methodology changes require a new mode version rather than silently rewriting V1. Forward validation should accumulate on every official Mode evaluation/state change, while 08:10/20:10 sessions remain the canonical deep-context reports.
