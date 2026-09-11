# BTC Mode Research — Directional Stance Gate V0

Status: RESEARCH GATE — NOT PRODUCTION METHODOLOGY

## Question

After aggressive-flow and Spot-vs-perpetual flow failed to earn directional decision power, can a conservative rule based on the existing canonical BTC Core become reliable enough if it only fires when evidence is strong, Opportunity is elevated, and Market Regime is not Transition?

## Test design

Canonical hourly replay with strict five-minute delayed entry.

Candidate gates tested:
- absolute BTC Core score >= 20 / 40 / 60
- optional exclusion of `Transition`
- optional Opportunity filter using trailing 60-minute BTC range >= calibration median or >= calibration 75th percentile
- +1h delayed-entry directional outcome

Chronological 70/30 split with a two-hour purge.

## Result

The apparent calibration improvement does not generalize.

Examples:

- Core >= 40 + high Opportunity (top calibration quartile): calibration directional accuracy ~57.3%, holdout ~47.1%.
- Core >= 40 + non-Transition + high Opportunity: calibration ~57.6%, holdout ~47.8%.
- Core >= 20 + non-Transition + Opportunity above median: calibration ~53.2%, holdout ~52.9%, but aligned holdout return is economically negligible.
- Core >= 60 produces very low holdout coverage; results are unstable and sample size is too small to justify decision power.

Increasing selectivity improves the in-sample appearance but does not create stable out-of-sample directionality.

## Decision

The existing BTC Core must **not** be promoted into a Risk-On/Risk-Off setter merely by adding:
- stronger score thresholds,
- high-Opportunity gating,
- or a non-Transition regime filter.

Those gates are reasonable product heuristics but are not empirically sufficient to freeze a directional `btc-mode-v1`.

## Implication

The current evidence supports one strong fast layer:

- **Opportunity / Activity** — useful for estimating whether meaningful movement is likely.

It does not yet support a comparably strong fast directional layer.

Therefore any production V1 should keep Opportunity separate from Directional Bias and must preserve abstention rather than synthesizing a confident Risk-On/Risk-Off call from weak directionality.

This finding does not change runtime code or current production methodology.
