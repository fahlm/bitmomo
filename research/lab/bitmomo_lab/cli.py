"""bitmomo-lab command line (research only; manual invocation).

  bitmomo-lab build    --dataset binance_um_klines_5m --start 2020-01-01 --end 2026-09-10
  bitmomo-lab verify   --manifest manifests/<name>.json
  bitmomo-lab equivalence --metrics-manifest ... --klines-manifest ...
  bitmomo-lab replay   --manifest manifests/<um klines>.json --start ... --end ...
  bitmomo-lab opportunity-study [--config config/opportunity_v1_evaluation.json]
  bitmomo-lab coverage --prefix data/futures/um/daily/metrics/BTCUSDT/
  bitmomo-lab record   [--fixtures DIR] [--capture-fixtures DIR]
  bitmomo-lab recorder-gaps --endpoint taker_long_short_ratio_5m --start ... --end ...
"""

from __future__ import annotations

import argparse
import dataclasses
import datetime as dt
import json
import pathlib
import sys

from bitmomo_lab import build as build_mod
from bitmomo_lab.recorder import recorder
from bitmomo_lab.recorder.endpoints import default_endpoints
from bitmomo_lab.sources import coverage, registry
from bitmomo_lab.store.pit import load_verified
from bitmomo_lab.validate import equivalence


def _date(text: str) -> dt.date:
    return dt.date.fromisoformat(text)


def _utc(text: str) -> dt.datetime:
    value = dt.datetime.fromisoformat(text.replace("Z", "+00:00"))
    return value if value.tzinfo else value.replace(tzinfo=dt.timezone.utc)


def cmd_build(args: argparse.Namespace) -> int:
    manifest = build_mod.build_dataset(args.dataset, args.symbol, args.start, args.end,
                                       pathlib.Path(args.data_dir), pathlib.Path(args.manifest_dir))
    q = manifest["quality"]
    summary = {k: q.get(k) for k in ("rows", "first_event_time", "last_event_time", "expected_intervals",
                                     "missing_intervals", "rows_by_quality")}
    summary.update(content_sha256=manifest["normalized"]["content_sha256"],
                   missing_archive_files=len(manifest["missing_archive_files"]),
                   assembly=manifest["assembly"])
    print(json.dumps(summary, indent=2))
    return 0


def cmd_verify(args: argparse.Namespace) -> int:
    manifest = json.loads(pathlib.Path(args.manifest).read_text())
    if build_mod.body_sha256(manifest) != manifest["manifest_body_sha256"]:
        print("FAIL manifest body hash mismatch (manifest edited?)")
        return 1
    path = pathlib.Path(args.data_dir) / manifest["normalized"]["relative_path"]
    frame = load_verified(path, manifest["normalized"]["content_sha256"], manifest["dataset"])
    print(f"PASS {manifest['dataset']} rows={frame.num_rows} content_sha256={manifest['normalized']['content_sha256']}")
    return 0


def cmd_equivalence(args: argparse.Namespace) -> int:
    report = equivalence.metrics_vs_klines(pathlib.Path(args.metrics_manifest), pathlib.Path(args.klines_manifest),
                                           pathlib.Path(args.data_dir))
    print(json.dumps(report, indent=2, sort_keys=True))
    return 0


def cmd_replay(args: argparse.Namespace) -> int:
    from bitmomo_lab.engines.contract import Mode
    from bitmomo_lab.replay.runner import run_replay

    manifest = run_replay(args.engine, [pathlib.Path(m) for m in args.manifest], _utc(args.start), _utc(args.end),
                          args.cadence_seconds, Mode(args.mode), pathlib.Path(args.data_dir))
    print(json.dumps({k: manifest[k] for k in ("run_id", "mode", "window", "outcome_counts", "output", "execution")},
                     indent=2))
    return 0


def cmd_opportunity_study(args: argparse.Namespace) -> int:
    from bitmomo_lab.evaluate.pipeline import opportunity_study

    result = opportunity_study(pathlib.Path(args.config), pathlib.Path(args.data_dir))
    print(json.dumps({"run_id": result["run"]["run_id"], "report": result["report_path"],
                      "timings": result["timings"],
                      "hypotheses": {h["id"]: h["result"] for h in result["hypotheses"]}}, indent=2))
    return 0


def cmd_coverage(args: argparse.Namespace) -> int:
    print(json.dumps(coverage.series_coverage(args.prefix), indent=2))
    return 0


