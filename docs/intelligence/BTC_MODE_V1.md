# BTC Mode V1 — Canonical Intelligence Specification

Status: RESEARCH / NOT FROZEN

## Purpose

BTC Mode is Bitmomo's top-level compression of the current BTC risk environment into one of three states:

- `risk_on`
- `wait_and_see`
- `risk_off`

Customer-facing labels:

- **RISK-ON**
- **WAIT & SEE**
- **RISK-OFF**

BTC Mode is a market-intelligence classification. It is not a direct buy/sell signal, allocation recommendation, or guaranteed price-direction forecast.

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

## Primary Drivers terminology

Customer-facing explanatory label: **PRIMARY DRIVERS**.

The existing deterministic key-driver logic should be reused where applicable rather than creating a separate LLM explanation layer. BTC Mode-specific macro/regime drivers must be traceable to canonical evidence and must not fabricate causality.

## Research gate before freeze

No exact weighting, veto, confirmation, or mapping rule is frozen in this document yet.

Before changing status to FROZEN / ACCEPTED, `docs/research/btc-mode/RESULTS_V1.md` must document:

- point-in-time dataset audit
- baseline comparisons A/B/C/D
- conditional analysis
- walk-forward/out-of-sample results
- downside/upside path-risk results
- state-distribution sanity check
- accepted and rejected decision rules
- known limitations

## Versioning

When accepted, the methodology identifier will be `btc-mode-v1`.

After launch, V1 historical records are immutable. Any methodology change that can alter classification semantics requires a new version such as `btc-mode-v2`; historical V1 outputs must not be silently rewritten.

## Current implementation status

- P0.2A Session-Aware BTC Intelligence: merged to `main` via PR #72.
- Production deployment of P0.2A: not authorized / not performed as part of this documentation work.
- Bond extension point: available as `bond_context = null` in P0.2A; no Bond API integration yet.
- BTC Mode production engine: not implemented.
- BTC Mode production UI: not implemented.
