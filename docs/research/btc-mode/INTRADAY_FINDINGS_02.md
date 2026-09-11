# BTC Mode Intraday Findings 02

Status: RESEARCH — NOT A PRODUCTION FORMULA

Date: 2026-09-12

This note records the next empirical findings after the intraday correction merged in PR #75. No runtime rule is changed by this document.

## Research question

Separate two questions that were previously mixed together:

1. **Opportunity / Activity** — is the BTC market likely to produce a meaningful tradable excursion over the next minutes-to-hours?
2. **Directional Stance** — if a meaningful excursion occurs, is there defensible evidence that the first/favorable path is more likely upward or downward?

BTC Mode must not force a Risk-On/Risk-Off answer when only the first question is supported.

## Dataset and validation

Primary exploratory tactical dataset:

- 30-day window
- 15-minute evaluation cadence
- 2,880 tactical snapshots before quality exclusions
- chronological calibration / embargo / holdout split inherited from P0.2B
- forward path labels derived from 5-minute BTCUSDT candles
- no random train/test split

Canonical slower BTC Core / Market Regime context remains available as context, but is not assumed to be the best short-horizon directional engine.

## Finding A — recent realized range is a strong Opportunity signal

Define a meaningful short-horizon opportunity event as a forward absolute excursion of at least 0.30% from the evaluation price.

Using only trailing 60-minute realized BTC range, discrimination remained strong across the chronological holdout:

| Forward window | Calibration AUC | Holdout AUC |
| --- | ---: | ---: |
| 1 hour | 0.798 | 0.783 |
| 2 hours | 0.801 | 0.866 |
| 4 hours | 0.844 | 0.883 |

Adding more variables improved AUC only marginally relative to the simple range baseline. This is important: a simple transparent activity feature already captures most of the observed Opportunity signal.

### Rolling-percentile robustness

A point-in-time seven-day rolling percentile of trailing 60-minute range preserved the expected monotonic ordering: high recent activity was followed by materially more 0.30% excursions than low recent activity.

Absolute probabilities changed materially between calibration and holdout because the holdout period was more volatile. Therefore the rolling percentile is more defensible as a **relative Opportunity state** than as a fixed universal probability forecast.

### Research decision

A separate deterministic Opportunity/Activity layer is justified for further productization.

Candidate interpretation:

- low activity: opportunity weak
- normal activity: opportunity mixed
- high activity: opportunity elevated

Exact customer-facing labels and thresholds are not frozen here.

## Finding B — current Directional Stance does not generalize well enough

The existing short-horizon feature stack was tested against the direction of meaningful forward excursions.

The following were examined in combinations:

- recent 5m/15m/30m/60m returns
- recent price range and range position
- EMA alignment and short-horizon breakout / HH-HL / LH-LL style structure proxies
- 5-minute ADX / directional movement
- taker buy imbalance
- OI changes
- long/short ratios
- existing BTC Core score / confidence
- Market Regime candidate / confidence

Static calibration models looked meaningfully better than chance in-sample, but chronological holdout performance deteriorated or inverted. Selective high-confidence predictions did not rescue the problem.

Conclusion: the current feature stack does **not** justify a frozen intraday directional probability or a forced Risk-On/Risk-Off output.

## Finding C — apparent same-timestamp 5-minute derivatives edge is rejected

A very important leakage test was performed using Binance 5-minute futures metrics.

When a model was allowed to consume the derivatives record stamped at exactly the decision timestamp, apparent directional holdout AUC rose to roughly:

- 1 hour: ~0.69
- 2 hours: ~0.68
- 4 hours: ~0.67

That result initially looked promising.

However, Binance historical derivatives records are period-end observations. Treating the value stamped at the decision timestamp as if it had been safely known before the decision creates a point-in-time ambiguity.

Two conservative checks were therefore applied:

1. use only the most recent derivatives record strictly **before** the decision timestamp; and
2. alternatively, consume the timestamped record but delay simulated execution by one full 5-minute interval.

Under these stricter assumptions the apparent edge collapsed toward chance and failed chronological holdout.

### Research decision

The same-timestamp result is classified as **contemporaneous / leakage-sensitive, rejected for production evidence**.

Bitmomo must not claim or encode that edge unless a live data path proves the observation is available with defensible sub-period latency and a matching delayed-entry backtest retains the effect.

## Product architecture implication

BTC Mode research should now use a two-stage structure:

### Stage 1 — Opportunity / Activity

Answer:

> Is the market currently active enough that a meaningful short-horizon move is likely?

This layer has useful empirical support and can be recomputed frequently.

### Stage 2 — Directional Stance

Answer:

> If activity is elevated, is there sufficient evidence to prefer upside, downside, or abstention?

Current evidence supports **abstention as a first-class output**. `Wait & See` must be allowed to dominate when directional evidence is unstable.

Risk-On and Risk-Off must not be generated merely because Opportunity is high.

## Data implication for the next directional experiment

Before freezing Directional Stance, prioritize genuinely leading / near-real-time microstructure inputs whose knowledge time can be audited:

1. sub-5-minute aggressive trade flow / CVD from trades or aggTrades
2. order-book imbalance / depth change with explicit capture timestamps
3. liquidation / forced-order flow if a reliable source is available
4. 5-minute OI / top-trader / account-ratio data only when live availability latency is measured and replayed honestly

Do not add these inputs merely because they sound useful. Each new source must pass a delayed-entry point-in-time backtest before receiving decision power.

## Bond Intelligence implication

Bond / macro remains important but should not be forced into the 15m–1h Directional Stance.

Research priority:

- crypto-native microstructure for 15m–2h direction
- Market Regime as context / downgrade layer
- Bond / macro incremental test primarily for 4h–12h context and directional-risk asymmetry

## Current decision-power map

| Layer / input | 15m–1h | 2h–4h | 4h–12h | Current status |
| --- | --- | --- | --- | --- |
| Recent BTC activity/range | strong Opportunity input | strong Opportunity input | context | candidate decision input |
| Existing BTC Core | context only | context / conditional | context | not sufficient alone |
| Market Regime | downgrade/context | context | context | no hard alias to Mode |
| 5m derivatives same-timestamp | rejected | rejected | rejected | leakage-sensitive |
| Strict-lag 5m derivatives | weak in current test | weak in current test | unproven | no decision power |
| Bond / Macro | explanation only | exploratory | next incremental test | no decision power yet |

## Next step

1. Preserve Opportunity as a separate candidate engine.
2. Build the next Directional Stance experiment around auditable near-real-time microstructure rather than adding more weight to the existing slow Core.
3. Keep `Wait & See` as the default when Opportunity is high but direction is not empirically justified.
4. Test Bond increment only after the horizon-specific role is explicit.
5. Do not freeze `btc-mode-v1` yet.
