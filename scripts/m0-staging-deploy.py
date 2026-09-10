#!/usr/bin/env python3
"""Bitmomo M0 staging deploy guard.

This script manages only the Bitmomo WordPress theme/plugin source under
website/wp-content. It refuses ambiguous targets, reports remote drift, and
uses a remote manifest so server hand edits abort later deploys instead of being
silently overwritten.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import shlex
import subprocess
import sys
import tarfile
import tempfile
from dataclasses import dataclass
from pathlib import Path


EXPECTED_STAGING_DOMAIN = "seagreen-snail-158456.hostingersite.com"
DEFAULT_HOST = "bitmomo-staging"
REMOTE_MANIFEST = ".bitmomo-m0-deploy-manifest.json"
MANAGED_DIRS = (
    "wp-content/plugins/bitmomo-ai",
    "wp-content/plugins/bitmomo-btc-intelligence",
    "wp-content/plugins/bitmomo-pro",
    "wp-content/plugins/bitmomo-regime",
    "wp-content/themes/bitmomo-child-v3",
)
IGNORED_DIRS = {".git", "__pycache__", "tests"}
IGNORED_FILES = {".DS_Store"}


@dataclass(frozen=True)
class RemoteScan:
    root: str
    files: dict[str, str]
    manifest: dict[str, str] | None


def repo_root() -> Path:
    return Path(__file__).resolve().parents[1]


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build_manifest() -> dict[str, str]:
    base = repo_root() / "website"
    manifest: dict[str, str] = {}
    for managed in MANAGED_DIRS:
        root = base / managed
        if not root.is_dir():
            raise SystemExit(f"missing managed source directory: website/{managed}")
        for path in sorted(root.rglob("*")):
            rel_parts = path.relative_to(base).parts
            if any(part in IGNORED_DIRS for part in rel_parts):
                continue
            if path.name in IGNORED_FILES:
                continue
            if path.is_file():
                manifest["/".join(rel_parts)] = sha256_file(path)
    return manifest


def load_classified_extras(path: Path) -> dict[str, dict[str, object]]:
    if not path.exists():
        return {}
    data = json.loads(path.read_text())
    result: dict[str, dict[str, object]] = {}
    for item in data.get("extras", []):
        rel = item.get("path")
        if isinstance(rel, str):
            result[rel] = item
    return result


def run(argv: list[str], *, input_bytes: bytes | None = None) -> subprocess.CompletedProcess[bytes]:
    return subprocess.run(argv, input=input_bytes, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)


def ssh(host: str, command: str) -> str:
    proc = run(["ssh", "-o", "BatchMode=yes", host, command])
    if proc.returncode != 0:
        sys.stderr.write(proc.stderr.decode("utf-8", "replace"))
        raise SystemExit(proc.returncode)
    return proc.stdout.decode("utf-8", "replace")


def remote_scan(host: str, document_root: str) -> RemoteScan:
    if not document_root.startswith("/"):
        raise SystemExit("document root must be an absolute path")

    managed_json = json.dumps(MANAGED_DIRS)
    remote = f"""
set -eu
root={shlex.quote(document_root)}
cd "$root"
test -d wp-content/plugins
test -d wp-content/themes
python3 - <<'PY'
import hashlib, json, os
root = os.getcwd()
managed = json.loads({managed_json!r})
manifest_path = os.path.join(root, {REMOTE_MANIFEST!r})
files = {{}}
for managed_dir in managed:
    if not os.path.isdir(managed_dir):
        continue
    for dirpath, dirnames, filenames in os.walk(managed_dir):
        dirnames[:] = sorted(d for d in dirnames if d not in ('.git', '__pycache__'))
        for name in sorted(filenames):
            rel = os.path.relpath(os.path.join(dirpath, name), root).replace(os.sep, '/')
            digest = hashlib.sha256()
            with open(os.path.join(root, rel), 'rb') as handle:
                for chunk in iter(lambda: handle.read(1024 * 1024), b''):
                    digest.update(chunk)
            files[rel] = digest.hexdigest()
remote_manifest = None
if os.path.exists(manifest_path):
    with open(manifest_path, 'r', encoding='utf-8') as handle:
        remote_manifest = json.load(handle).get('files')
