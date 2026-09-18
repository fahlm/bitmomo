"""Engine replay: verified manifests -> PIT inputs -> engine -> immutable records.

Every record carries: cutoff, engine identity/version, methodology version, input
lineage (manifest + content hashes), feature versions, evidence coverage, quality
flags, output, output hash, mode, code version and computation timestamp.

Determinism: ``record_sha256`` and the run's ``logical_content_sha256`` exclude only
``computed_at``; same canonical input + same versions + same parameters give the
same logical output. Strict runs go to the canonical store; production-parity runs
go to a separate ``parity/`` tree and are refused by the canonical writer.
"""

from __future__ import annotations

import dataclasses
import datetime as dt
import hashlib
import json
import pathlib
import random
import time

import pyarrow as pa

from bitmomo_lab.build import DEFAULT_DATA_DIR, LAB_ROOT, body_sha256, code_identity
from bitmomo_lab.contract import SCHEMA_VERSION, TS, AvailabilityBasis, Quality
from bitmomo_lab.engines import opportunity_v1
from bitmomo_lab.engines.contract import Mode, Snapshot
from bitmomo_lab.engines.registry import get as get_engine
from bitmomo_lab.store.parquet import content_sha256, write_dataset, write_parity_dataset
from bitmomo_lab.store.pit import PITFrame, load_verified

RUN_MANIFEST_VERSION = "lab-run-v1"
DEFAULT_RUN_DIR = LAB_ROOT / "runs"


def _canonical_json(value) -> bytes:
    return json.dumps(value, sort_keys=True, separators=(",", ":"), allow_nan=False).encode()


def sha256_json(value) -> str:
    return hashlib.sha256(_canonical_json(value)).hexdigest()


@dataclasses.dataclass(frozen=True)
class InputRef:
    dataset: str
    manifest_path: str
    manifest_body_sha256: str
    content_sha256: str


def load_input(manifest_path: pathlib.Path, data_dir: pathlib.Path) -> tuple[InputRef, PITFrame]:
    manifest = json.loads(manifest_path.read_text())
    if body_sha256(manifest) != manifest["manifest_body_sha256"]:
        raise ValueError(f"manifest body hash mismatch: {manifest_path}")
    frame = load_verified(data_dir / manifest["normalized"]["relative_path"],
                          manifest["normalized"]["content_sha256"], manifest["dataset"])
    rel = manifest_path.resolve().relative_to(LAB_ROOT) if manifest_path.resolve().is_relative_to(LAB_ROOT) else manifest_path
    return InputRef(manifest["dataset"], str(rel), manifest["manifest_body_sha256"],
                    manifest["normalized"]["content_sha256"]), frame


def cutoffs_between(start: dt.datetime, end_inclusive: dt.datetime, cadence_seconds: int) -> list[int]:
    first = int(start.timestamp())
    if first % cadence_seconds:
        raise ValueError("start must be aligned to the cadence")
    return list(range(first, int(end_inclusive.timestamp()) + 1, cadence_seconds))


def _evaluate_opportunity(frame: PITFrame, cutoffs: list[int], mode: Mode, verify_sample: int, seed: int):
    """Batch path for multi-year replay, cross-checked against the per-cutoff PIT path."""
    engine = opportunity_v1.OpportunityV1()
    try:
        batch = opportunity_v1.BatchEvaluator(frame.as_of(cutoffs[-1] * 1_000_000))
        outputs = batch.evaluate(cutoffs)
        path = "batch"
    except opportunity_v1.BatchNotApplicable:
        outputs, path = None, "per_cutoff_snapshot"
    if outputs is None:
        outputs = []
        for cutoff in cutoffs:
            when = dt.datetime.fromtimestamp(cutoff, tz=dt.timezone.utc)
            outputs.append(dict(engine.run(Snapshot.build(when, {opportunity_v1.INPUT_DATASET: frame}), mode).output))
        return outputs, {"path": path, "sample_size": 0, "mismatches": 0}
    rng = random.Random(seed)
    sample = sorted(rng.sample(range(len(cutoffs)), min(verify_sample, len(cutoffs))))
    mismatches = 0
    for index in sample:
        when = dt.datetime.fromtimestamp(cutoffs[index], tz=dt.timezone.utc)
        snapshot = Snapshot.build(when, {opportunity_v1.INPUT_DATASET: frame})
        if dict(engine.run(snapshot, mode).output) != outputs[index]:
            mismatches += 1
    if mismatches:
        raise AssertionError(f"batch path disagrees with per-cutoff PIT path on {mismatches}/{len(sample)} cutoffs")
    return outputs, {"path": path, "sample_size": len(sample), "mismatches": 0, "sample_seed": seed}


