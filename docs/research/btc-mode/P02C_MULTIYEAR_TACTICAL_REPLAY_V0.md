# P0.2C — Multi-year Tactical Replay V0

Status: EMPIRICAL FINDING / RESEARCH ONLY

Tracks: Issue #78

## Objective

Test whether the tactical directional signals that were weak or unstable in the original 30-day P0.2B replay become materially more stable when evaluated across multiple BTC market regimes and conditioned on higher-level context.

This replay also stress-tests the already-frozen `opportunity-v1` semantics across a much longer sample.

## Source data

Research window: 2020-01-01 through 2026-09-10 UTC.

Sources:

- Binance BTCUSDT Spot 5-minute klines: 703,698 rows
- Binance BTCUSDT USD-M perpetual 5-minute klines: 704,160 rows
- Spot history contains 462 missing 5-minute intervals identified in the source manifest
- USD-M history is complete over the requested window
- source `knowledge_time` is the closed candle boundary; only data with `knowledge_time <= decision cutoff` is used

No missing observations are silently filled as neutral.

## Fixed context definitions

Context definitions were fixed before evaluating directional outcomes.

### Trend context

Derived from fully closed daily Spot candles:

- `bull`: daily close > EMA50 > EMA200
- `bear`: daily close < EMA50 < EMA200
- `range`: all other valid EMA states

### Volatility context

- daily log-return realized volatility over 20 closed daily observations
- compare current realized volatility with the prior 180 valid daily observations
- `low`: at or below the prior-window 33rd percentile
- `normal`: between the 33rd and 67th percentiles
- `high`: at or above the prior-window 67th percentile

### Cycle metadata

Cycle metadata remains descriptive only.

Using known historical Bitcoin halving dates, days since the most recent halving are mapped as:

- `early`: 0–365 days
- `mid`: 366–730 days
- `late`: >730 days

Cycle phase is not a bullish/bearish rule and is not granted decision power in this replay.

## Tactical inputs

The replay uses simple PIT-safe features derived from closed 5-minute Spot and USD-M candles:

- 15m / 30m / 60m / 120m price returns
- Spot and USD-M taker-buy imbalance
- perpetual-vs-Spot relative return
- taker-imbalance divergence
- perpetual premium/basis change
- a simple 4-hour breakout state against the prior closed 4-hour price range

No new fitted technical indicator or hand-tuned composite score is introduced.

## Opportunity V1 multi-year validation

Opportunity remains a separate activity question, not a direction forecast.

For the replay, the canonical V1 calculation is applied every 15 minutes using the trailing 60-minute range from 12 fully closed 5-minute USD-M BTCUSDT candles and the immediately preceding 14 calendar days of 15-minute Opportunity observations as the adaptive reference window.

State mapping remains:

- `HIGH`: >= 75th percentile
- `NORMAL`: 25th–75th percentile
- `LOW`: <= 25th percentile

Validation event: whether absolute BTC excursion reaches at least 0.30% within the stated future horizon.

### Full-sample event rates

| Opportunity | +15m | +30m | +1h | +2h |
|---|---:|---:|---:|---:|
| HIGH | 61.5% | 78.4% | 90.1% | 96.3% |
| NORMAL | 33.2% | 53.6% | 73.4% | 88.2% |
| LOW | 18.7% | 35.0% | 54.8% | 73.6% |

Absolute event rates vary substantially across years, which is expected. The important result is ranking stability.

### Calendar-year stability

`HIGH > NORMAL > LOW` holds in every calendar year represented in the sample (2020 through 2026) for all four tested horizons: +15m, +30m, +1h, and +2h.

For the +1h >=0.30% excursion target:

| Year | HIGH | NORMAL | LOW |
|---|---:|---:|---:|
| 2020 | 90.9% | 79.4% | 65.0% |
| 2021 | 98.9% | 96.5% | 93.1% |
| 2022 | 94.2% | 80.2% | 62.8% |
| 2023 | 79.9% | 53.9% | 29.9% |
| 2024 | 93.6% | 75.4% | 50.8% |
| 2025 | 86.6% | 62.8% | 36.2% |
| 2026 YTD | 85.8% | 63.3% | 38.5% |

The state is therefore useful as a relative activity ranking, not as a fixed probability forecast.

### Context stability

Across all nine sufficiently populated `trend_context × volatility_context` combinations, `HIGH > NORMAL > LOW` remains ordered at +15m, +30m, +1h, and +2h.

For the +1h target, examples include:

| Trend | Volatility | HIGH | NORMAL | LOW |
|---|---|---:|---:|---:|
| bear | high | 98.1% | 86.7% | 65.4% |
| bear | low | 82.8% | 62.7% | 41.6% |
| bull | high | 95.9% | 83.9% | 67.6% |
| bull | low | 84.2% | 62.8% | 42.1% |
| range | normal | 90.3% | 72.5% | 52.4% |

The same ordering is also visible across descriptive early/mid/late halving-phase metadata, but those phases are not independent market cycles and must not be treated as causal evidence.

