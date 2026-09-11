# ADR-004 — BTC Mode intraday research uses separate opportunity and direction layers

Status: PROVISIONAL / ACCEPTED FOR RESEARCH
Date: 2026-09-12

## Context

The corrected 30-day intraday replay reconstructed 720 hourly canonical-style BTC Core observations and 2,880 exploratory 15-minute tactical observations.

The current BTC Core / Direction / Regime stack showed useful information about future movement magnitude and volatility, but directional ordering was not stable enough across the chronological market-state shift to justify a direct `Bullish -> Risk-On` or `Bearish -> Risk-Off` mapping.

A tactical layer built from existing 5-minute BTC price/range, volume/taker flow and short-horizon derivatives activity also showed materially stronger discrimination for **whether a meaningful move would occur** than for **which direction it would take**.

## Decision

Research and product architecture will separate two internal questions:

1. **Opportunity / Activity** — Is a meaningful tradable BTC move likely soon?
2. **Directional Stance** — Is there enough evidence to prefer upside or downside, or should the system abstain?

Market Regime remains a phase/context modifier. Bond/Macro will be evaluated only at horizons where incremental value is economically plausible rather than being forced into every intraday decision.

BTC Mode remains the single customer-facing compression layer:

- Risk-On only when opportunity and directional evidence align sufficiently
- Risk-Off only when hostile/downside evidence aligns sufficiently
- Wait & See when direction is mixed, late, transitional, or unsupported even if movement probability is high

## Evidence

Corrected replay findings are recorded in `docs/research/btc-mode/RESULTS_V1.md`.

Notable observations include:

- Transition regime behaved as a strong conflict/abstention context in the observed sample.
- Multi-timeframe alignment could be late for intraday continuation; the existing Confidence value is evidence-agreement confidence, not a calibrated win probability.
- Existing BTC-native inputs were substantially more stable for future movement/volatility discrimination than for return sign.
- Exploratory tactical models showed holdout AUC around 0.75 / 0.79 / 0.84 for detecting a >=0.3% excursion within 30m / 1h / 2h, while directional AUC remained close to chance.
- A simple recent-range feature was strongly explanatory: the highest calibration quartile of trailing 60-minute range had a >=0.3% following-hour move about 77% in calibration and 86% in the later period, versus about 13% and 19% in the lowest quartile.

## Consequences

- Do not freeze a single weighted BTC Mode score yet.
- Do not present current Confidence as probability of a profitable trade.
- Do not hard-map Distribution to Risk-Off or Accumulation to Risk-On.
- `Wait & See` is a first-class state, not an error/fallback.
- A faster tactical computation layer may be justified without requiring the public session report to update every interval; customer-facing updates should emphasize material state changes.
- The 08:10/20:10 New York session editions remain deep-context checkpoints.
- Existing Binance data is sufficient to continue Opportunity/Activity research before adding another provider.
- Additional microstructure data should be added only if selective Directional Stance research cannot obtain robust evidence from existing data.

## Production status

No production formula, runtime change, staging change, or deployment is authorized by this ADR.