EVALUATORS = {"opportunity-v1-py": _evaluate_opportunity}


def run_replay(
    engine_id: str,
    manifest_paths: list[pathlib.Path],
    start: dt.datetime,
    end_inclusive: dt.datetime,
    cadence_seconds: int,
    mode: Mode = Mode.STRICT,
    data_dir: pathlib.Path = DEFAULT_DATA_DIR,
    run_dir: pathlib.Path = DEFAULT_RUN_DIR,
    symbol: str = "BTCUSDT",
    verify_sample: int = 64,
    clock=time.time,
) -> dict:
    spec = get_engine(engine_id).spec
    loaded = [load_input(p, data_dir) for p in manifest_paths]
    refs = {ref.dataset: ref for ref, _ in loaded}
    frames = {ref.dataset: frame for ref, frame in loaded}
    missing = [d for d in spec.required_inputs if d not in frames]
    if missing:
        raise KeyError(f"{engine_id} requires inputs {missing}")
    cutoffs = cutoffs_between(start, end_inclusive, cadence_seconds)
    identity = spec.identity()
    lineage = [dataclasses.asdict(refs[d]) for d in spec.required_inputs]
    run_key = {"engine": identity, "mode": mode.value, "inputs": lineage, "symbol": symbol,
               "cadence_seconds": cadence_seconds, "first_cutoff": cutoffs[0], "last_cutoff": cutoffs[-1]}
    run_id = sha256_json(run_key)[:16]

    started = clock()
    outputs, verification = EVALUATORS[engine_id](frames[spec.required_inputs[0]], cutoffs, mode,
                                                  verify_sample, seed=int(run_id[:8], 16))
    elapsed = clock() - started
    code = code_identity()
    computed_at = dt.datetime.fromtimestamp(started, tz=dt.timezone.utc).isoformat()

    n = len(cutoffs)
    output_hash, record_hash, flags, coverage = [], [], [], []
    for cutoff, output in zip(cutoffs, outputs):
        out_sha = sha256_json(output)
        quality_flags = [output["error_code"]] if output.get("error_code") else []
        evidence = {"required_candles": opportunity_v1.REQUIRED_CANDLES,
                    "reference_observation_count": output.get("reference_observation_count"),
                    "status": output["status"]}
        record = {"cutoff": cutoff, "engine": identity, "mode": mode.value, "inputs": lineage,
                  "feature_versions": dict(spec.required_features), "evidence_coverage": evidence,
                  "quality_flags": quality_flags, "output_sha256": out_sha}
        output_hash.append(out_sha)
        record_hash.append(sha256_json(record))
        flags.append(json.dumps(quality_flags))
        coverage.append(json.dumps(evidence, sort_keys=True))

    cutoff_us = pa.array([c * 1_000_000 for c in cutoffs], pa.int64()).cast(TS)
    column = lambda key: [o.get(key) for o in outputs]  # noqa: E731
    table = pa.table({
        "source": pa.array(["bitmomo_lab"] * n), "dataset": pa.array([f"engine.{engine_id}"] * n),
        "symbol": pa.array([symbol] * n), "interval": pa.array([f"{cadence_seconds}s"] * n),
        "event_time": cutoff_us, "available_at": cutoff_us,
        "availability_basis": pa.array([AvailabilityBasis.ENGINE_CUTOFF.value] * n),
        "schema_version": pa.array([SCHEMA_VERSION] * n), "quality": pa.array([Quality.OK.value] * n),
        "raw_ref": pa.array([run_id] * n),
        "engine_id": pa.array([spec.engine_id] * n), "engine_version": pa.array([spec.engine_version] * n),
        "methodology_version": pa.array([spec.methodology_version] * n),
        "parameters_sha256": pa.array([identity["parameters_sha256"]] * n),
        "status": pa.array(column("status"), pa.string()), "state": pa.array(column("state"), pa.string()),
        "range_60m_pct": pa.array(column("range_60m_pct"), pa.float64()),
        "activity_percentile": pa.array(column("activity_percentile"), pa.float64()),
        "reference_observation_count": pa.array(column("reference_observation_count"), pa.int64()),
        "error_code": pa.array(column("error_code"), pa.string()),
        "quality_flags": pa.array(flags), "evidence_coverage": pa.array(coverage),
        "output_json": pa.array([json.dumps(o, sort_keys=True) for o in outputs]),
        "output_sha256": pa.array(output_hash), "record_sha256": pa.array(record_hash),
        "code_commit": pa.array([code["code_commit"]] * n),
    })
    logical_sha = content_sha256(table.drop_columns(["code_commit"]))
    table = table.append_column("computed_at", pa.array([computed_at] * n))

    if mode is Mode.STRICT:
        rel = pathlib.Path("intelligence") / engine_id / f"{run_id}.parquet"
        _, file_sha = write_dataset(table, data_dir / rel)
    else:
        table = table.append_column("engine_mode", pa.array([mode.value] * n))
        rel = pathlib.Path("parity") / engine_id / f"{run_id}.parquet"
        _, file_sha = write_parity_dataset(table, data_dir / rel)

    statuses: dict[str, int] = {}
    for o in outputs:
        key = o["state"] if o["status"] == "available" else o["error_code"]
        statuses[key] = statuses.get(key, 0) + 1
    manifest = {
        "run_manifest_version": RUN_MANIFEST_VERSION,
        "run_id": run_id,
        "engine": identity,
        "engine_spec": {"source_ref": spec.source_ref, "min_history": spec.min_history,
                        "required_inputs": list(spec.required_inputs), "output_schema": dict(spec.output_schema),
                        "parameters": dict(spec.parameters)},
        "mode": mode.value,
        "symbol": symbol,
        "inputs": lineage,
        "cadence_seconds": cadence_seconds,
        "window": {"first_cutoff": dt.datetime.fromtimestamp(cutoffs[0], tz=dt.timezone.utc).isoformat(),
                   "last_cutoff": dt.datetime.fromtimestamp(cutoffs[-1], tz=dt.timezone.utc).isoformat(),
                   "cutoffs": n},
        "outcome_counts": dict(sorted(statuses.items())),
        "output": {"relative_path": str(rel), "rows": n,
                   "logical_content_sha256": logical_sha,
                   "note": "logical hash excludes computed_at and code_commit"},
        "verification": verification,
        "code": {"code_commit": code["code_commit"], "code_dirty": code["code_dirty"],
                 "python": code["python"], "pyarrow": code["pyarrow"]},
    }
    manifest["manifest_body_sha256"] = body_sha256(manifest)
    # Execution facts are outside the hashed body: they legitimately differ per run.
    manifest["execution"] = {"computed_at": computed_at, "seconds": round(elapsed, 2), "parquet_file_sha256": file_sha}
    run_dir.mkdir(parents=True, exist_ok=True)
    (run_dir / f"{engine_id}__{mode.value}__{run_id}.json").write_text(json.dumps(manifest, indent=2, sort_keys=True) + "\n")
    return manifest
