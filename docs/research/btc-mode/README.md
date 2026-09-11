# BTC Mode Research

Status: RESEARCH FOUNDATION

This folder is the canonical research record for Bitmomo BTC Mode.

## Product question

BTC Mode compresses the current BTC risk environment into exactly one of three customer-facing states:

- `risk_on`
- `wait_and_see`
- `risk_off`

The research objective is not to invent intuitive weights. It is to determine, using point-in-time historical evidence, whether and how the existing Bitmomo intelligence pillars improve the classification of BTC's forward risk environment.

## Candidate intelligence pillars

1. **BTC Core** — existing Bitmomo Signal Engine outputs: direction, structure, carry, crowding, volatility, aggregate directional score/strength, and canonical confidence.
2. **Market Regime** — existing Bitmomo Regime outputs: accumulation, expansion, distribution, capitulation, transition, regime certainty, evidence, and conflicts.
3. **Bond / Macro** — Bond Intelligence outputs: policy repricing, real-rate pressure, inflation repricing, curve state, bond volatility, provenance, maturity, and data quality.

The pillars must remain semantically distinct. Market Regime is not automatically equivalent to BTC Mode. Distribution does not automatically mean Risk-Off; accumulation does not automatically mean Risk-On. Bond context may confirm, conflict with, or add no useful information to BTC Core and Regime.

## Required outcome horizons

For each point-in-time observation, evaluate BTC outcomes at:

- +6h
- +24h
- +72h
- +7d

Also evaluate path-dependent risk where data permits:

- maximum adverse excursion (MAE)
- maximum favorable excursion (MFE)
- realized volatility

BTC Mode must therefore be evaluated as a risk-environment classification, not merely a next-period up/down predictor.

## Research sequence

Compare progressively more informative models:

- **A — Direction only**
- **B — BTC Core**
- **C — BTC Core + Market Regime**
- **D — BTC Core + Market Regime + Bond**

Do not promote a more complex model unless it adds meaningful out-of-sample information or risk discrimination.

## Non-negotiable rules

- Strict point-in-time construction; no look-ahead.
- Use closed/available observations only.
- Preserve source timestamps and knowledge-time semantics.
- Do not fabricate historical intraday availability.
- Do not tune directly on the final holdout period.
- Failed experiments are retained in `EXPERIMENT_LOG.md`.
- No production BTC Mode formula is frozen until research is reviewed and accepted.
- Any accepted production methodology gets an immutable version such as `btc-mode-v1`; later methodology changes require a new version.

## Current platform status

P0.2A Session-Aware BTC Intelligence was merged to `main` via PR #72. Production deployment has not been authorized. The existing `bond_context` extension point remains `null`; Bond is not yet integrated into WordPress/session generation.
