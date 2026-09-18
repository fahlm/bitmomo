# Intelligence Platform — Handoff

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

Branch: `research/intelligence-platform-v2` (from `origin/main` `e7f53e4`). Local commits
only; **not pushed**. The push is blocked on the token-in-remote-URL cleanup (Fahmi).
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

### Open questions for Fahmi / CTO

1. **Recorder live verification.** Can someone run
   `bitmomo-lab record --capture-fixtures fixtures/recorder` once from a host with legitimate
   Binance API access? This replaces the synthetic fixtures and starts latency measurement.
   Where should the recorder eventually run? (It needs your explicit approval; nothing is
   deployed.)
2. **Remote auth cleanup** (PAT embedded in `origin` URL) before the first push / PR.
3. **Metrics 2025-08 → 2026-03 anomaly.** Should it be investigated in M1, or should
   research simply exclude that window for taker-ratio claims?
4. The P0.2B oracle CSVs (`canonical_hourly_intraday_replay.csv`,
   `tactical_15m_exploratory.csv`): when convenient, send their location. They will be
   registered as `historical_reference / parity_oracle` for Wave 1.5.

### Next step

M1: extend `store/` with multi-dataset joins through `Snapshot`; turn the data-quality report
into a standalone command (gaps, duplicates, out-of-order, coverage per source per window);
and add adversarial tests on real data slices, including the 470-slot Spot gap set.
Then M2: `opportunity-v1-py` plus the PHP golden-fixture harness. That needs a local PHP CLI;
the install decision is deferred to M2 per Fahmi.
