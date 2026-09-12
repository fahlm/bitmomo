# P0.2C — Multilayer Context Findings V0

Status: RESEARCH FINDING — NOT PRODUCTION METHODOLOGY

Issue: #78

## Scope

This pass evaluates the first canonical long-history context bundle against the existing P0.2B tactical research assets.

Source bundle:
- Binance Spot BTCUSDT 1D OHLCV: 3,312 rows, 2017-08-17 through 2026-09-10 UTC
- Binance Spot BTCUSDT 4H OHLCV: 19,854 rows, 2017-08-17 through 2026-09-10 UTC
- generated file checksums were independently re-computed and matched the supplied manifest
- 1D coverage has no missing intervals
- 4H coverage has 17 missing intervals across 9 historical gaps, approximately 0.086% of the expected grid; gap-crossing 4H research windows are excluded from canonical pattern examples

All joins use `knowledge_time <= cutoff`. Daily context never uses a still-open daily candle.

## Context definitions used in this pass

These definitions are deliberately coarse and were fixed for this pass rather than optimized against tactical outcomes.

### Daily trend context

`bull`
- completed daily close > SMA200
- SMA50 > SMA200
- trailing 90-day return > 0

`bear`
- completed daily close < SMA200
- SMA50 < SMA200
- trailing 90-day return < 0

`range_mixed`
- all other valid states

### Daily volatility context

- 30-day realized volatility from completed daily log returns
- current 30-day volatility is ranked against the previous 730 completed daily observations
- current observation is not part of the reference distribution
- `low` <= 33rd percentile
- `normal` > 33rd and < 67th percentile
- `high` >= 67th percentile

### Cycle metadata

- days since the latest historical BTC halving date
- descriptive age buckets only
- no cycle bucket is assigned directional decision power

Halving metadata remains context, not a hardcoded bullish/bearish rule.

## R1 — Long-history context coverage

Across the 3,312 completed daily observations:

| Trend context | High vol days | Low vol days | Normal vol days | Total |
| --- | ---: | ---: | ---: | ---: |
| Bear | 276 | 544 | 308 | 1,128 |
| Bull | 275 | 515 | 500 | 1,290 |
| Range / mixed | 138 | 357 | 399 | 894 |

The V0 classifier therefore spans all three trend states and all three volatility states rather than collapsing most history into one label.

Observed context transitions across the available daily history:
- trend-context changes: 121
- volatility-context changes: 168

These counts are descriptive only; no transition frequency is used as a trading rule.

## R1 — What context the original 30-day tactical sample actually contained

The P0.2B tactical window was not one homogeneous regime.

The long-history context join identifies:

- through 2026-08-19 UTC: predominantly `bear + low volatility`
- 2026-08-20 UTC: daily trend moved to `range_mixed` while volatility was still `low`
- from 2026-08-21 UTC: `range_mixed + normal volatility`

This matters because the original chronological calibration / holdout boundary coincided with that context shift.

Hourly P0.2B canonical sample composition:

### Calibration — 497 rows
- 428 rows / 86.1%: `bear + low`
- 24 rows / 4.8%: `range_mixed + low`
- 45 rows / 9.1%: `range_mixed + normal`

### Holdout — 217 rows
- 217 rows / 100%: `range_mixed + normal`

Therefore the earlier calibration-to-holdout change was partly confounded by a real higher-level market-context change. It should not be interpreted as a clean same-regime stability test.

## R2 — Higher-level context does not rescue current BTC Core by itself

The context shift is important, but it does **not** justify restoring BTC Core as a Risk-On / Risk-Off setter.

Restricting the existing canonical hourly Core replay to the same `range_mixed + normal` context on both sides of the chronological split still fails to produce stable directional performance.

| Horizon | Calibration n | Calibration accuracy | Holdout n | Holdout accuracy | Holdout mean signed return |
| --- | ---: | ---: | ---: | ---: | ---: |
| +1h | 45 | 53.3% | 120 | 47.5% | -0.048% |
| +2h | 45 | 51.1% | 120 | 51.7% | -0.068% |
| +4h | 45 | 57.8% | 120 | 49.2% | -0.094% |

The calibration slice is small, but the practical conclusion is clear: coarse context explains part of the phase change, yet the current Core formula still does not earn directional decision power inside the same coarse context.

## R2 — Current aggressive-flow inputs also remain weak after coarse context conditioning

The existing 30-day Spot and USD-M aggressive-flow datasets were rejoined to the same daily context using strict point-in-time semantics and a five-minute delayed-entry price convention.

Simple 60-minute aggressive-flow imbalance remains close to random for price direction within both dominant context states. Depending on horizon and market, AUC stays approximately in the 0.45–0.52 area rather than becoming a stable directional discriminator.

This reinforces PR #77:
- raw aggressive flow may remain useful as explanation / confirmation
- current Spot-vs-perp flow does not become a Directional Stance setter merely by adding the coarse daily context available in this one tactical month

The key limitation is sample coverage: only one 30-day microstructure window exists, so the same tactical feature has not yet been observed across multiple bull, bear, range, low-vol, and high-vol episodes.

## R2 — Long history confirms that pattern meaning can change with context

A simple, gap-safe 4H research example supports the hierarchical-context hypothesis.

Pattern definition for this diagnostic only:
- upside breakout: completed 4H close above the highest high of the prior 20 contiguous completed 4H bars
- downside breakout: completed 4H close below the lowest low of the prior 20 contiguous completed 4H bars
- evaluate the next 24H close-to-close return only when the future 4H path is contiguous

