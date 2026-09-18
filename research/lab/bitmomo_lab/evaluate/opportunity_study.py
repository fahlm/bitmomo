"""Opportunity V1 historical evaluation: records x settled outcomes -> machine-readable report.

The question is descriptive and out-of-sample: does Opportunity V1 separate future
movement, and under which conditions? No thresholds are tuned here; only baselines
are fitted, and only on development data or on prior walk-forward years.
"""

from __future__ import annotations

import collections
import datetime as dt
import json
import pathlib

import pyarrow as pa
import pyarrow.compute as pc

from bitmomo_lab.evaluate.metrics import auc_from_ranks, average_ranks, day_bootstrap_spread, strictly_ordered
from bitmomo_lab.evaluate.splits import DevelopmentSlice, EvaluationPlan, fit_quartile_thresholds, fixed_state
from bitmomo_lab.outcomes.settlement import horizon_label

STATES = ("HIGH", "NORMAL", "LOW")
SCORES = ("activity_percentile", "range_60m_pct", "abs_return_60m")


def _year(c: int) -> str:
    return str(dt.datetime.fromtimestamp(c, tz=dt.timezone.utc).year)


def _day(c: int) -> str:
    return dt.datetime.fromtimestamp(c, tz=dt.timezone.utc).strftime("%Y-%m-%d")


