"""End-to-end Opportunity V1 study: manifest -> replay -> settlement -> evaluation -> reports."""

from __future__ import annotations

import datetime as dt
import json
import pathlib
import time

from bitmomo_lab.build import DEFAULT_DATA_DIR, LAB_ROOT
from bitmomo_lab.engines.contract import Mode
from bitmomo_lab.evaluate import report as report_mod
from bitmomo_lab.evaluate.opportunity_study import load_json, run_study
from bitmomo_lab.evaluate.splits import EvaluationPlan
from bitmomo_lab.features import context
from bitmomo_lab.outcomes.settlement import settle
from bitmomo_lab.replay.runner import DEFAULT_RUN_DIR, load_input, run_replay
from bitmomo_lab.store.parquet import read_dataset, write_dataset

REPO_ROOT = LAB_ROOT.parents[1]
DEFAULT_RESULTS_DIR = REPO_ROOT / "docs" / "intelligence-platform" / "results"


def _utc(text: str) -> dt.datetime:
    return dt.datetime.fromisoformat(text.replace("Z", "+00:00"))


def opportunity_study(config_path: pathlib.Path, data_dir: pathlib.Path = DEFAULT_DATA_DIR,
                      run_dir: pathlib.Path = DEFAULT_RUN_DIR,
                      results_dir: pathlib.Path = DEFAULT_RESULTS_DIR) -> dict:
    timings: dict[str, float] = {}
    t0 = time.time()
    config = load_json(config_path)
    manifest_path = LAB_ROOT / config["input_manifest"]
    run = run_replay(config["engine_id"], [manifest_path], _utc(config["replay_start"]),
                     _utc(config["replay_end_inclusive"]), config["cadence_seconds"], Mode(config["mode"]),
                     data_dir, run_dir)
    timings["replay_seconds"] = round(time.time() - t0, 1)

    t1 = time.time()
    records = read_dataset(data_dir / run["output"]["relative_path"])
    cutoffs = [int(v.timestamp()) for v in records.sort_by([("event_time", "ascending")])["event_time"].to_pylist()]
    _, klines = load_input(manifest_path, data_dir)
    outcomes = settle(klines, cutoffs, config["horizons_minutes"], config["excursion_thresholds_pct"],
                      config["entry"]["delay_seconds"])
    outcome_path = data_dir / "outcomes" / f"{config['evaluation_id']}__{run['run_id']}.parquet"
    outcome_hash, _ = write_dataset(outcomes, outcome_path)
    timings["settlement_seconds"] = round(time.time() - t1, 1)

    t2 = time.time()
    abs_ret = context.abs_return_60m(klines, cutoffs)
    vol = context.vol_context(klines, cutoffs)
    plan = EvaluationPlan.load(config_path)
    reference = load_json(LAB_ROOT / "registry" / "references" / "p02c_opportunity_event_rates.json")
    report = run_study(records, outcomes, abs_ret, vol, plan, config, reference)
    timings["evaluation_seconds"] = round(time.time() - t2, 1)

    report["lineage"] = {
        "run_id": run["run_id"],
        "run_manifest_body_sha256": run["manifest_body_sha256"],
        "records_logical_content_sha256": run["output"]["logical_content_sha256"],
        "outcomes_content_sha256": outcome_hash,
        "input": run["inputs"],
        "features": {"abs_return_60m": context.ABS_RETURN_60M[1], "vol_context_v1": context.VOL_CONTEXT[1]},
        "code": run["code"],
    }
    results_dir.mkdir(parents=True, exist_ok=True)
    name = config["evaluation_id"]
    (results_dir / f"{name}.json").write_text(json.dumps(report, indent=1, sort_keys=True) + "\n")
    markdown = report_mod.render(report, run, {"content_sha256": outcome_hash})
    (results_dir.parent / "M1_OPPORTUNITY_V1_RESULTS.md").write_text(markdown)
    timings["total_seconds"] = round(time.time() - t0, 1)
    return {"run": run, "report_path": str(results_dir / f"{name}.json"), "timings": timings,
            "hypotheses": report["hypotheses"]}
