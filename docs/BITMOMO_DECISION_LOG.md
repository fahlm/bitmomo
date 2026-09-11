# Bitmomo Decision Log

Purpose: a lightweight chronological record of important product, research, intelligence, architecture, and release decisions. This complements formal specifications and research reports; it does not replace them.

## 2026-09-11 — BTC Mode terminology accepted for research

**Decision**

BTC Mode will use three customer-facing states:

- Risk-On
- Wait & See
- Risk-Off

**Rationale**

The terms are familiar to the target crypto audience and compress a complex market assessment with minimal learning friction. The three-state design preserves ambiguity instead of forcing every observation into a binary Risk-On/Risk-Off call.

**Status**

Accepted as product vocabulary. Production formula not yet frozen.

---

## 2026-09-11 — Primary Drivers terminology

**Decision**

Use **Primary Drivers** as the customer-facing label for the strongest material evidence behind the current assessment.

**Rationale**

Professional market-research terminology, concise, and less causally absolute than a generic "Why" label.

**Status**

Accepted.

---

## 2026-09-11 — BTC Mode must be empirically calibrated

**Decision**

Do not assign intuitive fixed weights to BTC Core, Market Regime, or Bond Intelligence before research. Build a point-in-time historical dataset, evaluate forward BTC outcomes, run conditional analysis, compare simpler and richer baselines, and validate chronologically before freezing `btc-mode-v1`.

**Status**

Accepted. This is the active next milestone.

---

## 2026-09-11 — BTC Mode research model sequence

**Decision**

Research must compare:

- A: Direction only
- B: BTC Core
- C: BTC Core + Market Regime
- D: BTC Core + Market Regime + Bond Intelligence

A more complex model is adopted only if it provides meaningful incremental value, especially for downside-risk discrimination and stability.

**Status**

Accepted.

---

## 2026-09-11 — BTC Mode V1 uses a 30-day synchronized full-feature calibration window

**Decision**

For launch calibration, prioritize the latest defensible 30 days containing the full intended short-horizon feature stack rather than a multi-year dataset that omits derivatives fields used by the live engine.

The original P0.2B execution used two samples:

- production-aligned observations at 08:10 and 20:10 `America/New_York`
- 4-hour robustness observations

and emphasized +6h/+12h/+24h outcomes with +72h as persistence.

**Status**

The 30-day full-feature window remains accepted. The original observation cadence/outcome emphasis is **superseded** by the 2026-09-12 intraday research correction below.

---

## 2026-09-11 — Forward validation becomes the long-run evidence base

**Decision**

After `btc-mode-v1` is frozen and launched, every official output and subsequent outcome must be stored append-only. V1 historical records are immutable. This forward dataset becomes the primary long-run validation asset.

**Status**

Accepted. The exact Mode evaluation cadence is to be determined by intraday research; the 08:10/20:10 editions remain deep-context checkpoints.

---

## 2026-09-11 — Market State is related to, but not identical to, BTC Mode

**Decision**

Market Regime is a contextual pillar, not a hard alias for BTC Mode. Distribution does not automatically equal Risk-Off and accumulation does not automatically equal Risk-On. Empirical research determines how regime context should influence final BTC Mode.

**Status**

Accepted.

---

## 2026-09-11 — Bond Intelligence is a launch-critical research pillar

**Decision**

Bond Intelligence will be evaluated as a potential canonical macro/rates pillar for BTC Mode rather than treated only as a decorative widget. It does not receive decision power until incremental value is demonstrated empirically.

**Status**

Accepted for research. WordPress/session integration remains pending.

---

## 2026-09-11 — Session-Aware BTC Intelligence P0.2A

**Decision / release state**

PR #72 (`feature/p02a-session-aware-intelligence`) was merged to `main` after deterministic tests, release safety checks, and staging validation passed.

The canonical session anchors are 08:10 and 20:10 `America/New_York`, including weekends and US holidays. US market calendar status is context, not a generation kill switch.

**Production status**

Not deployed/authorized as part of this milestone. Production remains unchanged until a later explicit release decision.

---

## 2026-09-11 — Research and release provenance are company assets

**Decision**

Important findings, failed experiments, accepted methodologies, source/version lineage, and release evidence must live in versioned canonical artifacts rather than only in chats, staging state, or engineer memory.

Staging is a disposable validation environment, not a source of truth. Production changes must trace back to versioned source and release evidence.

**Status**

Accepted operating principle.

---

## 2026-09-12 — BTC Mode research corrected to intraday decision horizons

**Decision**

The first P0.2B A/B/C run is retained as an exploratory research artifact but is not accepted as canonical calibration evidence for `btc-mode-v1`.

The corrected research design keeps the 30-day full-feature window but changes the primary target to active intraday decision support:

- primary feature replay: hourly PIT-safe snapshots, unless a source genuinely supports a higher canonical update cadence
- primary outcome horizons: +15m, +30m, +1h, +2h, +4h, +6h
- secondary context: +12h and +24h
- required path metrics: return, MAE, MFE, time-to-MAE, time-to-MFE, realized volatility, and path ordering where defensible
- the 08:10/20:10 session editions remain deep-context/product checkpoints, not the sole statistical sample for Mode calibration
- overlapping observations must be handled with chronological validation, purging/embargo, and dependence-aware uncertainty
- the replay must reproduce repository production semantics exactly; substitute indicators are exploratory only

**Rationale**

Bitmomo's intended differentiation is short-horizon BTC decision intelligence for active traders. A 30-day dataset is not inherently too short if sampled at an appropriate intraday cadence, but using +24h/+72h outcomes as the primary target discards the path information active traders care about. Intraday MFE/MAE and time-to-move are more aligned with the product than buy-and-hold close-to-close returns.

**Status**

Accepted. This supersedes the earlier P0.2B cadence/outcome emphasis. No BTC Mode production formula is frozen yet.

---

## 2026-09-12 — Intraday replay separates opportunity from direction

**Decision**

Do not treat the existing BTC Core directional label as a direct Risk-On/Risk-Off mapping. Research will separate:

- **Opportunity / Activity**: whether a meaningful tradable move is likely soon
- **Directional Stance**: whether evidence is strong enough to prefer upside or downside, with explicit abstention allowed

BTC Mode remains the single customer-facing compression layer. Risk-On/Risk-Off require sufficient directional evidence; otherwise the state is Wait & See even when movement probability is high.

**Evidence**

The corrected replay produced 720 hourly observations and 2,880 exploratory 15-minute observations. Existing inputs were materially more robust for future movement/volatility than for return direction. The exploratory tactical layer achieved roughly 0.75 / 0.79 / 0.84 holdout AUC for detecting a >=0.3% excursion within 30m / 1h / 2h, while directional AUC remained close to chance.

Transition regime also behaved as a conflict/abstention context, and full multi-timeframe directional agreement could arrive too late for intraday continuation. Existing Confidence therefore remains evidence-agreement confidence, not win probability.

**Status**

Accepted for continued research architecture. Production methodology remains unfrozen. See `docs/research/btc-mode/RESULTS_V1.md` and ADR-004.