print(json.dumps({{'root': root, 'files': files, 'manifest': remote_manifest}}, sort_keys=True))
PY
"""
    data = json.loads(ssh(host, remote))
    return RemoteScan(root=data["root"], files=data["files"], manifest=data.get("manifest"))


def compare(local: dict[str, str], remote: RemoteScan, classified: dict[str, dict[str, object]]) -> dict[str, object]:
    remote_files = remote.files
    previous = remote.manifest or {}
    missing = sorted(set(local) - set(remote_files))
    extras = sorted(set(remote_files) - set(local))
    changed = sorted(path for path, digest in local.items() if path in remote_files and remote_files[path] != digest)

    drift = []
    updates = []
    if remote.manifest:
        for path in changed:
            if previous.get(path) != remote_files[path]:
                drift.append(path)
            else:
                updates.append(path)
    else:
        drift = changed[:]

    classified_delete = []
    unclassified = []
    classified_mismatch = []
    for path in extras:
        item = classified.get(path)
        if not item or item.get("decision") != "delete":
            unclassified.append(path)
            continue
        expected_sha = item.get("sha256")
        if expected_sha and expected_sha != remote_files[path]:
            classified_mismatch.append(path)
            continue
        classified_delete.append(path)

    return {
        "missing": missing,
        "extras": extras,
        "changed": changed,
        "drift": drift,
        "updates": updates,
        "classified_delete": classified_delete,
        "unclassified_extras": unclassified,
        "classified_hash_mismatch": classified_mismatch,
        "remote_manifest_present": remote.manifest is not None,
    }


def print_report(report: dict[str, object], remote: RemoteScan) -> None:
    print(f"document_root={remote.root}")
    print(f"manifest_present={str(report['remote_manifest_present']).lower()}")
    for key in ("missing", "changed", "drift", "updates", "extras", "unclassified_extras", "classified_hash_mismatch", "classified_delete"):
        value = report[key]
        assert isinstance(value, list)
        print(f"{key}={len(value)}")
        for item in value:
            print(f"  {item}")


def make_tar(paths: list[str]) -> bytes:
    source_root = repo_root() / "website"
    with tempfile.NamedTemporaryFile() as tmp:
        with tarfile.open(tmp.name, "w") as archive:
            for rel in paths:
                archive.add(source_root / rel, arcname=rel)
        tmp.seek(0)
        return tmp.read()


def deploy(host: str, document_root: str, local: dict[str, str], report: dict[str, object], *, bootstrap: bool) -> None:
    drift = report["drift"]
    unclassified = report["unclassified_extras"]
    mismatch = report["classified_hash_mismatch"]
    if drift or unclassified or mismatch:
        raise SystemExit("abort: remote drift or unexplained extras detected")
    if report["changed"] and not report["remote_manifest_present"] and not bootstrap:
        raise SystemExit("abort: first deploy with changed files requires --bootstrap after recovery")

    paths_to_copy = sorted(set(report["missing"]) | set(report["updates"]))
    if paths_to_copy:
        tar_bytes = make_tar(paths_to_copy)
        proc = run(["ssh", "-o", "BatchMode=yes", host, f"tar -C {shlex.quote(document_root)} -xf -"], input_bytes=tar_bytes)
        if proc.returncode != 0:
            sys.stderr.write(proc.stderr.decode("utf-8", "replace"))
            raise SystemExit(proc.returncode)

    delete_paths = report["classified_delete"]
    assert isinstance(delete_paths, list)
    if delete_paths:
        quoted = " ".join(shlex.quote(str(path)) for path in delete_paths)
        ssh(host, f"cd {shlex.quote(document_root)} && rm -f -- {quoted}")

    payload = json.dumps(
        {
            "schema": 1,
            "site": EXPECTED_STAGING_DOMAIN,
            "managed_dirs": MANAGED_DIRS,
            "files": local,
        },
        sort_keys=True,
        indent=2,
    ).encode()
    proc = run(
        ["ssh", "-o", "BatchMode=yes", host, f"cat > {shlex.quote(document_root + '/' + REMOTE_MANIFEST)}"],
        input_bytes=payload,
    )
    if proc.returncode != 0:
        sys.stderr.write(proc.stderr.decode("utf-8", "replace"))
        raise SystemExit(proc.returncode)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Bitmomo M0 staging deploy guard")
    parser.add_argument("command", choices=("manifest", "dry-run", "deploy"))
    parser.add_argument("--host", default=os.environ.get("BITMOMO_STAGING_HOST", DEFAULT_HOST))
    parser.add_argument("--document-root", default=os.environ.get("BITMOMO_STAGING_ROOT"))
    parser.add_argument("--site", default=os.environ.get("BITMOMO_STAGING_SITE", EXPECTED_STAGING_DOMAIN))
    parser.add_argument("--classified-extras", default="config/m0-classified-extras.json")
    parser.add_argument("--bootstrap", action="store_true", help="allow writing the first remote manifest after recovery")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if args.site != EXPECTED_STAGING_DOMAIN:
        raise SystemExit(f"refusing non-staging site: {args.site}")

    local = build_manifest()
    if args.command == "manifest":
        print(json.dumps({"site": EXPECTED_STAGING_DOMAIN, "managed_dirs": MANAGED_DIRS, "files": local}, sort_keys=True, indent=2))
        return 0

    if not args.document_root:
        raise SystemExit("BITMOMO_STAGING_ROOT or --document-root is required")

    remote = remote_scan(args.host, args.document_root)
    classified = load_classified_extras(repo_root() / args.classified_extras)
    report = compare(local, remote, classified)
    print_report(report, remote)

    if args.command == "dry-run":
        return 0 if not report["drift"] and not report["unclassified_extras"] and not report["classified_hash_mismatch"] else 2

    deploy(args.host, args.document_root, local, report, bootstrap=args.bootstrap)
    post = remote_scan(args.host, args.document_root)
    post_report = compare(local, post, classified)
    print("post_deploy:")
    print_report(post_report, post)
    if post_report["missing"] or post_report["changed"] or post_report["extras"]:
        raise SystemExit("abort: post-deploy verification failed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
