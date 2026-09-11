# Directional Microstructure Data Plan V0

Status: RESEARCH PLAN — NO PRODUCTION BUILD AUTHORIZED

Date: 2026-09-12

## Why this exists

P0.2B now has useful evidence for an Opportunity/Activity layer, but current price/Core/Regime/tactical inputs do not produce a stable chronological Directional Stance.

The next step is **not** to add more arbitrary weight to the existing slow engine. It is to test whether genuinely near-real-time microstructure adds incremental directional information.

## Principle

Historical proof comes before infrastructure.

Do not build a permanent collector, database, or new service until a candidate source demonstrates useful delayed-entry, point-in-time-safe incremental value.

## Phase 1 — historical microstructure experiment only

Priority source order:

### 1. Aggressive trade flow / CVD

Preferred source: Binance USD-M BTCUSDT aggregate trades / trades.

Derive small time-bucket features rather than fitting on raw trade IDs:

- aggressive buy notional
- aggressive sell notional
- signed delta
- CVD slope
- 1m / 3m / 5m / 15m delta
- trade-count acceleration
- average trade size
- large-trade share
- flow burst percentile / z-score
- price response per unit signed flow

Primary question:

> Does flow observed strictly before decision time improve first-excursion direction over 15m–2h after realistic entry latency?

### 2. Order-book imbalance / depth change

Only proceed if trade-flow research is insufficient or additive value is plausible.

Candidate features:

- top-of-book spread
- top 5 / 10 / 20 level bid-vs-ask notional imbalance
- depth slope
- depth change over 5s / 30s / 1m
- bid depletion / ask depletion
- microprice vs mid-price

Historical depth is harder to source reliably than trades. Do not make it a launch blocker before a defensible historical dataset exists.

### 3. Liquidation / forced-order flow

Optional third source.

Use only if a source provides reliable timestamps, side, size, and sufficient historical coverage. It should be tested as a shock/context input rather than assumed predictive.

## Point-in-time contract

Every observation used by the directional experiment must carry:

- `event_time`
- `received_at` or defensible availability time
- `source`
- `source_sequence` where available
- aggregation window start/end
- decision cutoff
- simulated entry time

Hard rule:

> No value whose aggregation period ends at the decision timestamp may be treated as safely known unless availability latency is explicitly measured and the backtest enters only after that latency.

This rule is required because the previous same-timestamp 5-minute derivatives test produced a false-looking edge that disappeared under stricter timing.

## Delayed-entry test grid

For every candidate microstructure feature set, evaluate at minimum:

- immediate next tradable observation where defensible
- +5 seconds / +15 seconds / +30 seconds when tick data supports it
- +1 minute
- +5 minutes

An effect that disappears after realistic latency is not production decision evidence.

## Outcomes

Primary:

- first +0.20% / -0.20% excursion
- first +0.30% / -0.30% excursion
- first +0.50% / -0.50% excursion where sample size permits
- direction of dominant MFE vs MAE

Horizons:

- 15m
- 30m
- 1h
- 2h

Secondary:

- 4h

## Model policy

Use models only as research instruments for discovering structure.

Production preference remains:

- simple deterministic rule
- explicit abstention
- explainable Primary Drivers
- no black-box probability marketed as certainty

A more complex model is accepted only if it demonstrates material chronological holdout improvement and survives delayed-entry tests.

## Minimal production architecture — only if research passes

If historical trade flow shows real incremental value, implement the lightest upstream collector possible outside WordPress.

The collector should:

1. consume public Binance USD-M market streams such as BTCUSDT aggregate trades; Binance also exposes public depth streams
2. aggregate to compact 1m feature rows
3. persist features, not an unlimited raw-trade archive, unless raw retention is explicitly needed
4. expose read-only `latest` and point-in-time `at_or_before` outputs to Bitmomo backend
5. include event/receive timestamps and source health
6. fail closed when stale

WordPress remains the presentation/session consumer, not the high-frequency stream processor.

## Cost / launch guardrail

Do not build a dedicated VPS or permanent WebSocket service merely because such infrastructure is technically attractive.

Build it only if the historical microstructure experiment demonstrates enough directional value to justify the operational cost.

Until then:

- Opportunity/Activity research continues on existing data
- Directional Stance remains selective
- `Wait & See` is legitimate output
- 08:10 / 20:10 session intelligence remains the deep-context layer

## Bond role

Bond Intelligence is not replaced by this plan.

Expected research split:

- 15m–2h: crypto-native microstructure has first priority for Directional Stance
- 2h–6h: BTC Core + Regime + microstructure interaction
- 4h–12h: Bond / macro incremental analysis becomes more relevant

No pillar receives decision power without horizon-specific evidence.
