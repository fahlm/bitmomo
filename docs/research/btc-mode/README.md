# BTC Mode Research

Status: ACTIVE — INTRADAY CALIBRATION

This folder is the canonical research record for Bitmomo BTC Mode.

## Product question

BTC Mode compresses BTC market intelligence into three customer-facing states:

- Risk-On
- Wait & See
- Risk-Off

The intended use is short-horizon decision support for active BTC users/traders. It is not a long-cycle allocation model and not a direct buy/sell signal.

## Current empirical direction

The corrected P0.2B intraday replay shows that the existing BTC Core / Regime stack is more robust at describing **future movement intensity / volatility** than future price direction.

A 15-minute exploratory tactical layer built from existing 5-minute Binance data materially improves discrimination for whether a meaningful move is likely, but has not yet demonstrated robust directional edge.

Research therefore separates four layers:

1. **Opportunity / Activity** — is a meaningful tradable move likely soon?
2. **Directional Stance** — is upside or downside evidence strong enough, or should the system abstain?
3. **Context** — Market Regime and, later, horizon-appropriate Bond/Macro context
4. **BTC Mode** — one customer-facing compression layer that emits Risk-On/Risk-Off only when evidence aligns, otherwise Wait & See

See `RESULTS_V1.md` and ADR-004 for current evidence and rationale.

## Corrected V1 research scope

Primary calibration window remains the latest defensible **30 days of synchronized full-feature data**.

Current canonical-style replay:

- hourly PIT-safe feature snapshots for current slow/structural engine semantics
- primary outcome horizons: +15m, +30m, +1h, +2h, +4h, +6h
- MAE, MFE, realized volatility, time-to-excursion, and path ordering
- 08:10 / 20:10 `America/New_York` editions remain deep-context product checkpoints rather than the only statistical sample

Exploratory tactical research may use 15-minute snapshots only when the underlying data genuinely supports that cadence. This does not silently redefine the current production engine cadence.

## Candidate intelligence pillars

- **BTC Core / structural context** — current Direction, Structure, Carry, Crowding, Volatility, aggregate score and evidence-agreement Confidence
- **Market Regime** — Accumulation, Expansion, Distribution, Capitulation, Transition, certainty/evidence/conflicts
- **Opportunity / Activity** — faster BTC-native price/range/volatility/flow/derivatives activity research
- **Bond / Macro** — tested by horizon rather than forced into every intraday decision

The pillars remain semantically distinct. Distribution does not automatically mean Risk-Off; Accumulation does not automatically mean Risk-On. Existing Confidence is not a trade win probability.

## Canonical files

- `DATA_DICTIONARY.md` — research fields and lineage
- `METHODOLOGY.md` — accepted research method
- `EXECUTION_SPEC.md` — execution/acceptance contract
- `EXPERIMENT_LOG.md` — append-only experiment history
- `RESULTS_V1.md` — current empirical findings
- `LIMITATIONS.md` — known limitations and replay caveats

Production methodology remains separate in `docs/intelligence/BTC_MODE_V1.md` and must not be frozen until research acceptance is explicit.

## Non-negotiable rules

- strict point-in-time construction; no look-ahead
- no hidden substitution of missing inputs
- no arbitrary fitted weights from one short window
- overlapping intraday rows are dependent observations, not independent trials
- failed/rejected experiments remain documented
- no hard alias such as Distribution = Risk-Off or Accumulation = Risk-On
- no presentation of existing Confidence as probability a trade will win
- production methodology is immutable/versioned after freeze
- append-only forward validation becomes the long-run evidence base

## Current next step

Research selective **Directional Stance** rules using the corrected replay and existing public Binance inputs. Start with:

- Transition/conflict downgrade
- early versus mature Expansion
- price/OI/taker-flow divergence and material state changes
- explicit abstention

Only add another microstructure provider if existing data cannot support a defensible directional layer.

Bond Intelligence is evaluated after the BTC-native selective-direction study and by horizon rather than as a forced universal input.

## Platform status

P0.2A Session-Aware BTC Intelligence is merged to `main`. Production deployment remains unauthorized. The current `bond_context` extension point remains `null`; Bond is not yet integrated into WordPress/session generation.
