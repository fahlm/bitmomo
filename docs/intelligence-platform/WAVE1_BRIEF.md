# Bitmomo Intelligence Platform — Wave 1 Delegation Brief

Status: ACTIVE — engineering brief for Claude Code
Owner: Fahmi (founder) / CTO
Branch: `research/intelligence-platform-v2` (branched from current `main`)
Canonical location: `docs/intelligence-platform/WAVE1_BRIEF.md`

---

## 1. Role and mission

You are Bitmomo's Senior Intelligence Systems Engineer. Your job is to build the
**Bitmomo Intelligence Lab V1**: a deterministic, point-in-time (PIT) research and
replay platform on which every future intelligence engine (Directional Stance,
on-chain/Dune features, Event Intelligence, Calibration, Analyst Runtime,
Champion–Challenger) will be built and evaluated.

Pipeline:

```
raw historical data → PIT normalization → feature computation
→ engine replay → outcome settlement → evaluation
```

Wave 1 is infrastructure plus one proof: reproducing existing research results from
code. It is **not** a place to invent new signals or change production methodology.

## 2. Read before writing any code

1. `README.md`, `CONTRIBUTING.md`
2. `docs/CURRENT_RELEASE.md` — the whitelist RC is frozen and awaiting production
3. `docs/ENGINEERING_OPERATING_MODEL.md`
4. `docs/technical-audit-v1.md`, `docs/INTELLIGENCE_CONTRACT_V1.md`
5. `research/btc-mode/README.md` — research-code rules (binding)
6. `docs/research/btc-mode/` — especially `SOURCE_COVERAGE_AUDIT.md`,
   `METHODOLOGY.md`, `DATA_DICTIONARY.md`, `P02C_MULTIYEAR_TACTICAL_REPLAY_V0.md`,
   `DIRECTIONAL_STANCE_GATE_V0.md`, `LIMITATIONS.md`
7. `docs/intelligence/OPPORTUNITY_V1.md`, `docs/adr/ADR-004-*.md`
8. `website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-opportunity.php`
   and `class-bitmomo-ai-signal-engine.php` (the logic you will need parity with)

Summarize what you learned and any contradictions **before** starting M1.

## 3. Hard boundaries (forbidden changes)

- Do **not** modify anything under `website/`, `scripts/build-production-artifact.py`,
  `scripts/check-staging-*`, `.github/workflows/`, `config/staging/`, or any file
  managed by the accepted RC. Mutation of RC-managed source invalidates the candidate.
- Do **not** re-enable GitHub-hosted Actions. Validation is local.
- Do **not** write to production or staging WordPress, databases, or Telegram.
- Do **not** commit raw/large datasets, `.env`, API keys, or credentials. Commit
  manifests (path, row count, time range, SHA-256, source) instead.
- Do **not** encode new production weights, thresholds, or BTC Mode rules.
- Do **not** deploy anything to the VPS or any server without Fahmi's explicit
  approval in the session. Prepare scripts; a human runs or approves them.
- Do **not** merge to `main`. Open PRs only; Fahmi/CTO decides.

## 4. Allowed paths

```
research/lab/                      # Python package (new)
  bitmomo_lab/
    store/        # PIT storage, schemas, manifests, loaders
    ingest/       # source fetchers + recorders (no secrets in code)
    features/     # feature registry + feature implementations
    engines/      # engine registry + versioned Python ports
    replay/       # replay runner
    outcomes/     # outcome labeling / settlement
    evaluate/     # metrics, walk-forward, breakdowns, reports
    registry/     # hypothesis registry
  tests/
  pyproject.toml
research/lab/fixtures/             # small golden fixtures only (committable)
research/lab/php-harness/          # CLI harness that calls production PHP classes
                                   # read-only to emit golden fixtures
docs/intelligence-platform/        # this brief, design docs, ADRs, results
```

Local data lives in `research/lab/.data/` (gitignored), with subfolders
`raw/ normalized/ features/ intelligence/ outcomes/ metadata/`.

## 5. Stack

Python 3.11+, DuckDB, Parquet (pyarrow), pandas or polars, numpy, scikit-learn
(evaluation only), pytest. Keep dependencies minimal and pinned. No Kafka,
Kubernetes, ClickHouse, or managed services.

## 6. Non-negotiable design principles

**Knowledge time is first-class.** Every stored observation carries:

```
source, source_version, event_time, available_at (knowledge_time),
ingested_at, computed_at, transformation_version, feature_version,
methodology_version, quality, fallback_used
```

