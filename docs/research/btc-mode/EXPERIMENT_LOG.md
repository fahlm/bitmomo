# BTC Mode Research — Experiment Log

Status: ACTIVE LOG

Every experiment, including rejected/failed experiments, is recorded here. Do not delete prior entries when conclusions change; append a new experiment or superseding note.

## Template

### EXP-XXX — Short title

- **Status:** PLANNED / RUNNING / PASS / FAIL / INCONCLUSIVE
- **Question:**
- **Hypothesis:**
- **Dataset ID / manifest:**
- **Coverage:**
- **Feature code commit:**
- **Engine/classifier versions:**
- **Training window:**
- **Validation window:**
- **Holdout window:**
- **Inputs tested:**
- **Outcome horizons:**
- **Metrics:**
- **Result:**
- **Interpretation:**
- **Decision:** KEEP / REJECT / RETEST / CONTEXT ONLY
- **Known limitations:**
- **Artifacts / report path:**

---

## Planned first sequence

### EXP-001 — Direction-only baseline

- **Status:** PLANNED
- **Question:** How much forward BTC return/risk discrimination exists in the canonical directional state alone?
- **Decision rule:** Establish baseline only; no BTC Mode production decision yet.

### EXP-002 — BTC Core incremental value

- **Status:** PLANNED
- **Question:** Does the full existing BTC Core materially improve on Direction-only for forward returns and path risk?

### EXP-003 — Market Regime incremental value

- **Status:** PLANNED
- **Question:** Conditional on BTC Core, does Market Regime change forward BTC return/downside distributions enough to justify decision influence in BTC Mode?

### EXP-004 — Bond incremental value

- **Status:** PLANNED
- **Question:** Conditional on BTC Core + Market Regime, do production-candidate Bond axes add stable out-of-sample information, especially for downside-risk discrimination?

### EXP-005 — BTC Mode candidate rule sanity

- **Status:** PLANNED
- **Question:** Does the proposed deterministic three-state rule produce sensible Risk-On / Wait & See / Risk-Off distributions and robust out-of-sample separation without excessive complexity?

No experiment may silently promote an input into production. Production methodology is frozen separately in `docs/intelligence/BTC_MODE_V1.md` only after explicit acceptance.
