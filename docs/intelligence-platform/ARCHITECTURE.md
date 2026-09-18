# Bitmomo Intelligence Lab — Architecture (Wave 1, as of M1)

Status: M0 accepted; M1 (first engine vertical slice, Opportunity V1) implemented. Research only.
Code: `research/lab/` (package `bitmomo_lab`). Brief: [WAVE1_BRIEF.md](WAVE1_BRIEF.md).

## Scope decisions recorded for Wave 1 (Fahmi, 2026-09-18)

| Topic | Decision |
|---|---|
| M4 | Split. **M4a (Wave 1):** Opportunity V1 parity, P0.2C-style multi-year replay, PIT feature construction, deterministic settlement, manifests, deterministic reruns. **M4b (Wave 1.5):** Directional Stance Gate V0 reproduction, with ports of `signal-engine-v1-py`, the production feature semantics it needs, `regime-v1-py` and hysteresis. Wave 1 provides the plug-in boundary only. |
| Neutral fill | Two structural modes: `strict` (research truth, never fills) and `production_parity` (reproduces PHP `?? 0` etc., recording every substitution). Parity output can never enter the canonical store. |
| Recorder | Built in M0, research branch only. Manual invocation, plus an example scheduler file and a runbook. No deployment, daemon, cron install, secrets or authenticated endpoints. |
| Old coverage audit | Historical evidence. Superseded in [M0_SOURCE_COVERAGE.md](M0_SOURCE_COVERAGE.md) with explicit links; the old file is not rewritten. |
| Directories | `research/lab/` = reusable infrastructure (not BTC-Mode-specific). `research/btc-mode/` = domain research history, left untouched. |
| P0.2C data | Rebuilt reproducibly from official Binance archives. Published counts (704,160 / 703,698) are verification targets, not constants. |
| P0.2B artifacts | `canonical_hourly_intraday_replay.csv` and `tactical_15m_exploratory.csv`, if supplied later, are `historical_reference / parity_oracle`, never `canonical_source`. |
| PHP | Not needed for M0. The local PHP harness is deferred to the milestone that tests PHP parity (M2). |

## Pipeline and module boundaries

```
sources/            acquisition: archive planning, checksum-verified download, raw cache + provenance,
                    empirical coverage probe (S3 listing); dataset registry with availability rules
normalize/          raw CSV -> canonical observation table (unit detection, header detection,
                    boundary checks, duplicate resolution, available_at derivation)
validate/           data-quality: grid gaps, duplicates, out-of-order, coverage per year, event spacing
store/              deterministic Parquet writer + content hash + strict-only guard;
                    PITFrame.as_of(T) = the only read path
build.py            acquire -> normalize -> assemble -> validate -> store -> manifest
recorder/           forward-only public-REST recorder (manual), raw envelopes, revisions
engines/contract.py Snapshot, InputResolver, strict / production_parity modes, Engine base
engines/registry.py EngineSpec (id, version, methodology, inputs, features, min history,
                    output schema, parameters, source ref) + a dict registry — no plugin framework
engines/opportunity_v1.py  parity port of the PHP calculator + equivalent batch evaluator
replay/runner.py    manifest-verified inputs -> engine -> immutable records -> run manifest
outcomes/settlement.py     forward path labels (+15m..+6h), available only after settlement
features/           evaluation-side features (abs_return_60m, vol_context_v1) and the
                    metrics timestamp-semantics guard
evaluate/           metrics (rank AUC, bootstrap), splits (segments, purge, walk-forward,
                    DevelopmentSlice guard), opportunity study, report renderer, pipeline
registry/           pre-registered hypotheses + historical references (parity oracles)
config/             locked evaluation plans
cli.py              build | verify | coverage | equivalence | replay | opportunity-study |
                    record | recorder-gaps
```

Ownership boundary: the lab owns data truth, PIT access, deterministic features, engine
execution, settlement, evaluation and reproducibility. Each engine owns its methodology in
one module (`engines/<engine>.py`) plus its `EngineSpec`. A second engine adds a module, a
spec and an evaluator entry in `replay/runner.py`, with no storage redesign. Engines never
import `outcomes` or `evaluate` (enforced by test).

Not yet built: `php-harness/` (needs a PHP CLI; parity is currently proven by ported PHP
test vectors plus hand-derived vectors).

## M1 execution path

```
manifest (verified) -> PITFrame -> Snapshot.build(T) / BatchEvaluator (invariant-checked,
64-cutoff cross-check) -> engine output -> immutable record (lineage, hashes, mode)
-> run manifest (logical hash excludes computed_at)       [.data/intelligence | .data/parity]
                    settlement (available_at = T + 6h) -> outcomes dataset [.data/outcomes]
records x outcomes -> segments / walk-forward / baselines / bootstrap -> JSON + Markdown
```

Records: one row per cutoff with `event_time = available_at = cutoff`, engine identity,
parameters hash, status/state/values, `error_code`, `quality_flags`, `evidence_coverage`,
`output_json`, `output_sha256`, `record_sha256` (independent of code commit and wall clock),
`code_commit`, `computed_at`. Run manifests live in `research/lab/runs/`.