class _CapturingTransport:
    def __init__(self, inner, directory: pathlib.Path) -> None:
        self.inner, self.directory = inner, directory
        self.names = {(e.path, e.period): e.name for e in default_endpoints()}

    def get(self, path, params):
        status, body = self.inner.get(path, params)
        self.directory.mkdir(parents=True, exist_ok=True)
        name = self.names[(path, params.get("period"))]
        (self.directory / f"{name}.json").write_text(json.dumps({
            "provenance": f"live capture {dt.datetime.now(dt.timezone.utc).isoformat()} {recorder.build_url(path, params)}",
            "status": status, "body": json.loads(body) if status == 200 else body.decode(errors="replace"),
        }, indent=2) + "\n")
        return status, body


def cmd_record(args: argparse.Namespace) -> int:
    endpoints = default_endpoints()
    if args.endpoint:
        endpoints = [e for e in endpoints if e.name in set(args.endpoint)]
    transport = (recorder.FixtureTransport(pathlib.Path(args.fixtures), endpoints)
                 if args.fixtures else recorder.UrllibTransport())
    if args.capture_fixtures:
        transport = _CapturingTransport(transport, pathlib.Path(args.capture_fixtures))
    outcomes = recorder.record_once(args.symbol, transport, pathlib.Path(args.data_dir), endpoints)
    for outcome in outcomes:
        print(json.dumps(dataclasses.asdict(outcome)))
    failed = [o for o in outcomes if o.status in ("rejected", "http_error")]
    return 1 if failed else 0


def cmd_recorder_gaps(args: argparse.Namespace) -> int:
    endpoint = next(e for e in default_endpoints() if e.name == args.endpoint)
    report = recorder.recorded_gaps(pathlib.Path(args.data_dir), endpoint, args.symbol,
                                    _utc(args.start), _utc(args.end))
    print(json.dumps(report, indent=2))
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="bitmomo-lab")
    parser.add_argument("--data-dir", default=str(build_mod.DEFAULT_DATA_DIR))
    sub = parser.add_subparsers(dest="command", required=True)

    b = sub.add_parser("build", help="acquire + normalize + validate + manifest one dataset window")
    b.add_argument("--dataset", required=True, choices=sorted(registry.DATASETS))
    b.add_argument("--symbol", default="BTCUSDT")
    b.add_argument("--start", required=True, type=_date)
    b.add_argument("--end", required=True, type=_date, help="inclusive UTC date")
    b.add_argument("--manifest-dir", default=str(build_mod.DEFAULT_MANIFEST_DIR))
    b.set_defaults(func=cmd_build)

    v = sub.add_parser("verify", help="re-hash a stored dataset against its manifest")
    v.add_argument("--manifest", required=True)
    v.set_defaults(func=cmd_verify)

    c = sub.add_parser("coverage", help="list archive coverage for an S3 prefix")
    c.add_argument("--prefix", required=True)
    c.set_defaults(func=cmd_coverage)

    rp = sub.add_parser("replay", help="run an engine over a manifest window (immutable records)")
    rp.add_argument("--engine", default="opportunity-v1-py")
    rp.add_argument("--manifest", action="append", required=True)
    rp.add_argument("--start", required=True)
    rp.add_argument("--end", required=True, help="inclusive last cutoff (UTC)")
    rp.add_argument("--cadence-seconds", type=int, default=900)
    rp.add_argument("--mode", default="strict", choices=["strict", "production_parity"])
    rp.set_defaults(func=cmd_replay)

    st = sub.add_parser("opportunity-study", help="manifest -> replay -> settle -> evaluate -> reports")
    st.add_argument("--config", default=str(build_mod.LAB_ROOT / "config" / "opportunity_v1_evaluation.json"))
    st.set_defaults(func=cmd_opportunity_study)

    e = sub.add_parser("equivalence", help="empirical metrics-archive vs kline semantics check")
    e.add_argument("--metrics-manifest", required=True)
    e.add_argument("--klines-manifest", required=True)
    e.set_defaults(func=cmd_equivalence)

    r = sub.add_parser("record", help="one manual forward-recorder pass (public endpoints only)")
    r.add_argument("--symbol", default="BTCUSDT")
    r.add_argument("--endpoint", action="append", help="limit to endpoint name(s)")
    r.add_argument("--fixtures", help="replay recorded responses from this directory instead of HTTP")
    r.add_argument("--capture-fixtures", help="also save each response as a test fixture here")
    r.set_defaults(func=cmd_record)

    g = sub.add_parser("recorder-gaps", help="missing periods in a recorded series")
    g.add_argument("--endpoint", required=True)
    g.add_argument("--symbol", default="BTCUSDT")
    g.add_argument("--start", required=True)
    g.add_argument("--end", required=True)
    g.set_defaults(func=cmd_recorder_gaps)

    args = parser.parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
