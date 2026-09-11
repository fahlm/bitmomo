# BTC Mode Research

Status: RESEARCH FOUNDATION / EXECUTION-READY

This folder is the canonical research record for Bitmomo BTC Mode.

## Product question

BTC Mode compresses the current BTC risk environment into exactly one of three customer-facing states:

- `risk_on`
- `wait_and_see`
- `risk_off`

The intended use is short-horizon decision support for active BTC users/traders. It is not a long-cycle allocation model and not a direct buy/sell signal.

## V1 research scope

Primary calibration window: the latest defensible **30 days of synchronized full-feature data**.

This is intentional. The V1 product is session-aware and short horizon; a shorter window with complete BTC derivatives + regime + macro context is preferred over a much longer window that silently drops the inputs actually used by the live engine.

Two observation sets are required:

1. **Production-aligned sample** — observations at the canonical 08:10 and 20:10 `America/New_York` anchors, approximately 60 observations over 30 days.
2. **Research robustness sample** — replay every 4 hours using only closed/known data, approximately 180 observations over 30 days. This sample is for relationship robustness only; it does not change the two-edition production cadence.

## Candidate intelligence pillars

1. **BTC Core** — existing Bitmomo Signal Engine outputs: direction, structure, carry, crowding, volatility, aggregate directional score/strength, and canonical confidence.
2. **Market Regime** — existing Bitmomo Regime outputs: accumulation, expansion, distribution, capitulation, transition, regime certainty, evidence, and conflicts.
3. **Bond / Macro** — Bond Intelligence outputs: policy repricing, real-rate pressure, inflation repricing, curve state, bond volatility, provenance, maturity, and data quality.

The pillars remain semantically distinct. Distribution does not automatically mean Risk-Off; accumulation does not automatically mean Risk-On. Bond may confirm, conflict with, or add no useful decision information.

## Outcome horizons

Primary horizons:

- +6h
- +12h — especially important because it approximately spans one Bitmomo edition to the next
- +24h

Secondary persistence check:

- +72h

For every defensible horizon also evaluate:

- maximum adverse excursion (MAE)
- maximum favorable excursion (MFE)
- realized volatility

BTC Mode is therefore evaluated as a short-horizon risk-environment classification, not merely a next-period up/down predictor.

## Research sequence

Compare progressively richer models:

- **A — Direction only**
- **B — BTC Core**
- **C — BTC Core + Market Regime**
- **D — BTC Core + Market Regime + Bond**

Do not promote a more complex model unless it adds meaningful information or risk discrimination.

## V1 calibration principle

Do not fit precise-looking weights from a small sample. Use the 30-day sample primarily to discover robust conditional relationships, confirmation/conflict behavior, and downgrade/veto candidates.

Example research question: does a bullish BTC Core retain favorable +12h/+24h return and MAE distribution when Market Regime is Distribution and real-rate pressure is Rising?

The desired production result is an explainable deterministic rule, not an overfit numerical score.

## Non-negotiable rules

- Strict point-in-time construction; no look-ahead.
- Use closed/available observations only.
- Preserve source timestamps and knowledge-time semantics.
- Do not fabricate historical intraday availability.
- Report sample counts for every conditional result.
- Overlapping outcomes are dependent observations and must not be presented as independent evidence.
- Failed experiments are retained in `EXPERIMENT_LOG.md`.
- No production BTC Mode formula is frozen until research is reviewed and accepted.
- Any accepted production methodology gets an immutable version such as `btc-mode-v1`; later semantic changes require a new version.
- After launch, append-only forward validation becomes the primary evidence base and grows twice daily.

## Current platform status

P0.2A Session-Aware BTC Intelligence was merged to `main` via PR #72. Production deployment has not been authorized. The existing `bond_context` extension point remains `null`; Bond is not yet integrated into WordPress/session generation.
