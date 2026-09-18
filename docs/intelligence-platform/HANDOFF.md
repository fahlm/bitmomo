# Intelligence Platform — Handoff

## Session 2026-09-18 (continued) — M1: Opportunity V1 vertical slice

Branch: `research/intelligence-platform-v2-m1`, stacked on `research/intelligence-platform-v2`
(M0, Draft PR #210). **Depends on #210.** Nothing is merged, deployed, or changed in production,
staging or WordPress. No `website/` file was modified; the PHP classes were only read.

### What changed

* `engines/registry.py` (EngineSpec + minimal registry), `engines/opportunity_v1.py` (parity
  port + invariant-checked batch evaluator).
* `replay/runner.py` (immutable lineage-carrying records, run manifests, strict vs parity storage).
* `outcomes/settlement.py` (+15m…+6h path labels; `available_at = cutoff + 6h`).
* `evaluate/` (rank AUC, day bootstrap, segments + purge, expanding walk-forward,
  `DevelopmentSlice` fitting guard, study, generated Markdown, end-to-end pipeline,
  P0.2C divergence diagnostic).
* `features/context.py` (abs_return_60m, vol_context_v1), `features/metrics_semantics.py` (guard).
* `validate/metrics_anomaly.py` (bounded investigation + aggTrades cross-check).
* `registry/hypotheses.json` + `config/opportunity_v1_evaluation.json`, **committed before evaluation** (`9b3389f`).
* `registry/references/` (P0.2C tables as historical_reference; per-day metrics semantics).
* Fixes found during M1: `PITFrame.as_of` now refuses non-microsecond integer cutoffs; boolean
  columns in the content hash; `as_of` fast path when there are no revisions.
* Docs: `M1_OPPORTUNITY_V1_PARITY.md`, `M1_OPPORTUNITY_V1_RESULTS.md` (generated),
  `M1_OPPORTUNITY_V1_FINDINGS.md`, `M1_METRICS_ANOMALY_INVESTIGATION.md`, `results/*.json`,
  and updates to ARCHITECTURE / M0_SOURCE_COVERAGE.

### Results (headline; details in FINDINGS)

* Pre-registered hypotheses: H-OPP-001 PASS, 002 PASS, **003a FAIL**, 003b PASS, 004 PASS,
  005 PASS, **006 FAIL (explained)**.
* Holdout (+1h, ≥0.30%): HIGH 88.8% / NORMAL 67.5% / LOW 41.7%; HIGH−LOW 47.1 pp
  (day-bootstrap 95% CI 44.5–49.4); AUC 0.736. Ordering holds in 303/306 cells (the 3 misses
  are saturated 2021 long-horizon cells).
* The raw 60m range ranks absolute moves better (003a), but fixed thresholds collapse out of
  sample (holdout HIGH 8.9% / LOW 50.9%). The adaptive design is justified for a relative state.
* P0.2C used **Spot-path outcomes**: with Spot outcomes all 33 published cells reproduce within
  0.28 pp.
* Metrics anomaly: inside the archive taker field (klines == aggTrades); 2025-08-06 → 2026-04-06
  = `UNVERIFIED`, guarded in code.

### M1 acceptance gate (reported separately; nothing collapsed into one "done")

| # | Gate item | Status | Evidence |
|---|---|---|---|
| 1 | Deterministic Python implementation | ✅ | 3 replays → identical run id `2cb83ed716ea8724` and logical hash `71ce00aa…` |
| 2 | Known PHP behavior reproduced by golden/parity tests | ✅ partial | all 10 calculator/scheduler checks of the PHP test file + hand-derived vectors; **PHP-executed harness still open** (no local PHP) |
| 3 | Engine cannot bypass PIT rules | ✅ | Snapshot-only inputs; batch path proves invariant + 64-cutoff cross-check; µs-cutoff guard |
| 4 | Output records have lineage/versioning | ✅ | records + run manifests (`research/lab/runs/`) |
| 5 | Settlement cannot leak into evaluation | ✅ | `available_at = T+6h`; adversarial Snapshot test; engines may not import outcomes/evaluate |
| 6 | Same input → identical output | ✅ | `test_replay.py`; real reruns identical |
| 7 | Replay end-to-end manifest → report | ✅ | `bitmomo-lab opportunity-study`, 6.0 min on a laptop (replay 1–2 min, settlement 1.7 min, evaluation 2.2 min) |
| 8 | Chronological / walk-forward reporting | ✅ | segments with 6h purge; 6 expanding yearly folds |
| 9 | Baseline comparison | ✅ | base rate, raw range, dev-fitted fixed quartiles, |60m return| |
| 10 | Missing data explicit | ✅ | 1,348 warm-up records kept as `insufficient_history`; incomplete paths null, never zero |
| 11 | Strict / parity isolated | ✅ | parity run `3d3ae43d4081a373` under `.data/parity/`, refused by canonical I/O; outputs identical (no fills in Opportunity) |
| 12 | No production/staging/runtime change | ✅ | diff vs `origin/main` touches no `website/`, `scripts/`, `.github/`, `config/` |
| 13 | Tests green | ✅ | `pytest` 120 passed (13.8 s) |
| 14 | Methodology/report documented | ✅ | PARITY, RESULTS (generated), FINDINGS docs |
| 15 | HANDOFF updated | ✅ | this section |
| — | Reviewed | ❌ | not yet reviewed |

Validation run for this session:
* `pytest` (research/lab): **120 passed**.
* `bash scripts/bitmomo-check.sh quick`: still exits at `need php` (by decision). Its Python
  compile step was run by hand on all 55 changed `.py` files: PASS. The diff contains no
  `.php`/`.js`.

### Open items

1. **PHP-executed golden vectors** (`php-harness/`) need a PHP CLI or a CI job. This is the only
   parity item not closed.
2. Recorder live capture (deferred acceptance item from M0; unchanged).
3. Suggested next research (not started): evaluate Opportunity against a **volatility-scaled**
   event target, which is the fair target for a relative state. It needs a new hypothesis entry.

### Next step

Stop here per instruction. Directional Stance (Wave 1.5) starts only after Fahmi/CTO review
of M1.

---

## Session 2026-09-18 — M0 (data recovery + forward recorder)

**Decision (Fahmi, 2026-09-18): M0 = PASS.**
* Forward recorder status: `RESEARCH-READY / NOT PRODUCTION-VALIDATED`. It stays that way until at
  least one legitimate live Binance response has been captured (`record --capture-fixtures`) and
  replayed in tests. This is a **deferred acceptance item that blocks any persistent recorder
  deployment**, not M0. No proxy, VPN, alternate endpoint or other workaround for the DNS/ISP block.
* Git remote sanitized to `https://github.com/fahlm/bitmomo.git` (no embedded credential; the old
  token is being revoked separately). Branch pushed via `gh` as a one-off credential helper, with
  no global Git config change.
* Metrics 2025-08 → 2026-03: `timestamp_semantics = UNVERIFIED`; bounded investigation runs alongside M1.
* P0.2B CSVs located (outside the repo): `historical_reference / parity_oracle` for Wave 1.5 only.

Branch: `research/intelligence-platform-v2` (from `origin/main` `e7f53e4`), pushed after the
remote was sanitized; M0 is in a Draft PR (not to be merged by the engineer).
Worktree: `worktrees/intelligence-platform-v2` inside the main checkout. The main checkout,
the release/RC worktrees and all runtimes were not touched.

### What changed

* `research/lab/`: new Python package `bitmomo_lab` (acquisition, normalization, PIT store,
  validation, manifests, recorder, engine contract, CLI), 75 tests, labeled fixtures, a
  pinned `uv.lock`, and `ops/recorder.cron.example` (not installed).
* `research/lab/manifests/`: 7 dataset manifests (2020-01-01 → 2026-09-10; metrics from 2020-09-01).
* `docs/intelligence-platform/`: `ARCHITECTURE.md`, `M0_SOURCE_COVERAGE.md`,
  `M0_DATASETS.md`, `RECORDER_RUNBOOK.md`, this file, and the brief.
* `.gitignore`: `research/lab/.data/`.
* Nothing under `website/`, `.github/workflows/`, `scripts/`, `config/staging/`, or any
  RC-managed path changed. `research/btc-mode/` and `docs/research/btc-mode/` are unchanged.

### State per milestone (reported separately, never as one "done")

| Item | Code written | Tests passing | Reproduced vs reference | Reviewed |
|---|---|---|---|---|
| M0.1 P0.2C dataset rebuild from official archives | yes | yes (unit + e2e determinism) | **USD-M 704,160 = match; Spot 703,698 = match; Spot missing 462 → 470 explained** (8 truncated maintenance candles) | no |
| M0.2 manifests | yes, 7 committed | yes (`verify` PASS ×7; determinism verified across 3 builds) | n/a | no |
| M0.3 forward recorder | yes | yes, on **synthetic** fixtures | **not yet**: no live call possible (`fapi.binance.com` DNS-blocked on this machine) | no |
| M0.4 backfill investigation | yes (`coverage`, `equivalence` commands) | yes | documented in M0_SOURCE_COVERAGE.md | no |
| Brief acceptance "recorder has tests with **recorded** HTTP fixtures" | — | — | **NOT MET**: fixtures are synthetic until captured from an unblocked host | — |

Validation run for this session:
* `pytest` (research/lab, Python 3.12.11): 75 passed.
* `bash scripts/bitmomo-check.sh quick`: **could not run.** It exits at `need php` (PHP is
  not installed, by decision). Its Python step (`compile()` of every changed `.py` with
  system `python3` 3.9.6) was run by hand on all 31 files: PASS. The php/node steps are not
  applicable to this diff, which contains no `.php`/`.js`.

### Findings that change earlier assumptions

1. **`metrics.create_time` semantics change over time.** It stamps the period END until
   2024-02 and the period START from 2024-03; 2025-08 → 2026-03 is unexplained. In the
   START regime, using `create_time` as knowledge time leaks 5 minutes. This probably
   explains the rejected P0.2B "same-timestamp" edge. The lab's
   `available_at = create_time + 10m` is safe in both regimes.
2. The metrics archive has **75,255 exact duplicate rows**, **OI `0` sentinels** (nulled with
   provenance), and **null taker ratios for 2022-01 → 2022-04**.
3. **Spot µs timestamps from 2025-01** are handled and tested.
4. Production's `oi_change_24h_pct` spans the first and last of 30 hourly rows (≈29h, not 24h).
   This is a parity fact to carry into Wave 1.5; the production code is not to be changed here.
5. The 1h taker `buySellRatio` cannot be derived from 5m archive ratios, but **can be
   reconstructed from USD-M klines** (Σ taker_buy / Σ(volume − taker_buy)).
6. `count_long_short_ratio` ↔ production `globalLongShortAccountRatio` (1h) is **unverified**
   and not to be treated as equivalent yet.

### Open questions (M0) — resolved 2026-09-18

1. Recorder live verification: deferred acceptance item (see top of this section).
2. Remote auth cleanup: done; the old token is being revoked separately.
3. Metrics anomaly: bounded investigation alongside M1; `UNVERIFIED` until explained.
4. P0.2B oracle CSVs: located outside the repo; used only in Wave 1.5.

### Next step

M1 (redefined by Fahmi 2026-09-18): first engine vertical slice, Opportunity V1: registry →
parity port → immutable records → settlement → walk-forward evaluation → reports.
