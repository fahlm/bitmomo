# BTC Mode Research — Multilayer Data V0

Status: EXECUTION SPEC / RESEARCH ONLY

Issue: #78

## Why this exists

P0.2B found that Bitmomo currently separates two very different strengths:

- **Opportunity / Activity** has materially stronger and more stable short-horizon evidence.
- **Directional Stance** is unstable when BTC Core, aggressive flow/CVD, Spot flow, Spot-vs-perp divergence, or harder Core gating are evaluated unconditionally.

The next research hypothesis is not "add another indicator." It is that some tactical directional relationships may be **conditional on higher-level context**.

This document defines the minimum multilayer data architecture needed to test that hypothesis without turning Bitmomo into a general-purpose data warehouse and without blocking launch.

## Product principle

Bitmomo remains a short-horizon BTC decision-intelligence product.

Higher-horizon context exists to change the interpretation of tactical evidence, not to replace tactical evidence.

The intended hierarchy is:

1. Cycle / structural context
2. Macro / liquidity context
3. Market regime
4. Tactical structure
5. Microstructure
6. Opportunity / Activity
7. Directional Stance
8. Product synthesis

No layer automatically overrides all layers below it. Decision power must be earned empirically by horizon.

## Layer A — Long-cycle context

### Source scope

Initial V0 source is Binance Spot `BTCUSDT` OHLCV from first defensible available history through present.

Required native cadences:

- 1D
- 4H

Store source candles as immutable raw inputs with provenance and checksums. Derived features are versioned separately.

### Derived features

V0 may derive only simple, auditable features that do not depend on tactical outcomes:

- close / OHLCV
- rolling return: 1D, 7D, 30D, 90D, 180D where valid
- rolling realized volatility / range percentile
- drawdown from rolling / all-time high
- distance from ATH
- moving-average state and slope using fixed windows
- higher-high / higher-low or lower-high / lower-low structure where deterministically defined
- range position
- trend strength / trend state
- momentum state
- halving metadata:
  - previous halving timestamp
  - next known scheduled halving reference where used only as metadata
  - days since previous halving
  - normalized position between historical halving dates

Halving metadata is **not** a predictor by construction. It is a context field to be tested.

### Research context labels

V0 should begin with deliberately coarse, non-optimized context labels:

- `trend_context`: bullish / bearish / range
- `volatility_context`: low / normal / high
- `cycle_context`: descriptive phase metadata only

Thresholds must be fixed before joining tactical outcomes. They must not be tuned to maximize Directional Stance performance.

## Layer B — Medium-horizon context

### Cadence

Use 1H / 4H where source history and point-in-time semantics are defensible.

### Candidate inputs

- canonical BTC Core component outputs
- canonical Market Regime outputs
- funding / open interest / derivatives history where source timestamps are defensible
- Bond / rates context where knowledge-time can be represented honestly
- later macro/liquidity sources only if they add incremental value

Layer B is intentionally optional for the first V0 join. Layer A + existing tactical data must be researchable before Layer B is complete.

## Layer C — Tactical rolling context

Reuse existing P0.2B assets rather than reacquire them:

- Spot aggTrades
- USD-M perpetual aggTrades
- aggressive buy/sell flow
- signed delta / CVD
- derivatives inputs
- short-horizon BTC price structure
- Opportunity features
- existing BTC Core / Regime snapshots where available
- forward outcomes at +15m / +30m / +1h / +2h, with longer horizons as context only

Source features may exist at 1m / 5m, while research decision cutoffs remain 15m/hourly only when justified by knowledge-time and input cadence.

## Point-in-time join contract

For every tactical cutoff `T`:

- every Layer C feature must have `knowledge_time <= T`
- every Layer B feature must have `knowledge_time <= T`
- Layer A 4H / 1D context must use only candles closed before or at the defensible cutoff
- daily features cannot use the current still-open daily candle
- halving metadata can use only predetermined historical event dates; no future market information

The joined research row must retain:

- `cutoff_time`
- source observation timestamps
- knowledge times
- layer versions
- source/provenance identifiers
- missingness flags

No unavailable higher-layer feature is silently imputed as neutral.

## Research sequence

### R1 — Context-only descriptive audit

Before directional testing, report:

- history coverage
- missingness
- number of days / 4H bars in each trend/volatility context
- transition frequency
- whether one context dominates the 30-day tactical window

This establishes whether the tactical month was structurally unusual.

### R2 — Conditional re-test of existing weak signals

Re-test, without changing their formulas:

- BTC Core
- canonical structure state
- aggressive flow / CVD
- Spot flow
- Spot-vs-perp divergence
- selective Core gates

For each feature, compare unconditional performance with conditional slices such as:

- bull vs bear vs range trend context
- low vs normal vs high volatility context
- combinations only when sample size is sufficient

Do not create dozens of slice combinations. Start coarse.

### R3 — Interaction stability

A context-dependent relationship is considered interesting only if:

- direction of effect is consistent across multiple chronological folds
- holdout does not collapse toward random
- sample count is sufficient to make the slice operationally meaningful
- gross edge survives plausible friction where the product claim implies tradability
- the rule remains simple enough to explain

### R4 — Product-role decision

Each input receives one of these roles:

- `decision_power`
- `confirmation`
- `downgrade_or_veto`
- `primary_driver_explanation`
- `context_only`
- `rejected`

No input gets decision power because it is intuitively plausible.

## Validation

- chronological / walk-forward only
- purge/embargo for overlapping forward outcomes
- report row count and effective day/event coverage
- do not equate overlapping rows with independent observations
- calibration and holdout shown separately
- regime slices with very small `n` remain exploratory

## Relationship to launch

This research does **not** block productionizing the already-supported Opportunity layer.

Until Directional Stance earns stronger evidence, the launch product may expose:

- Opportunity
- Directional Bias
- Confidence as evidence strength, not probability of correctness
- Market State
- Primary Drivers
- Changed / state change

A customer-facing three-state `Risk-On / Wait & See / Risk-Off` verdict remains withheld from production synthesis until its Directional Stance component is empirically defensible.

## Non-goals

V0 does not require:

- a data warehouse rewrite
- a VPS migration
- multi-year order-book reconstruction
- every macro series
- a machine-learning model
- a fitted composite score
- a hardcoded 4-year halving-cycle rule

## Acceptance criteria

Multilayer Data V0 is ready for empirical use when:

1. long-history BTCUSDT 1D/4H source data is exported with provenance/checksums;
2. deterministic long-context features are reproducible locally;
3. every joined tactical row is PIT-safe;
4. context definitions are frozen before outcome analysis;
5. unconditional vs context-conditioned Directional Stance results are documented;
6. accepted and rejected context interactions are explicit;
7. a clear decision is recorded on whether higher-level context materially improves Directional Stance.
