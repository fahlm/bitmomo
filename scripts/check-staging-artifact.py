#!/usr/bin/env python3
"""Compare the canonical runtime artifact manifest with Bitmomo staging."""

from __future__ import annotations

import argparse
import json
import shlex
import subprocess
import sys
from pathlib import Path


EXPECTED_SITE = "https://seagreen-snail-158456.hostingersite.com"
DEFAULT_HOST = "bitmomo-staging"
DEFAULT_ROOT = "/home/u689746960/domains/seagreen-snail-158456.hostingersite.com/public_html"


def ssh(host: str, command: str) -> str:
    result = subprocess.run(
        ["ssh", host, command], check=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True
    )
    if result.returncode:
        sys.stderr.write(result.stderr)
        raise SystemExit(result.returncode)
    return result.stdout


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("manifest", type=Path)
    parser.add_argument("--host", default=DEFAULT_HOST)
    parser.add_argument("--root", default=DEFAULT_ROOT)
    parser.add_argument("--expected-commit", default="", help="Exact candidate commit expected in the artifact manifest")
    parser.add_argument("--expected-tree", default="", help="Exact candidate git tree expected in the artifact manifest")
    parser.add_argument(
        "--expected-artifact-sha256",
        default="",
        help="Expected runtime TAR SHA-256 recorded in the artifact manifest",
    )
    args = parser.parse_args()

    if "/domains/seagreen-snail-158456.hostingersite.com/public_html" not in args.root:
        raise SystemExit("refusing non-staging root")

    manifest = json.loads(args.manifest.read_text())
    expected = {item["path"]: item["sha256"] for item in manifest.get("files", [])}
    if manifest.get("file_count") != len(expected) or any("/tests/" in path for path in expected):
        raise SystemExit("invalid runtime manifest")

    source_commit = str(manifest.get("source_commit", ""))
    source_tree = str(manifest.get("source_tree", ""))
    artifact_sha256 = str(manifest.get("artifact_sha256", ""))
    if args.expected_commit and source_commit != args.expected_commit:
        raise SystemExit(
            f"artifact candidate mismatch: manifest source_commit={source_commit} expected={args.expected_commit}"
        )
    if args.expected_tree and source_tree != args.expected_tree:
        raise SystemExit(f"artifact tree mismatch: manifest source_tree={source_tree} expected={args.expected_tree}")
    if args.expected_artifact_sha256 and artifact_sha256 != args.expected_artifact_sha256:
        raise SystemExit(
            "artifact digest mismatch: "
            f"manifest artifact_sha256={artifact_sha256} expected={args.expected_artifact_sha256}"
        )

    managed = sorted({"/".join(path.split("/")[:3]) for path in expected})
    remote_program = f"""
import hashlib, json, os
root = {args.root!r}
managed = json.loads({json.dumps(managed)!r})
files = {{}}
for directory in managed:
    absolute = os.path.join(root, directory)
    if not os.path.isdir(absolute):
        continue
    for dirpath, dirnames, filenames in os.walk(absolute):
        dirnames[:] = sorted(d for d in dirnames if d not in ('.git', '__pycache__'))
        for name in sorted(filenames):
            path = os.path.join(dirpath, name)
            rel = os.path.relpath(path, root).replace(os.sep, '/')
            digest = hashlib.sha256()
            with open(path, 'rb') as handle:
                for chunk in iter(lambda: handle.read(1024 * 1024), b''):
                    digest.update(chunk)
            files[rel] = digest.hexdigest()
print(json.dumps(files, sort_keys=True))
"""
    site = ssh(args.host, f"wp option get siteurl --path={shlex.quote(args.root)}").strip()
    if site != EXPECTED_SITE:
        raise SystemExit(f"refusing unexpected site: {site}")
    remote = json.loads(ssh(args.host, "python3 - <<'PY'\n" + remote_program + "PY\n"))

    missing = sorted(set(expected) - set(remote))
    unexpected = sorted(set(remote) - set(expected))
    changed = sorted(path for path in set(expected) & set(remote) if expected[path] != remote[path])
    print(f"source_commit={source_commit}")
    print(f"source_tree={source_tree}")
    print(f"artifact_sha256={artifact_sha256}")
    for name, paths in (("missing", missing), ("changed", changed), ("unexpected", unexpected)):
        print(f"{name}={len(paths)}")
        for path in paths:
            print(f"  {path}")
    return 0 if not missing and not changed and not unexpected else 1


if __name__ == "__main__":
    raise SystemExit(main())