Selected results:

### Upside breakout

| Daily context | Volatility context | n | Probability next 24H is up | Mean next 24H return |
| --- | --- | ---: | ---: | ---: |
| Bull | Normal | 177 | 58.2% | +0.921% |
| Bull | Low | 178 | 53.9% | +0.792% |
| Bull | High | 106 | 55.7% | +0.376% |
| Bear | High | 61 | 37.7% | -0.971% |
| Bear | Normal | 96 | 51.0% | +0.527% |
| Bear | Low | 146 | 53.4% | +0.494% |

### Downside breakout

In a bull daily context, downside-breakout continuation is notably weak:
- bull + high vol: 30.2% downside continuation, n=43
- bull + normal vol: 40.7%, n=91
- bull + low vol: 47.6%, n=105

The same local pattern therefore does not have one stable meaning across higher-level contexts.

However, these interactions are **not** accepted trading rules. Year/era stability is mixed, and some context cells remain modest in size. Their role here is to demonstrate that hierarchical conditioning is empirically plausible and deserves broader tactical history.

## Opportunity V1 survives the context check

The already-frozen Opportunity V1 candidate was reconstructed on the existing 15-minute P0.2B tactical sample:
- trailing 60-minute BTC range
- percentile against the previous 14 days of the same 15-minute metric
- current observation excluded
- HIGH >= 75th percentile
- LOW <= 25th percentile
- NORMAL otherwise

The metric was evaluated against whether the future BTC path reaches at least +/-0.30% within the horizon.

### All available Opportunity observations

| State | n | >=0.30% move within +1h | within +2h | within +4h |
| --- | ---: | ---: | ---: | ---: |
| HIGH | 586 | 87.9% | 96.8% | 98.8% |
| NORMAL | 736 | 57.3% | 80.6% | 92.8% |
| LOW | 214 | 21.0% | 38.8% | 60.3% |

Continuous 14-day percentile discrimination:
- +1h AUC: 0.790
- +2h AUC: 0.818
- +4h AUC: 0.851

### Bear + low-vol context

| State | n | >=0.30% move within +1h | within +2h | within +4h |
| --- | ---: | ---: | ---: | ---: |
| HIGH | 102 | 64.7% | 85.3% | 94.1% |
| NORMAL | 182 | 24.2% | 54.9% | 83.0% |
| LOW | 84 | 15.5% | 29.8% | 53.6% |

AUC:
- +1h: 0.728
- +2h: 0.723
- +4h: 0.747

### Range/mixed + normal-vol context

| State | n | >=0.30% move within +1h | within +2h | within +4h |
| --- | ---: | ---: | ---: | ---: |
| HIGH | 391 | 92.1% | 99.0% | 99.7% |
| NORMAL | 551 | 68.1% | 88.9% | 96.0% |
| LOW | 130 | 24.6% | 44.6% | 64.6% |

AUC:
- +1h: 0.797
- +2h: 0.859
- +4h: 0.911

These probabilities are sample outcomes, not promised future probabilities. The important finding is the **ordering**: HIGH remains materially above NORMAL, and NORMAL above LOW, in both dominant higher-level contexts observed in the month.

This strengthens the product decision to ship Opportunity separately from direction.

## Decision

### Accepted

1. Keep the multilayer architecture.
2. Treat higher-level context as a conditional interpretation layer rather than an additive score.
3. Continue shipping Opportunity V1 independently; this pass strengthens rather than weakens its case.
4. Keep Directional Bias / Core descriptive until broader regime-conditioned tactical history exists.
5. Keep `Risk-On / Wait & See / Risk-Off` out of production synthesis for now.

### Rejected

1. Do not claim that the 30-day calibration/holdout alone proves a universal directional model failure; the split also crossed a real context change.
2. Do not claim that context has already rescued Directional Stance; same-context holdout remains weak.
3. Do not turn halving age into a deterministic direction rule.
4. Do not fit regime-specific weights from the current 30-day microstructure sample.

## Next data requirement

The current bottleneck is no longer long-cycle context. Layer A is sufficient for V0.

The bottleneck is **multi-year tactical history at manageable cost**.

Before considering multi-year raw aggTrades or order-book reconstruction, acquire compact 5-minute BTCUSDT kline history for:
- Binance Spot
- Binance USD-M perpetual

Preferred initial common window:
- 2020-01-01 UTC through latest completed data

Required fields where the source provides them:
- open / high / low / close
- base volume
- quote volume
- trade count
- taker-buy base volume
- taker-buy quote volume
- open time
- exclusive close / knowledge time
- provenance / archive checksum / gap audit

Why this is the next cheapest useful layer:
- 5-minute candles are far smaller than raw aggTrades
- they allow multi-year reconstruction of realized range / Opportunity-like activity, price structure, volume, and taker-buy imbalance
- the window covers multiple distinct BTC market regimes and both the 2020 and 2024 halving eras
- it lets us test whether tactical relationships actually change by regime before paying the operational cost of deeper microstructure history

No model fitting or backtest should be performed by the data supplier. Analysis remains local/canonical after export.

## Product implication

No launch change is required from this finding.

The current product contract remains:
- Opportunity: actionable activity / movement likelihood
- Directional Bias: current directional evidence, explicitly not a correctness probability
- Confidence: evidence consistency / strength
- Market State: structural context
- Primary Drivers: strongest material evidence
- Changed: material state change

Multilayer Directional Stance research continues in parallel and must not block Opportunity V1 integration or Founding Beta launch.