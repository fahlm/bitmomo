# BTC Mode Research — Methodology

Status: DRAFT FOR EXECUTION

## Objective

Determine whether BTC Core, Market Regime, and Bond Intelligence provide stable, incremental information about BTC's forward risk environment, and use that evidence to design an explainable deterministic `btc-mode-v1` classification.

The objective is not to maximize in-sample accuracy and not to derive arbitrary weights.

## Unit of analysis

Primary unit: a point-in-time observation aligned to a defensible decision timestamp. Session-aware analysis should prefer the canonical 08:10 and 20:10 `America/New_York` anchors when the source data supports them.

BTC remains a 24/7 market. Weekend and US-holiday observations are included; US cash-equity calendar state is context, not a generation kill switch.

## Point-in-time safety

For every feature at decision time `T`:

1. `event_time` must not be after `T`.
2. `knowledge_time` must be known and `<= T`, or the feature must be excluded from intraday point-in-time claims.
3. Rolling statistics must use only observations available by `T`.
4. Swing/structure logic must use only closed candles available by `T`.
5. Historical revisions or later-published values must not be back-projected into earlier decision times unless a specific research experiment explicitly studies revised data and is labeled as such.
6. Forward BTC outcomes are labels only and may never influence feature construction.

Unknown historical knowledge time stays unknown. Do not fabricate an exact intraday availability time for daily Treasury observations.

## Outcomes

For each observation, calculate:

- forward return at +6h, +24h, +72h, +7d
- maximum adverse excursion (MAE) over each horizon
- maximum favorable excursion (MFE) over each horizon
- realized volatility over the evaluated horizon where consistently measurable

Report distributions, not only averages. At minimum include sample count, median, mean where useful, positive-return rate, lower-tail behavior, and MAE/MFE summaries.

## Baselines and model families

Evaluate in this order:

### A — Direction only
Use the canonical directional output/strength as a minimal baseline.

### B — BTC Core
Use the existing Signal Engine outputs and their canonical quality/confidence semantics.

### C — BTC Core + Market Regime
Test whether regime context changes the forward distribution conditional on BTC Core state. Do not equate regime labels directly with Risk-On/Risk-Off.

### D — BTC Core + Market Regime + Bond
Test whether Bond axes add incremental information after BTC-native information and regime are already known.

Experimental cross-asset fields such as DXY/Nasdaq/MOVE may be studied separately but must not silently become required V1 inputs.

## Conditional analysis before weighting

Before fitting any weights, inspect conditional outcome distributions, including examples such as:

- strong bullish vs strong bearish directional states
- bullish BTC Core during expansion vs distribution
- bearish BTC Core during accumulation vs capitulation
- bullish/expansion with rising vs falling real-rate pressure
- bearish/distribution with tightening vs supportive rates context
- transition regime with conflicting BTC Core evidence

The purpose is to discover when a variable is informative, redundant, contradictory, or regime-dependent.

## Validation

Prefer walk-forward or expanding-window validation rather than random train/test splits.

Illustrative structure:

- train 2021–2023, validate 2024
- train 2021–2024, validate 2025
- train 2021–2025, validate 2026 available period

Exact windows depend on defensible historical coverage of each source. The final holdout must not be used to tune rules repeatedly.

## Complexity rule

A more complex model is accepted only if it provides meaningful out-of-sample improvement in at least one important dimension without materially degrading the others:

- directional discrimination
- downside-risk discrimination
- upside participation
- stability across periods/regimes
- calibration/consistency
- data availability and operational reliability

If Bond or another pillar does not add durable information, it remains context/explanation rather than receiving decision power in BTC Mode.

## From research to production rule

Research may use richer statistical analysis, but the customer-facing production rule should remain deterministic, explainable, versioned, and auditable.

Before freezing `btc-mode-v1`:

- document the accepted evidence
- document rejected alternatives
- record sample sizes and limitations
- run a historical state-distribution sanity check
- confirm no absurd state concentration unless justified by data
- define fail-closed behavior for stale/missing required inputs
- define exact lineage/version fields

After freeze, methodology changes require a new mode version rather than silently rewriting V1.
