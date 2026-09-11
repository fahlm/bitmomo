# BTC Mode V1 — Canonical Intelligence Specification

Status: RESEARCH / NOT FROZEN

## Purpose

BTC Mode is Bitmomo's top-level compression of the **current short-horizon BTC risk environment** into one of three states:

- `risk_on`
- `wait_and_see`
- `risk_off`

Customer-facing labels:

- **RISK-ON**
- **WAIT & SEE**
- **RISK-OFF**

BTC Mode is a market-intelligence classification for active BTC decision support. It is not a direct buy/sell signal, allocation recommendation, or guaranteed price-direction forecast.

## Intended horizon

V1 is designed primarily around the next few hours to one day, aligned with Bitmomo's twice-daily session intelligence.

Primary research outcomes:

- +6h
- +12h
- +24h

Secondary persistence check:

- +72h

The +12h horizon is especially relevant because it approximately spans one canonical Bitmomo edition to the next.

## Relationship to existing Bitmomo intelligence

BTC Mode sits above, and must not erase, the existing canonical concepts:

- **Directional Bias / Strength** — immediate directional tendency from BTC Core.
- **Confidence** — strength/completeness of evidence behind the canonical BTC analysis; not probability of being correct.
- **Market State** — accumulation / expansion / distribution / capitulation / transition; structural regime context, not a synonym for BTC Mode.
- **Primary Drivers** — ranked material evidence explaining the current assessment.
- **Bond / Macro context** — external rates/liquidity evidence that may confirm, conflict with, or add no decision value depending on empirical research.

Examples of non-equivalence that must remain possible:

- Distribution does not automatically force Risk-Off.
- Accumulation does not automatically force Risk-On.
- Bullish direction with conflicting macro/regime evidence may remain Wait & See.
- A strong directional state with weak/insufficient data quality may fail closed or downgrade according to the frozen V1 rule.

## Session-aware behavior

BTC Mode will be generated within Bitmomo's canonical session-aware intelligence framework:

- 08:10 `America/New_York` — `us_pre_open`
- 20:10 `America/New_York` — `us_post_close`

Both editions continue on weekends and US holidays because BTC trades 24/7. US market calendar status is contextual metadata, not a scheduling kill switch.

A canonical session record should eventually support:

- current BTC Mode
- previous comparable BTC Mode
- whether the mode changed
- comparison timestamp/session lineage
- directional bias/strength
- confidence
- Market State/certainty
- Primary Drivers
- pillar/confirmation states used by the frozen methodology
- data quality/freshness
- source lineage
- `mode_version`

## Research basis for V1

Initial launch calibration uses the latest defensible **30-day synchronized full-feature window**.

Two datasets are used:

- production-aligned 08:10/20:10 observations for direct product relevance
- 4-hour robustness observations to test whether conditional relationships persist beyond publication anchors

The robustness cadence is research-only and does not change production cadence.

Because the synchronized sample is intentionally small, V1 research must not fit precise-looking weights merely to maximize in-sample performance. Prefer simple deterministic confirmation/conflict/downgrade rules supported by outcome distributions and path-risk evidence.

## Primary Drivers terminology

Customer-facing explanatory label: **PRIMARY DRIVERS**.

The existing deterministic key-driver logic should be reused where applicable rather than creating a separate LLM explanation layer. BTC Mode-specific macro/regime drivers must be traceable to canonical evidence and must not fabricate causality.

## Research gate before freeze

No exact weighting, veto, confirmation, or mapping rule is frozen in this document yet.

Before changing status to FROZEN / ACCEPTED, `docs/research/btc-mode/RESULTS_V1.md` must document:

- point-in-time dataset audit
- actual 30-day coverage and exclusions
- baseline comparisons A/B/C/D
- conditional analysis
- production-aligned versus 4-hour robustness results
- +6h/+12h/+24h risk-return distributions and +72h persistence
- MAE/MFE/realized-volatility behavior
- chronological holdout/sensitivity checks
- state-distribution sanity check
- accepted and rejected decision rules
- known limitations

## Forward validation

Once V1 is frozen and launched, every official session classification and subsequent outcome must be stored append-only. Historical V1 records are immutable. Twice-daily forward observations become Bitmomo's main long-run validation dataset and accountability surface.

## Versioning

When accepted, the methodology identifier will be `btc-mode-v1`.

After launch, V1 historical records are immutable. Any methodology change that can alter classification semantics requires a new version such as `btc-mode-v2`; historical V1 outputs must not be silently rewritten.

## Current implementation status

- P0.2A Session-Aware BTC Intelligence: merged to `main` via PR #72.
- Production deployment of P0.2A: not authorized / not performed as part of this documentation work.
- Bond extension point: available as `bond_context = null` in P0.2A; no Bond API integration yet.
- BTC Mode production engine: not implemented.
- BTC Mode production UI: not implemented.
