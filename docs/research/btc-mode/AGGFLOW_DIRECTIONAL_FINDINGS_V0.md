# BTC Mode Research — Aggressive Flow / CVD Directional Findings V0

Status: RESEARCH FINDING — NOT PRODUCTION METHODOLOGY

## Objective

Test whether Binance USD-M BTCUSDT aggressive-flow / CVD features can supply a point-in-time-safe Directional Stance for Bitmomo's intraday BTC Mode.

## Dataset and PIT construction

Source: Binance USD-M BTCUSDT public `aggTrades`, 2026-08-02T00:00:00Z through before 2026-09-01T00:00:00Z.

Coverage:

- 35,277,102 aggregate trades
- 43,200 one-minute buckets
- 8,640 five-minute buckets
- no missing 1m/5m buckets
- `knowledge_time` = bucket end
- aggressive buy = `is_buyer_maker=false`; aggressive sell = `is_buyer_maker=true`

Research cutoffs were sampled every 15 minutes from 2026-08-02 04:10 UTC through 2026-08-31 21:55 UTC, producing 2,856 usable observations.

To prevent the timestamp leakage seen in the prior derivatives experiment:

- features use only buckets with `knowledge_time <= cutoff`
- execution is delayed five minutes
- path/return labels begin from the first completed five-minute candle after signal cutoff
- chronological validation is used, never random split
- calibration: 1,992 observations through 2026-08-22 21:55 UTC
- two-hour purge before the split
- holdout: 856 observations from 2026-08-23 00:10 UTC onward

Primary delayed-entry horizons: +15m, +30m, +1h, +2h.

## Features tested

- aggressive buy/sell imbalance: 5m / 15m / 30m / 60m
- signed delta / CVD change
- normalized CVD slope: 15m / 30m / 60m
- short-vs-long flow acceleration
- quantity and aggTrade-count intensity
- deviation from flow VWAP
- recent price-return/range interactions
- slower BTC Core / Market Regime context as sensitivity only

## Findings

### Aggressive flow alone does not solve Directional Stance

Regularized multi-feature flow model holdout direction AUC:

- +15m: ~0.529
- +30m: ~0.501
- +1h: ~0.513
- +2h: ~0.497

Adding recent price/flow interactions:

- +15m: ~0.549
- +30m: ~0.524
- +1h: ~0.508
- +2h: ~0.491

A shallow nonlinear model reached only ~0.554 at +15m and deteriorated below 0.50 at +1h/+2h while calibration fit improved. This is consistent with overfit rather than durable directional information.

### Small contrarian tendency exists, but it is weak

The most persistent individual relation was 60-minute aggressive-flow imbalance versus very short-horizon return. Higher net aggressive buying tended to precede slightly weaker returns, and vice versa.

For +15m:

- calibration-oriented AUC: ~0.527
- holdout AUC: ~0.568
- daily rank correlation was negative on 23/30 days
- median daily rank correlation was only about -0.06

This is consistent with a small mean-reversion / exhaustion effect, not a production-quality directional engine.

### Selective accuracy is not economically strong enough

A selective flow+price model could achieve on holdout:

- +15m hit rate ~60.6% on its most confident subset
- average signed +15m return only ~0.019% gross

On a high-opportunity subset at +30m, directional hit rate could approach ~57%, with gross average signed return around +0.04%.

The gross edge is too thin to rely on after realistic fee/slippage sensitivity. Accuracy alone is therefore insufficient grounds for promotion.

### First meaningful excursion direction does not generalize

When the target was which side first reached +/-0.2% or +/-0.3%, rather than end-of-window return sign, holdout discrimination was approximately random or worse over +30m/+1h/+2h.

For an intraday decision product, this is a critical rejection test: the feature should not be promoted merely because it has a weak close-to-close relationship.

### Flow may remain context, not setter

There are hints that 60m flow agreement/conflict can modify a non-neutral BTC Core at some horizons, especially around +30m, but the separation is not stable between calibration and holdout or across horizons.

Aggressive flow may remain eligible for:

- confirmation/conflict evidence
- possible future downgrade/veto logic if forward validation confirms it
- Primary Drivers when flow is extreme

It does not receive authority to set `risk_on` or `risk_off`.

## Decision

**REJECT Aggressive Flow / CVD V0 as the primary Directional Stance setter.**

**KEEP as research/context input.**

Do not freeze a BTC Mode Risk-On/Risk-Off mapping from these features.

The prior two-stage architecture remains:

1. Opportunity / Activity
2. Directional Stance
3. Market Regime context
4. Bond/Macro context at appropriate horizons
5. final BTC Mode with `wait_and_see` as a first-class abstention state

## Next research priority

Test **spot-vs-perpetual flow leadership/divergence** before building expensive order-book infrastructure.

Rationale: perpetual aggressive flow can reflect leverage chasing or exhaustion. Comparing spot demand with perpetual demand may distinguish genuine directional demand from leveraged positioning.

If spot/perp divergence also fails delayed-entry chronological validation, next candidates are point-in-time order-flow/depth imbalance and liquidation-flow context.

## Production impact

None. No runtime code, staging, production, WordPress, or customer-facing BTC Mode methodology is changed by this research finding.