class Rows:
    """Column-wise joined evaluation rows (one per cutoff)."""

    def __init__(self, records: pa.Table, outcomes: pa.Table, abs_ret: list, vol: list,
                 horizons: list[int], thresholds: list[float]) -> None:
        records = records.sort_by([("event_time", "ascending")])
        outcomes = outcomes.sort_by([("event_time", "ascending")])
        rec_t = pc.cast(records["event_time"], pa.int64()).to_pylist()
        out_t = pc.cast(outcomes["event_time"], pa.int64()).to_pylist()
        if rec_t != out_t:
            raise ValueError("records and outcomes must cover identical cutoffs")
        self.cutoff = [t // 1_000_000 for t in rec_t]
        self.status = records["status"].to_pylist()
        self.error = records["error_code"].to_pylist()
        self.state = records["state"].to_pylist()
        self.score = {"activity_percentile": records["activity_percentile"].to_pylist(),
                      "range_60m_pct": records["range_60m_pct"].to_pylist(),
                      "abs_return_60m": abs_ret}
        self.vol = vol
        self.horizons = horizons
        self.thresholds = thresholds
        self.absexc = {h: outcomes[f"absexc_{horizon_label(h)}"].to_pylist() for h in horizons}
        self.complete = {h: outcomes[f"complete_{horizon_label(h)}"].to_pylist() for h in horizons}
        self.year = [_year(c) for c in self.cutoff]
        self.day = [_day(c) for c in self.cutoff]
        self.evaluable = [
            self.status[i] == "available" and all(self.complete[h][i] for h in horizons)
            for i in range(len(self.cutoff))
        ]

    def event(self, i: int, h: int, pct: float) -> bool:
        return self.absexc[h][i] >= pct / 100


def _cell_key(h: int, pct: float) -> str:
    return f"{horizon_label(h)}@{pct:.1f}%"


def _slice_report(rows: Rows, index: list[int], fixed: dict[str, tuple[float, float]] | None) -> dict:
    idx = [i for i in index if rows.evaluable[i]]
    report: dict = {"rows": len(index), "evaluable": len(idx), "distinct_days": len({rows.day[i] for i in idx})}
    if not idx:
        return report
    share = collections.Counter(rows.state[i] for i in idx)
    report["state_share"] = {s: round(share[s] / len(idx), 6) for s in STATES}
    ranks = {}
    for name in SCORES:
        usable = [i for i in idx if rows.score[name][i] is not None]
        ranks[name] = (usable, average_ranks([rows.score[name][i] for i in usable]))
    fixed_states = None
    if fixed:
        fixed_states = [fixed_state(rows.score["range_60m_pct"][i], fixed["range_60m_pct"]) for i in idx]
        fshare = collections.Counter(fixed_states)
        report["fixed_quartile_state_share"] = {s: round(fshare[s] / len(idx), 6) for s in STATES}
    cells = {}
    state_of = [rows.state[i] for i in idx]
    position = {i: k for k, i in enumerate(idx)}
    for h in rows.horizons:
        for pct in rows.thresholds:
            events = [rows.event(i, h, pct) for i in idx]
            by_state = {}
            for s in STATES:
                n = sum(1 for st in state_of if st == s)
                hits = sum(1 for st, e in zip(state_of, events) if st == s and e)
                by_state[s] = {"n": n, "events": hits, "rate": hits / n if n else None}
            cell = {
                "base_rate": sum(events) / len(events),
                "by_state": by_state,
                "ordered_high_normal_low": strictly_ordered([by_state[s]["rate"] for s in STATES]),
                "high_minus_low_pp": (by_state["HIGH"]["rate"] - by_state["LOW"]["rate"]) * 100
                if by_state["HIGH"]["rate"] is not None and by_state["LOW"]["rate"] is not None else None,
                "auc": {name: auc_from_ranks(r, [events[position[i]] for i in usable])
                        for name, (usable, r) in ranks.items()},
            }
            if fixed_states is not None:
                fixed_by_state = {}
                for s in STATES:
                    n = sum(1 for fs in fixed_states if fs == s)
                    hits = sum(1 for fs, e in zip(fixed_states, events) if fs == s and e)
                    fixed_by_state[s] = {"n": n, "rate": hits / n if n else None}
                cell["fixed_quartile_baseline"] = {
                    "by_state": fixed_by_state,
                    "ordered_high_normal_low": strictly_ordered([fixed_by_state[s]["rate"] for s in STATES]),
                }
            cells[_cell_key(h, pct)] = cell
    report["cells"] = cells
    return report


def run_study(records: pa.Table, outcomes: pa.Table, abs_ret: list, vol: list, plan: EvaluationPlan,
              config: dict, p02c_reference: dict) -> dict:
    horizons = config["horizons_minutes"]
    thresholds = config["excursion_thresholds_pct"]
    rows = Rows(records, outcomes, abs_ret, vol, horizons, thresholds)
    everything = list(range(len(rows.cutoff)))

    # Baseline fit: development only (DevelopmentSlice enforces the purge boundary).
    dev: DevelopmentSlice = plan.development_slice(rows.cutoff, [
        rows.score["range_60m_pct"][i] if rows.evaluable[i] else None for i in everything])
    fixed = {"range_60m_pct": fit_quartile_thresholds(dev)}

    coverage = {
        "cutoffs": len(everything),
        "engine_status": dict(sorted(collections.Counter(
            rows.state[i] if rows.status[i] == "available" else rows.error[i] for i in everything).items())),
        "outcome_incomplete_by_horizon": {horizon_label(h): sum(not c for c in rows.complete[h]) for h in horizons},
        "evaluable": sum(rows.evaluable),
        "vol_context_unavailable_among_evaluable": sum(1 for i in everything if rows.evaluable[i] and rows.vol[i] is None),
        "abs_return_60m_missing_among_evaluable": sum(1 for i in everything if rows.evaluable[i] and rows.score["abs_return_60m"][i] is None),
    }

    slices: dict[str, dict] = {"full": _slice_report(rows, everything, fixed)}
    for seg in plan.segments:
        mask = plan.member_mask(rows.cutoff, seg.name)
        slices[f"segment:{seg.name}"] = _slice_report(rows, [i for i in everything if mask[i]], fixed)
    for year in sorted(set(rows.year)):
        slices[f"year:{year}"] = _slice_report(rows, [i for i in everything if rows.year[i] == year], fixed)
    holdout_mask = plan.member_mask(rows.cutoff, "holdout")
    for bucket in ("low", "normal", "high"):
        slices[f"vol:{bucket}"] = _slice_report(rows, [i for i in everything if rows.vol[i] == bucket], None)
        slices[f"holdout×vol:{bucket}"] = _slice_report(
            rows, [i for i in everything if rows.vol[i] == bucket and holdout_mask[i]], None)

    # Walk-forward: baselines refit on all prior (purged) years; Opportunity itself is untrained.
    walk_forward = []
    for fold in plan.walk_forward_folds(rows.cutoff):
        train = DevelopmentSlice(
            [(rows.cutoff[i], rows.score["range_60m_pct"][i]) for i in everything
             if fold["train_mask"][i] and rows.evaluable[i]], fold["train_end"], plan.purge_seconds)
        fold_fixed = {"range_60m_pct": fit_quartile_thresholds(train)}
        test = [i for i in everything if fold["test_mask"][i]]
        report = _slice_report(rows, test, fold_fixed)
        walk_forward.append({"test_year": fold["test_year"], "train_rows": len(train.values),
                             "fixed_thresholds_range_60m_pct": list(fold_fixed["range_60m_pct"]), "test": report})

    primary = config["primary_cell"]
    key = _cell_key(primary["horizon_minutes"], primary["threshold_pct"])
    bootstrap = {}
    for name in ("full", "segment:development", "segment:validation", "segment:holdout"):
        mask = [True] * len(everything) if name == "full" else plan.member_mask(rows.cutoff, name.split(":")[1])
        counts: dict[str, list[int]] = collections.defaultdict(lambda: [0, 0, 0, 0])
        for i in everything:
            if mask[i] and rows.evaluable[i] and rows.state[i] in ("HIGH", "LOW"):
                event = rows.event(i, primary["horizon_minutes"], primary["threshold_pct"])
                slot = 0 if rows.state[i] == "HIGH" else 2
                counts[rows.day[i]][slot] += event
                counts[rows.day[i]][slot + 1] += 1
        bootstrap[name] = day_bootstrap_spread({d: tuple(v) for d, v in counts.items()},
                                               config["bootstrap"]["resamples"], config["bootstrap"]["seed"])

    return {
        "evaluation_id": config["evaluation_id"],
        "config": config,
        "fixed_quartile_thresholds_development": {"range_60m_pct": list(fixed["range_60m_pct"])},
        "coverage": coverage,
        "slices": slices,
        "walk_forward": walk_forward,
        "bootstrap_high_minus_low_primary_cell": {"cell": key, **bootstrap},
        "hypotheses": evaluate_hypotheses(slices, key, p02c_reference),
        "p02c_comparison": compare_p02c(slices, p02c_reference),
        "cells_evaluated_per_slice": len(horizons) * len(thresholds),
        "slices_evaluated": len(slices),
    }


def _cell(slices: dict, name: str, key: str) -> dict:
    return slices[name]["cells"][key]


def compare_p02c(slices: dict, reference: dict) -> dict:
    rows = []
    for state, by_h in reference["full_sample_pct"].items():
        for h, published in by_h.items():
            ours = _cell(slices, "full", _cell_key(int(h), 0.3))["by_state"][state]["rate"] * 100
            rows.append({"table": "full_sample", "state": state, "horizon_minutes": int(h),
                         "published_pct": published, "platform_pct": round(ours, 2),
                         "diff_pp": round(ours - published, 2)})
    for year, by_state in reference["yearly_60m_pct"].items():
        if f"year:{year}" not in slices:
            continue
        for state, published in by_state.items():
            ours = _cell(slices, f"year:{year}", _cell_key(60, 0.3))["by_state"][state]["rate"] * 100
            rows.append({"table": "yearly_60m", "year": year, "state": state, "published_pct": published,
                         "platform_pct": round(ours, 2), "diff_pp": round(ours - published, 2)})
    return {"rows": rows, "max_abs_diff_pp": max(abs(r["diff_pp"]) for r in rows),
            "within_1pp": sum(abs(r["diff_pp"]) <= 1.0 for r in rows), "total": len(rows)}


def evaluate_hypotheses(slices: dict, key: str, reference: dict) -> list[dict]:
    hold = _cell(slices, "segment:holdout", key)
    years = sorted(k for k in slices if k.startswith("year:"))
    h1_years = {y: _cell(slices, y, key)["ordered_high_normal_low"] for y in years}
    h1 = hold["ordered_high_normal_low"] and all(h1_years.values())
    share = slices["segment:holdout"]["state_share"]
    fshare = slices["segment:holdout"]["fixed_quartile_state_share"]
    drift_adaptive = abs(share["HIGH"] - 0.25) + abs(share["LOW"] - 0.25)
    drift_fixed = abs(fshare["HIGH"] - 0.25) + abs(fshare["LOW"] - 0.25)
    vol_order = {b: _cell(slices, f"holdout×vol:{b}", key)["ordered_high_normal_low"]
                 for b in ("low", "normal", "high") if "cells" in slices[f"holdout×vol:{b}"]}
    comparison = compare_p02c(slices, reference)
    return [
        {"id": "H-OPP-001", "result": "PASS" if h1 else "FAIL",
         "evidence": {"holdout_ordered": hold["ordered_high_normal_low"], "ordered_by_year": h1_years}},
        {"id": "H-OPP-002", "result": "PASS" if hold["auc"]["activity_percentile"] >= 0.70 else "FAIL",
         "evidence": {"holdout_auc": hold["auc"]["activity_percentile"]}},
        {"id": "H-OPP-003a",
         "result": "PASS" if hold["auc"]["activity_percentile"] >= hold["auc"]["range_60m_pct"] - 0.01 else "FAIL",
         "evidence": {"auc_activity_percentile": hold["auc"]["activity_percentile"],
                      "auc_range_60m_pct": hold["auc"]["range_60m_pct"]}},
        {"id": "H-OPP-003b", "result": "PASS" if drift_adaptive < drift_fixed else "FAIL",
         "evidence": {"holdout_share_adaptive": share, "holdout_share_fixed": fshare,
                      "drift_adaptive": drift_adaptive, "drift_fixed": drift_fixed}},
        {"id": "H-OPP-004",
         "result": "PASS" if hold["auc"]["activity_percentile"] > hold["auc"]["abs_return_60m"] else "FAIL",
         "evidence": {"auc_activity_percentile": hold["auc"]["activity_percentile"],
                      "auc_abs_return_60m": hold["auc"]["abs_return_60m"]}},
        {"id": "H-OPP-005", "result": "PASS" if vol_order and all(vol_order.values()) and len(vol_order) == 3 else "FAIL",
         "evidence": {"holdout_ordered_by_vol_bucket": vol_order}},
        {"id": "H-OPP-006", "result": "PASS" if comparison["within_1pp"] == comparison["total"] else "FAIL",
         "evidence": {"within_1pp": comparison["within_1pp"], "total": comparison["total"],
                      "max_abs_diff_pp": comparison["max_abs_diff_pp"]}},
    ]


def load_json(path: pathlib.Path) -> dict:
    return json.loads(path.read_text())
