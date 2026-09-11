# BTC Mode Research — Methodology

Status: DRAFT FOR EXECUTION

## Objective

Determine whether BTC Core, Market Regime, and Bond Intelligence provide stable, incremental information about BTC's **short-horizon** forward risk environment, and use that evidence to design an explainable deterministic `btc-mode-v1` classification.

The objective is not to maximize in-sample accuracy and not to derive arbitrary weights.

## Calibration window

V1 primary research window: the latest defensible **30 days** for which the full intended feature stack can be synchronized point-in-time.

A complete short window is preferred over a longer history that drops live derivatives inputs. This choice must be disclosed as a limitation and followed by append-only forward validation after launch.

## Observation sets

### Production-aligned sample

Replay only at the canonical Bitmomo decision anchors:

- 08:10 `America/New_York`
- 20:10 `America/New_York`

Expected order of magnitude: ~60 observations over 30 days.

This sample has highest product relevance and is the primary acceptance sample.

### Research robustness sample

Replay every 4 hours using only information available at each timestamp.

Expected order of magnitude: ~180 observations over 30 days.

This sample exists to test whether observed relationships are directionally robust beyond the two publication anchors. It must not be used to imply Bitmomo publishes six editions per day.

BTC remains a 24/7 market. Weekend and US-holiday observations are included; US cash-equity calendar state is context, not a generation kill switch.

## Point-in-time safety

For every feature at decision time `T`:

1. `event_time` must not be after `T`.
2. `knowledge_time` must be known and `<= T`, or the feature must be excluded from intraday point-in-time claims.
3. Rolling statistics must use only observations available by `T`.
4. Swing/structure logic must use only closed candles available by `T`.
5. Forward BTC outcomes are labels only and may never influence feature construction.
6. Any daily macro observation without defensible intraday knowledge time must be carried only according to a documented availability rule; never back-project the day's final observation into earlier timestamps.
7. Missing data stays missing; do not silently convert missing fields to neutral/zero unless that behavior exactly matches the canonical production engine and is documented.

## Outcomes

Primary horizons for every observation:

- forward return at +6h
- forward return at +12h
- forward return at +24h

Secondary persistence horizon:

- forward return at +72h

For each horizon calculate where defensible:

- maximum adverse excursion (MAE)
- maximum favorable excursion (MFE)
- realized volatility

Report distributions, not only averages. At minimum include sample count, median, mean where useful, positive-return rate, lower-tail behavior, MAE/MFE summaries, and relevant quantiles.

The +12h horizon is particularly important because it approximates the interval from one canonical Bitmomo edition to the next.

## Baselines and model families

Evaluate in this order:

### A — Direction only
Use the canonical directional output/strength as a minimal baseline.

### B — BTC Core
Use the existing Signal Engine outputs and canonical quality/confidence semantics, including available derivatives inputs.

### C — BTC Core + Market Regime
Test whether regime context changes the forward distribution conditional on BTC Core state. Do not equate regime labels directly with Risk-On/Risk-Off.

### D — BTC Core + Market Regime + Bond
Test whether Bond axes add incremental information after BTC-native information and regime are already known.

Experimental cross-asset fields such as DXY/Nasdaq/MOVE may be studied separately but must not silently become required V1 inputs.

## Conditional analysis before weighting

Do not optimize precise weights from this sample. First inspect conditional outcome distributions, including:

- strong bullish vs strong bearish directional states
- bullish BTC Core during expansion vs distribution
- bearish BTC Core during accumulation vs capitulation
- bullish/expansion with rising vs falling real-rate pressure
- bearish/distribution with tightening vs supportive rates context
- transition regime with conflicting BTC Core evidence
- crowded/elevated derivatives conditions versus cleaner positioning
- high/extreme volatility versus normal/low volatility

The purpose is to discover when a variable is informative, redundant, contradictory, or context-dependent.

## Rule discovery approach

Prefer simple confirmation/conflict logic over fitted weights.

Examples of candidate rule forms to test, not assume:

- BTC Core Risk-On candidate + supportive Regime + supportive/neutral Macro -> retain Risk-On.
- BTC Core Risk-On candidate + conflicting Regime or tightening Macro -> downgrade to Wait & See if outcome distributions support the downgrade.
- BTC Core Risk-Off candidate + Distribution/Capitulation + tightening Macro -> retain Risk-Off if downside/MAE evidence confirms.

A rule is accepted only from documented empirical evidence.

## Validation for a short sample

Classic multi-year walk-forward validation is not available for the synchronized full-feature V1 sample. Use instead:

1. strict chronological splits; never random splits
2. production-aligned versus 4-hour robustness comparison
3. first-part versus later-part stability checks
4. sensitivity checks that small threshold changes do not radically flip the conclusion
5. minimum-sample labeling for all conditional cells
6. no repeated tuning against the final chronological holdout

The final segment must remain untouched until candidate rules are substantially specified.

## Complexity rule

A richer model is accepted only if it provides meaningful improvement in at least one important dimension without materially degrading the others:

- directional discrimination
- downside-risk discrimination
- upside participation
- stability across timestamps
- calibration/consistency
- data availability and operational reliability

If Bond or another pillar does not add clear information, it remains context/explanation rather than receiving decision power in BTC Mode.

## From research to production rule

Research may use richer descriptive statistics, but the customer-facing production rule must remain deterministic, explainable, versioned, and auditable.

Before freezing `btc-mode-v1`:

- document accepted evidence and rejected alternatives
- record sample sizes and confidence limitations
- compare production-aligned and robustness samples
- run historical state-distribution sanity checks
- define fail-closed behavior for stale/missing required inputs
- define exact lineage/version fields
- explicitly state which pillars have decision power versus explanation-only status

After freeze, methodology changes require a new mode version rather than silently rewriting V1. Forward validation then accumulates append-only twice daily and becomes the main long-run evidence base.