## Spot vs USD-M Opportunity source sensitivity

Where both Spot and USD-M can form a strict 14-day V1 baseline, the resulting HIGH/NORMAL/LOW state agrees approximately 96.2% of the time.

However:

- USD-M has a complete 5-minute source history in this bundle
- strict Spot integrity rules make Opportunity unavailable for 19,424 15-minute observations because historical Spot gaps contaminate trailing/reference windows
- USD-M Opportunity is unavailable for only the expected initial warm-up period (1,347 observations)

Decision: use Binance BTCUSDT USD-M perpetual 5-minute klines as the canonical `opportunity-v1` market source. Spot remains a sensitivity/reference source, not a silent runtime fallback for the same methodology version.

## Directional findings

### Simple features contain only a small short-horizon mean-reversion tendency

The most repeatable simple effect is contrarian rather than trend-following: recent positive price momentum and aggressive buying are followed by slightly lower near-term positive-return probability, and vice versa.

For example, the inverse AUC of the prior 30-minute Spot return by calendar year averages approximately:

- +15m: 0.542
- +30m: 0.539
- +1h: 0.532
- +2h: 0.527

The effect remains on the same side of 0.50 in every calendar year, but magnitude is modest.

A simple 4-hour breakout is not a reliable short-horizon continuation rule in this dataset. Continuation accuracy is below 50% at +15m through +2h overall and across the major trend/volatility cells.

### Walk-forward multivariate diagnostic

A deliberately small logistic diagnostic was evaluated on hourly cutoffs with expanding chronological training windows and a 2-hour purge before each next-year validation segment.

Base tactical inputs include recent returns, Spot/USD-M taker imbalance, Spot/perp divergence, basis change, and the simple breakout state.

Mean validation AUC across 2021–2026:

| Horizon | Tactical only | Tactical + trend/vol context interactions |
|---|---:|---:|
| +15m | 0.545 | 0.542 |
| +30m | 0.543 | 0.540 |
| +1h | 0.537 | 0.530 |
| +2h | 0.535 | 0.529 |

Adding the coarse trend/volatility interaction layer does not improve the out-of-sample directional diagnostic; it slightly reduces mean AUC.

A lower-dependence sensitivity using a 2-hour decision grid produces tactical-only mean AUC of approximately 0.548 / 0.547 / 0.541 / 0.536 for +15m / +30m / +1h / +2h, so the modest result is not solely an artifact of densely overlapping 15-minute observations.

### Selective prediction remains economically thin

Using only walk-forward predictions with model probability >=0.55 or <=0.45:

| Horizon | Approx. coverage | Directional hit rate | Mean gross signed return |
|---|---:|---:|---:|
| +15m | 32.3% | 56.2% | 0.43 bps |
| +30m | 27.5% | 55.9% | 0.69 bps |
| +1h | 24.6% | 54.8% | 0.92 bps |
| +2h | 24.0% | 54.7% | 2.38 bps |

The mean edge becomes negative after a 2 bps friction assumption at +15m, +30m, and +1h. The +2h slice retains only about +0.38 bps after 2 bps friction and becomes negative under a 5 bps assumption.

Filtering these predictions to `Opportunity = HIGH` does not rescue economics; the mean gross signed return is near zero or negative at the shorter horizons.

This is not sufficient evidence for customer-facing Risk-On/Risk-Off decision power.

## What the multilayer hypothesis did and did not prove

### Supported

1. Higher-level market environment changes materially through time, so a 30-day directional result must not be assumed universal.
2. The meaning of tactical patterns can differ descriptively across context.
3. Opportunity is unusually robust because it ranks activity rather than forcing a direction forecast.
4. Long-horizon context is useful for explanation, conflict detection, research slicing, and future forward validation.

### Not supported

1. Coarse trend/volatility context does not currently turn the existing tactical feature set into a strong directional engine.
2. Halving/cycle metadata does not earn directional decision power.
3. More context dimensions should not be added merely to search for a fitted edge.
4. Current short-horizon direction evidence does not justify a synthesized production Risk-On/Risk-Off verdict.

## Product-role decisions

- `Opportunity V1`: **decision_power for activity only**
- daily trend context: `context_only` / explanation
- volatility context: `context_only` / explanation
- cycle metadata: `context_only`
- recent momentum mean reversion: `research_candidate`, not production decision power
- Spot/perp taker flow and divergence: `confirmation` / `primary_driver_explanation` at most
- simple short-horizon breakout continuation: `rejected` as a standalone directional rule
- synthesized Risk-On/Risk-Off: **withheld**

## CTO decision

P0.2C answers its central research question: regime-conditioning is conceptually valid and useful for understanding non-stationarity, but the current coarse context layer does **not** materially improve Directional Stance enough to delay launch.

Do not spend the launch path adding more historical indicators or more regime combinations solely to force directional accuracy.

Proceed with Opportunity V1 product integration under Issue #80 while Directional Stance remains a separate forward-shadow research track.

Any future directional promotion must be earned on append-only unseen data, not by further mining this 2020–2026 replay.