## Canonical observation contract (`contract.py`)

Every stored observation has: `source`, `dataset`, `symbol`, `interval`, `event_time`,
`available_at`, `availability_basis`, `schema_version`, `quality`, `raw_ref`. All times are
`timestamp[us, UTC]`. Recorder rows add `received_at`, `request_url` and `raw_response_sha256`.
Ingestion time for archive data lives in the raw sidecar (`fetched_at`), not in rows, so that
normalized content stays deterministic. Derived features (M2+) must also carry `feature_id`,
`feature_version`, `computed_at` and `input_lineage`.

`quality` ∈ `ok`, `conflicting_duplicate`, `boundary_mismatch`, `out_of_file_period`,
`period_incomplete`. Non-ok rows are stored, reported and excluded from `as_of` by default.
Nothing is ever dropped silently, and missing values stay null.

## Point-in-time invariant (executable)

> For a replay cutoff `T`, no engine or feature may consume any observation whose
> `available_at > T`.

Enforcement:

1. `PITFrame` holds its table in a name-mangled slot. Its only readers are `as_of(T)` and
   `window(T, lookback)`, both of which filter `available_at <= T` and `quality == ok`, and
   resolve revisions to the latest version known at `T`.
2. `as_of` re-checks its own output with `assert_pit`, which raises `LeakageError`.
3. `engines.contract.Snapshot.build` is the only constructor engines receive inputs from. It
   calls `as_of` and `assert_pit` again.
4. Tests (`tests/test_pit.py`): candle not yet closed, late-arriving row, revised row,
   incomplete→complete observation, non-ok exclusion, naive-datetime rejection, a direct
   leak-guard test, and a randomized test of 200 cutoffs checked against a brute-force oracle.
5. Integer cutoffs must be epoch **microseconds**. Anything smaller is refused, because a
   seconds value silently selected nothing during M1 development (caught by tests).
6. Outcomes are settled into rows with `available_at = cutoff + 6h`. An engine handed the
   outcome table sees nothing for its own cutoff, and engine modules may not import
   `outcomes`/`evaluate` (`tests/test_settlement.py`).
7. Batch replay paths must prove the close-boundary invariant before running, and are
   cross-checked against per-cutoff `Snapshot` evaluation on every real run.

### Source-specific `available_at` rules

| Dataset | `event_time` | `available_at` | Basis |
|---|---|---|---|
| klines, mark/index/premium klines (5m) | open time | open + 5m (close boundary) | matches production (`close_time <= cutoff`) |
| `metrics` (5m) | `create_time` as stamped; per-day `timestamp_semantics` (period_end / period_start / UNVERIFIED) via `features/metrics_semantics.py`. It marks the period **end** until 2024-02 and the period **start** from 2024-03 ([M0_SOURCE_COVERAGE §4.1](M0_SOURCE_COVERAGE.md)) | create_time + 5m + **5m declared lag** (≥ period end in both eras) | archive publication latency unobserved; replace with recorder-measured latency. OI/ratio values ≤ 0 are sentinels, nulled and listed in `invalid_value_fields` |
| `fundingRate` | `calc_time` (±1 ms jitter kept) | calc_time + **5m declared lag** | settled rate cannot precede settlement |
| recorder (all) | exchange timestamp | `received_at` | observed |

## Strict vs production-parity (`engines/contract.py`, `store/parquet.py`)

* `InputResolver.value()` records a missing input and returns `None`. It never fills.
* `InputResolver.parity_fill()` reproduces a legacy PHP fallback **only** in
  `Mode.PRODUCTION_PARITY`, and appends an `ImputationFlag(field, original_value=None,
  effective_value, rule, production_compatibility_fill=True)`. In strict mode it raises
  `StrictModeViolation`.
* `EngineResult` in strict mode cannot carry imputation flags (constructor check).
* `store.parquet.guard_strict` refuses any table that has parity-only columns
  (`imputation_flags`, `production_compatibility_fill`, `engine_mode`) or is marked
  `bitmomo_lab.mode != strict`. Every canonical write passes this guard, and every canonical
  file is stamped `strict`.

## Reproducibility

* Raw files are accepted only if their SHA-256 matches Binance's published `.CHECKSUM`. A
  tampered cache is detected and re-fetched.
* The normalized table is sorted deterministically, and duplicates resolve deterministically.
* `content_sha256` hashes the values: little-endian buffers for fixed-width columns, UTF-8
  for strings, explicit null masks. It is independent of Parquet encoding and chunking.
  The Parquet file hash is recorded as well (stable for a pinned `pyarrow`).
* Manifests contain no wall-clock values. Same raw bytes + same commit + same parameters
  produce a byte-identical manifest (`tests/test_build.py`). `manifest_body_sha256` detects
  edits (`bitmomo-lab verify`).
* Dependencies are pinned in `research/lab/uv.lock` (Python 3.12, pyarrow 21.0.0,
  pytest 8.4.2).

To reproduce from nothing: delete `research/lab/.data/`, run the `build` commands listed in
[M0_DATASETS.md](M0_DATASETS.md), then run `bitmomo-lab verify` against each committed
manifest.
