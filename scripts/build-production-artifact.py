#!/usr/bin/env python3
"""Build the deterministic, runtime-only Bitmomo production artifact."""

from __future__ import annotations

import argparse
import fnmatch
import hashlib
import json
import subprocess
import tarfile
from datetime import datetime, timezone
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
CONFIG_PATH = ROOT / "config" / "production-runtime.json"


def digest(path: Path) -> str:
    value = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            value.update(chunk)
    return value.hexdigest()


def git(*args: str) -> str:
    return subprocess.check_output(["git", "-C", str(ROOT), *args], text=True).strip()


def matches(path: str, patterns: list[str]) -> bool:
    return any(fnmatch.fnmatchcase(path, pattern) for pattern in patterns)


def collect(config: dict) -> tuple[list[dict], list[dict]]:
    included: list[dict] = []
    excluded: list[dict] = []
    unexpected: list[str] = []

    for component in config["components"]:
        source = ROOT / component["source"]
        if not source.is_dir():
            raise SystemExit(f"missing component: {component['source']}")

        component_files: list[str] = []
        for path in sorted(item for item in source.rglob("*") if item.is_file()):
            relative = path.relative_to(source).as_posix()
            source_relative = path.relative_to(ROOT).as_posix()
            if matches(relative, config["exclude"]):
                excluded.append({"path": source_relative, "rule": "explicit exclusion"})
                continue
            if not matches(relative, config["include"]):
                unexpected.append(source_relative)
                continue

            artifact_path = f"{component['destination']}/{relative}"
            component_files.append(relative)
            included.append(
                {
                    "source": path,
                    "path": artifact_path,
                    "sha256": digest(path),
                    "size": path.stat().st_size,
                }
            )

        missing = sorted(set(component["required"]) - set(component_files))
        if missing:
            raise SystemExit(f"{component['name']}: missing required runtime files: {', '.join(missing)}")
        if len(component_files) != component["expected_file_count"]:
            raise SystemExit(
                f"{component['name']}: expected {component['expected_file_count']} runtime files, found {len(component_files)}"
            )

    if unexpected:
        raise SystemExit("unexpected managed source files:\n  " + "\n  ".join(unexpected))
    if len(included) != config["expected_file_count"]:
        raise SystemExit(f"expected {config['expected_file_count']} runtime files, found {len(included)}")
    if any("/tests/" in f"/{item['path']}/" for item in included):
        raise SystemExit("tests must not appear in the production artifact")
    return sorted(included, key=lambda item: item["path"]), excluded


def write_tar(path: Path, files: list[dict], source_epoch: int) -> None:
    with tarfile.open(path, "w", format=tarfile.PAX_FORMAT) as archive:
        for item in files:
            data = item["source"].read_bytes()
            info = tarfile.TarInfo(item["path"])
            info.size = len(data)
            info.mode = 0o644
            info.uid = 0
            info.gid = 0
            info.uname = "root"
            info.gname = "root"
            info.mtime = source_epoch
            import io

            archive.addfile(info, io.BytesIO(data))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-dir", default="dist")
    parser.add_argument("--allow-dirty", action="store_true")
    args = parser.parse_args()

    if not args.allow_dirty and git("status", "--porcelain", "--untracked-files=all"):
        raise SystemExit("refusing to build from a dirty checkout; commit or use --allow-dirty for local inspection")

    config = json.loads(CONFIG_PATH.read_text())
    files, exclusions = collect(config)
    commit = git("rev-parse", "HEAD")
    source_epoch = int(git("show", "-s", "--format=%ct", "HEAD"))
    output = (ROOT / args.output_dir).resolve()
    output.mkdir(parents=True, exist_ok=True)
    artifact = output / config["artifact_name"]
    write_tar(artifact, files, source_epoch)

    manifest = {
        "schema": 1,
        "source_commit": commit,
        "source_date_epoch": source_epoch,
        "build_timestamp": datetime.now(timezone.utc).isoformat(),
        "packaging_definition": str(CONFIG_PATH.relative_to(ROOT)),
        "packaging_definition_sha256": digest(CONFIG_PATH),
        "artifact": artifact.name,
        "artifact_sha256": digest(artifact),
        "file_count": len(files),
        "files": [{key: item[key] for key in ("path", "sha256", "size")} for item in files],
        "exclusions": sorted(exclusions, key=lambda item: item["path"]),
    }
    manifest_path = output / "bitmomo-runtime-manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2, sort_keys=True) + "\n")
    print(f"source_commit={commit}")
    print(f"runtime_files={len(files)}")
    print(f"excluded_source_files={len(exclusions)}")
    print(f"artifact_sha256={manifest['artifact_sha256']}")
    print(f"manifest={manifest_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