A replay at decision time `t` may only read rows with `available_at <= t`.
This must be enforced by the loader API, not by caller discipline.

**No silent neutral fill.** Missing data stays missing and is reported as coverage.

**Parity over reimplementation.** Any Python port of a production engine must be
versioned (e.g. `opportunity-v1-py`) and must match golden fixtures generated from
the production PHP classes via `php-harness/`. Tolerances must be explicit.

**Determinism.** Same dataset manifest + same code commit → byte-identical results.
Every result artifact records dataset manifest hash, code commit, and methodology
version.

**Anti-overfitting.** Hypotheses are registered in `registry/` (id, statement,
features, horizon, success criterion, date) *before* evaluation. Holdout windows are
locked in config and cannot be read by calibration code. Reports state how many
hypotheses/variants were tested. ABSTAIN is a valid, measured outcome.

**Separation of states.** Never report "done" as one claim. Report separately:
code written, tests passing, reproduced against reference, reviewed.

## 7. Milestones and acceptance criteria

### M0 — Data recovery and forward recorder

1. Ask Fahmi where the P0.2C dataset (Binance BTCUSDT Spot + USD-M 5m klines,
   2020-01-01 → 2026-09-10) and its replay code currently live. If unavailable,
   write a rebuild script from Binance public bulk archives.
2. Produce dataset manifests for everything ingested.
3. Build a **forward-only derivatives recorder** (OI, funding, long/short ratio,
   taker buy/sell volume, premium/basis) that appends to PIT Parquet with
   `available_at`. Public endpoints only, no keys. Include a cron example and a
   runbook, but do not deploy.
4. Investigate whether Binance bulk archives contain historical derivatives metrics
   usable for backfill; document findings with coverage dates.

Acceptance: manifests committed; recorder has tests with recorded HTTP fixtures;
backfill findings documented in `docs/intelligence-platform/`.

### M1 — PIT store

Schema, writers, and a loader with `as_of(t)` semantics; manifests; data-quality
report (gaps, duplicates, out-of-order, coverage per source per window).

Acceptance: tests prove leakage is impossible through the public API (including
adversarial tests: late-arriving rows, revised rows, candle not yet closed);
gap detection reproduces the 462 missing Spot intervals noted in P0.2C.

### M2 — Engine registry and `opportunity-v1` parity

Engine interface (`name`, `version`, `required_features`, `compute(snapshot)`),
feature registry, PHP golden-fixture harness, Python port of `opportunity-v1`.

Acceptance: port matches ≥ N diverse golden fixtures (include edge cases: missing
inputs, extreme volatility, session boundaries) within documented tolerance.
If exact parity is impossible, document every divergence and why.

### M3 — Replay, outcomes, evaluation

```
replay(engine="opportunity-v1-py", start, end, cadence="15m",
       methodology_version="v1") -> ReplayResult
```

Outcomes: forward returns at configurable horizons, MAE/MFE, first excursion,
movement ≥ threshold, delayed entry. Evaluation: sample count, coverage,
missing-data ratio, hit rate, abstention rate, AUC, calibration buckets, regime and
session breakdowns, chronological split with purge/embargo, walk-forward, baseline
comparison, feature ablation. Markdown + machine-readable (JSON/Parquet) reports.

Acceptance: CLI runs end-to-end on the local dataset; unit tests for every metric
against hand-computed examples; full replay of the multi-year window finishes in
reasonable time on a laptop (document timing).

### M4 — Reproduction proof

Reproduce the headline P0.2C and Directional Stance Gate V0 results using only the
platform.

Acceptance: a results doc comparing platform numbers to published numbers, with
every discrepancy explained. Matching (or explained divergence) is the definition
of "Wave 1 works".

## 8. Working rules

- Small, focused commits and PRs per milestone (stacked PRs allowed if documented).
- Run `pytest` and `bash scripts/bitmomo-check.sh quick` before every PR; paste
  results into the PR description.
- Stop and ask Fahmi when: a boundary would be crossed, data location is unknown,
  production PHP behavior is ambiguous, or a result contradicts existing docs.
- End each session with a short handoff note in
  `docs/intelligence-platform/HANDOFF.md`: what changed, state per milestone,
  open questions, next step.

## 9. Out of scope for Wave 1

Dune/on-chain ingestion, Directional Stance modeling, Event Intelligence,
confidence recalibration, LLM analysts, champion–challenger automation, moving
production compute out of WordPress, UI of any kind.
