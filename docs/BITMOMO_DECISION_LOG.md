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

Use two samples:

- production-aligned observations at 08:10 and 20:10 `America/New_York` (~60 before exclusions)
- 4-hour robustness observations (~180 before exclusions)

Primary outcome horizons are +6h, +12h, and +24h, with +72h as a secondary persistence check. MAE, MFE, and realized volatility are part of the evaluation.

**Rationale**

BTC Mode is intended for short-horizon active decision support. Full feature fidelity is more valuable for V1 calibration than long history with materially different inputs. The small sample makes precise fitted weights inappropriate, so research should prefer conditional confirmation/conflict rules and conservative state mapping.

**Status**

Accepted for P0.2B research. Results remain provisional until empirical execution is complete.

---

## 2026-09-11 — Forward validation becomes the long-run evidence base

**Decision**

After `btc-mode-v1` is frozen and launched, every official 08:10/20:10 session output and subsequent outcome must be stored append-only. V1 historical records are immutable. This forward dataset becomes the primary long-run validation asset.

**Status**

Accepted.

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
