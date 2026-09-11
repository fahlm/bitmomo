# BTC Mode Research — Spot vs Perpetual Flow Findings V0

Status: RESEARCH FINDING — NOT PRODUCTION METHODOLOGY

## Objective

Test whether Binance Spot BTCUSDT aggressive flow adds stable short-horizon directional information beyond Binance USD-M perpetual aggressive flow, with strict point-in-time construction and a five-minute delayed-entry assumption.

## Data

Window: 2026-08-02 00:00 UTC through 2026-09-01 00:00 UTC.

Sources:
- Binance Spot BTCUSDT aggTrades public daily archive: 23,673,154 aggTrades.
- Binance USD-M Futures BTCUSDT aggTrades public daily archive: 35,277,102 aggTrades.

Both exports are complete at 1-minute and 5-minute resolution with no missing buckets. `knowledge_time` is the bucket end; no bucket is treated as known before completion.

Primary replay sample: approximately 2,850 15-minute cutoffs after endpoint exclusions. Features use only completed buckets at or before the cutoff. Entry is delayed five minutes. Primary directional horizons: +15m, +30m, +1h, +2h.

Validation: chronological calibration/holdout with a two-hour purge. No random split.

## Features tested

Per market:
- aggressive buy/sell imbalance over 5m / 15m / 30m / 60m
- signed delta / CVD change
- trade and quantity intensity
- short-horizon price momentum

Cross-market:
- Spot minus perpetual flow imbalance
- agreement/disagreement between Spot and perpetual flow
- Spot/perpetual price basis and basis change
- simple Spot-lead / perp-lead interaction terms

Simple linear and shallow non-linear models were checked. No broad hyperparameter search was used.

## Walk-forward directional discrimination

Mean AUC across four expanding chronological folds:

| Feature set | +15m | +30m | +1h | +2h |
| --- | ---: | ---: | ---: | ---: |
| Perpetual flow only | 0.554 | 0.556 | 0.551 | 0.523 |
| Spot flow only | 0.511 | 0.522 | 0.524 | 0.540 |
| Spot + perpetual flow | 0.528 | 0.550 | 0.532 | 0.549 |
| Cross-market / divergence features | 0.521 | 0.541 | 0.523 | 0.530 |

Interpretation: the strongest repeatable signal remains modest and mostly resides in perpetual flow. Adding Spot flow does not produce a stable material lift. Cross-market divergence does not create a robust directional edge.

## Intuitive Spot-vs-perp leadership rule

The intuitive rule was tested directly:

- Spot buying while perpetual flow sells => bullish
- Spot selling while perpetual flow buys => bearish

Across multiple 5m/15m/30m/60m aggregation windows, directional accuracy remains near coin-flip and changes sign across calibration/holdout. This rule is rejected as a production Directional Stance setter.

## Selective / abstaining models

Because BTC Mode can abstain, tail-only decisions were also checked.

In the final 30% chronological holdout, a perpetual-flow model at +15m reached roughly 58.8% directional accuracy on about 36% coverage. However, gross aligned mean return was only about +0.0063% (~0.63 bps) per selected observation — economically too thin once realistic spread, fee and slippage are considered.

A Spot-flow model at +1h reached about 57.1% accuracy on roughly 32% coverage with gross aligned mean return around +0.058% (~5.8 bps), but the effect weakened materially inside the high-Opportunity subset and is not stable enough to justify production decision power. At 5–10 bps round-trip friction, the apparent gross edge is small to negative.

These tail findings are therefore exploratory, not sufficient for `btc-mode-v1`.

## Opportunity-conditioned direction

Directional flow performance was rechecked only when trailing 60-minute BTC range was elevated. The directional edge did not materially strengthen; in several variants it weakened.

This reinforces the current two-stage architecture:

1. Opportunity / Activity can identify when a material move is likely.
2. Directional Stance requires independent evidence; high Opportunity must not be converted into Risk-On/Risk-Off by flow alone.

## First-excursion direction

Spot/perpetual flow was also tested against which side reached +/-0.2% or +/-0.3% first within the next one to two hours.

Results remain weak. Typical holdout AUCs were around 0.50–0.55, including inside elevated-Opportunity subsets. No stable first-excursion rule was found.

## Conclusion

Spot-vs-perpetual aggressive-flow leadership does **not** solve the Directional Stance problem.

Accepted uses:
- context / confirmation
- Primary Drivers narrative
- possible minor confidence modifier after future forward validation

Rejected uses for V1:
- direct Risk-On/Risk-Off setter
- hard directional veto
- precise fitted weight
- standalone trade-direction signal

The current evidence still supports aggressive flow as context rather than directional authority.

## Product implication

`Wait & See` remains a first-class state. The product must be allowed to say that an opportunity is high while directional evidence is insufficient.

This is preferable to forcing a low-quality directional call merely because market activity is high.

## Recommended next step

Do not spend more launch time on additional Spot/perpetual flow permutations. The incremental value is now sufficiently bounded.

For P0.2B, either:

1. test one genuinely different directional information class (for example point-in-time order-book pressure / liquidation stress if defensible historical data can be obtained cheaply), or
2. freeze V1 conservatively with Opportunity as the proven fast layer and Directional Bias/Regime/Bond as contextual layers, with a high abstention rate, then let append-only forward validation determine whether a richer directional layer earns promotion.

No production methodology is changed by this result.
