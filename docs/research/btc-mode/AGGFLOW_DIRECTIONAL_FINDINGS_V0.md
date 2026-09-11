# BTC Mode Research — Aggressive Flow / CVD Directional Findings V0

Status: RESEARCH FINDING — NOT PRODUCTION METHODOLOGY

## Objective

Test whether Binance USD-M BTCUSDT aggressive-flow / CVD features can supply a point-in-time-safe Directional Stance for Bitmomo's intraday BTC Mode after the prior static Direction/Core/Regime directional results proved too weak.

## Dataset and point-in-time construction

Source bundle: Binance USD-M BTCUSDT public `aggTrades` archive, 2026-08-02T00:00:00Z through before 2026-09-01T00:00:00Z.

Coverage in the supplied compact export:

- 35,277,102 aggregate trades
- 43,200 complete one-minute buckets
- 8,640 complete five-minute buckets
- no missing one-minute or five-minute buckets
- `knowledge_time` equals bucket end
- aggressive buy = `is_buyer_maker=false`; aggressive sell = `is_buyer_maker=true`

Research decision cutoffs were sampled every 15 minutes from 2026-08-02 04:10 UTC through 2026-08-31 21:55 UTC, producing 2,856 usable observations.

To avoid the timing issue discovered in the prior derivatives experiment, features use only buckets whose `knowledge_time <= cutoff`, and execution is delayed by five minutes. Returns/path labels begin from the first completed five-minute candle after the signal cutoff.

Chronological validation:

- calibration: 1,992 observations through 2026-08-22 21:55 UTC
- two-hour purge before the split
- holdout: 856 observations from 2026-08-23 00:10 UTC onward
- no random split

Primary delayed-entry horizons: +15m, +30m, +1h, +2h.

## Features tested

Research-only features included:

- aggressive buy/sell imbalance over 5m / 15m / 30m / 60m
- signed delta and CVD change over those windows
- normalized CVD slope over 15m / 30m / 60m
- short-vs-long flow acceleration
- quantity and aggTrade-count intensity
- price deviation from flow VWAP
- recent price returns/range as interactions
- slower BTC Core / Market Regime context as a sensitivity check

No feature was granted production decision power in advance.

## Results

### 1. Aggressive flow alone does not solve Directional Stance

A regularized multi-feature flow model produced holdout direction AUCs of approximately:

- +15m: 0.529
- +30m: 0.501
- +1h: 0.513
- +2h: 0.497

Adding recent price/flow interactions improved +15m modestly but did not create a robust longer-horizon edge:

- +15m: 0.549
- +30m: 0.524
- +1h: 0.508
- +2h: 0.491

A shallow nonlinear model did not rescue the result. Holdout AUC was roughly 0.554 at +15m and deteriorated below 0.50 at +1h/+2h while calibration performance increased, which is consistent with overfit rather than stable information.

### 2. The strongest univariate effect is small and mostly contrarian

The most persistent individual relationship was 60-minute aggressive-flow imbalance versus very short-horizon returns. Its direction was mostly contrarian: more net aggressive buying tended to precede slightly weaker near-term returns, and vice versa.

For +15m, the calibration-oriented AUC was only ~0.527 while holdout AUC reached ~0.568. Daily rank correlation between 60m flow imbalance and +15m return was negative on 23 of 30 days, but the median daily correlation was only about -0.06.

Interpretation: a small mean-reversion/exhaustion tendency may exist, but the magnitude is too small to use as the principal directional engine.

### 3. Selective short-horizon classification is not economically strong enough

A regularized flow+price model restricted to the most confident 20% of calibration scores achieved on the holdout:

- +15m hit rate ~60.6%
- average signed +15m return only ~0.019% gross

At +30m, a high-opportunity subset could reach ~57% directional hit rate with approximately +0.04% gross average signed return under a broader selective rule.

Those gross edges are too thin to survive realistic fee/slippage assumptions reliably and therefore do not qualify as production Directional Stance evidence.

### 4. Aggressive flow does not reliably predict first meaningful excursion direction

When the target was changed from close-to-close return sign to which side first reached a meaningful +/-0.2% or +/-0.3% excursion, the flow models did not generalize. Holdout AUCs were approximately random or worse across +30m/+1h/+2h.

This matters because Bitmomo is targeting intraday decision support: a feature that cannot reliably identify the first tradable side should not be promoted merely because it has a weak end-of-window correlation.

### 5. Flow may still have value as context / veto, but not yet as a setter

When 60-minute aggressive-flow direction agreed with a non-neutral BTC Core direction, the later holdout showed some improvement versus conflict, particularly around +30m. However, the same separation was weak or absent in calibration and was not stable across horizons.

Therefore aggressive flow may be retained for forward validation as:

- confirmation/conflict evidence
- potential downgrade/veto context
- a Primary Driver explanation when extreme

It should not yet set `risk_on` or `risk_off` by itself.

### 6. Opportunity remains much stronger than Direction

This experiment does not overturn the prior finding that recent realized BTC range/activity is materially better at answering **whether a meaningful move is likely** than the tested inputs are at answering **which direction the move will take**.

The two-stage architecture remains justified:

1. Opportunity / Activity
2. Directional Stance
3. Market Regime context
4. Bond/Macro context by appropriate horizon
5. final BTC Mode with `wait_and_see` as a valid abstention state

## Decision

**REJECT aggressive-flow/CVD V0 as the primary Directional Stance setter.**

Do not freeze a Risk-On/Risk-Off rule from these features.

**KEEP as research/context inputs** for confirmation, conflict, extreme-flow Primary Drivers, and append-only forward validation.

## Next research priority

Before building expensive order-book infrastructure, test whether **spot-vs-perpetual flow leadership/divergence** adds stable direction information. Rationale: perpetual aggressive flow alone can represent leverage chasing or exhaustion; spot-led buying/selling may better distinguish genuine directional demand from leveraged positioning.

If spot/perp flow divergence also fails, the next candidate is point-in-time order-flow imbalance / depth dynamics, followed by liquidation-flow context. These should be promoted only after delayed-entry chronological validation demonstrates economically meaningful incremental value.

## Production impact

None.

No runtime code, WordPress, staging, production, BTC Mode methodology, or customer-facing output is changed by this finding.
