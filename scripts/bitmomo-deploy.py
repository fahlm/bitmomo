#!/usr/bin/env python3
"""Bitmomo guarded two-target deploy/dry-run."""
from __future__ import annotations
import argparse, hashlib, json, os, shlex, subprocess, sys, tarfile, tempfile
from pathlib import Path

MANAGED_DIRS = (
    "wp-content/plugins/bitmomo-ai",
    "wp-content/plugins/bitmomo-btc-intelligence",
    "wp-content/plugins/bitmomo-pro",
    "wp-content/plugins/bitmomo-regime",
    "wp-content/themes/bitmomo-child-v3",
)
IGNORE_DIRS = {".git", "__pycache__", "tests"}
IGNORE_FILES = {".DS_Store"}
PRODUCTION_CONFIRMATION = "I UNDERSTAND THIS WRITES BITMOMO PRODUCTION"


def root() -> Path:
    return Path(__file__).resolve().parents[1]


def load_target(name: str) -> dict:
    path = root() / "config" / "deploy-targets" / f"{name}.json"
    if not path.exists():
        raise SystemExit(f"unknown target: {name}")
    return json.loads(path.read_text())


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def local_manifest() -> dict[str, str]:
    base = root() / "website"
    files: dict[str, str] = {}
    for managed in MANAGED_DIRS:
        src = base / managed
        if not src.is_dir():
            raise SystemExit(f"missing managed source directory: website/{managed}")
        for path in sorted(src.rglob("*")):
            rel_parts = path.relative_to(base).parts
            if path.is_file() and path.name not in IGNORE_FILES and not any(part in IGNORE_DIRS for part in rel_parts):
                files["/".join(rel_parts)] = sha256(path)
    return files


def run(argv: list[str], data: bytes | None = None) -> subprocess.CompletedProcess:
    return subprocess.run(argv, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)


def ssh(host: str, command: str) -> str:
    p = run(["ssh", "-o", "BatchMode=yes", host, command])
    if p.returncode:
        sys.stderr.write(p.stderr.decode("utf-8", "replace"))
        raise SystemExit(p.returncode)
    return p.stdout.decode("utf-8", "replace")


def host_and_root(target: dict) -> tuple[str, str]:
    host = os.environ.get(target["host_env"]) or target.get("default_host")
    docroot = os.environ.get(target["root_env"]) or target.get("default_root")
    if not host:
        raise SystemExit(f"{target['host_env']} is required")
    if not docroot or not str(docroot).startswith("/"):
        raise SystemExit(f"{target['root_env']} must be an absolute path")
    return str(host), str(docroot)


def previous_manifest(target: dict, remote_manifest: dict | None) -> dict[str, str] | None:
    if target.get("baseline_manifest"):
        data = json.loads((root() / target["baseline_manifest"]).read_text())
        return {str(k): str(v) for k, v in data.get("files", data).items()}
    if isinstance(remote_manifest, dict) and isinstance(remote_manifest.get("files"), dict):
        return {str(k): str(v) for k, v in remote_manifest["files"].items()}
    return None


def scan_remote(host: str, docroot: str, target: dict) -> tuple[dict[str, str], dict[str, str] | None]:
    managed = json.dumps(MANAGED_DIRS)
    remote_manifest = target["remote_manifest"]
    script = f'''
set -eu
cd {shlex.quote(docroot)}
test -d wp-content/plugins
test -d wp-content/themes
python3 - <<'REMOTE'
import hashlib, json, os
managed=json.loads({managed!r}); files={{}}
for m in managed:
    if not os.path.isdir(m): continue
    for dirpath, dirnames, filenames in os.walk(m):
        dirnames[:] = sorted(d for d in dirnames if d not in ('.git','__pycache__','tests'))
        for name in sorted(filenames):
            if name == '.DS_Store': continue
            rel=os.path.relpath(os.path.join(dirpath,name), os.getcwd()).replace(os.sep,'/')
            h=hashlib.sha256()
            with open(rel,'rb') as f:
                for chunk in iter(lambda:f.read(1024*1024), b''): h.update(chunk)
            files[rel]=h.hexdigest()
manifest=None
if os.path.exists({remote_manifest!r}):
    with open({remote_manifest!r}, encoding='utf-8') as f: manifest=json.load(f)
print(json.dumps({{'files': files, 'manifest': manifest}}, sort_keys=True))
REMOTE
'''
    data = json.loads(ssh(host, script))
    return data["files"], previous_manifest(target, data.get("manifest"))


def classified(target: dict) -> dict[str, dict]:
    rel = target.get("classified_extras")
    if not rel or not (root() / rel).exists():
        return {}
    data = json.loads((root() / rel).read_text())
    return {item["path"]: item for item in data.get("extras", []) if isinstance(item, dict) and item.get("path")}


def compare(local: dict[str, str], remote: dict[str, str], previous: dict[str, str] | None, known_extras: dict[str, dict]) -> dict[str, list | bool]:
    missing = sorted(set(local) - set(remote))
    extras = sorted(set(remote) - set(local))
    changed = sorted(p for p, digest in local.items() if p in remote and remote[p] != digest)
    updates, drift = [], []
    if previous is None:
        drift = changed[:]
    else:
        for p in changed:
            (updates if previous.get(p) == remote[p] else drift).append(p)
    deletes, unclassified, mismatched = [], [], []
    for p in extras:
        item = known_extras.get(p)
        if not item or item.get("decision") != "delete":
            unclassified.append(p)
        elif item.get("sha256") and item["sha256"] != remote[p]:
            mismatched.append(p)
        else:
            deletes.append(p)
    return {"missing": missing, "changed": changed, "updates": updates, "drift": drift, "extras": extras, "unclassified_extras": unclassified, "classified_hash_mismatch": mismatched, "classified_delete": deletes, "previous_manifest_present": previous is not None}


def report(target_name: str, target: dict, docroot: str, result: dict) -> None:
    print(f"target={target_name}")
    print(f"site={target['site']}")
    print(f"document_root={docroot}")
    print(f"previous_manifest_present={str(result['previous_manifest_present']).lower()}")
    for key in ("missing", "changed", "updates", "drift", "extras", "unclassified_extras", "classified_hash_mismatch", "classified_delete"):
        print(f"{key}={len(result[key])}")
        for item in result[key]: print(f"  {item}")


def make_tar(paths: list[str]) -> bytes:
    base = root() / "website"
    with tempfile.NamedTemporaryFile() as tmp:
        with tarfile.open(tmp.name, "w") as tar:
            for rel in paths: tar.add(base / rel, arcname=rel)
        tmp.seek(0)
        return tmp.read()


def deploy(target_name: str, target: dict, host: str, docroot: str, local: dict[str, str], result: dict, phrase: str | None) -> None:
    if not target.get("allow_writes"):
        raise SystemExit(f"abort: target {target_name} is configured read-only")
    if target.get("production") and phrase != PRODUCTION_CONFIRMATION:
        raise SystemExit("abort: production write requires exact --confirm-production phrase")
    if result["drift"] or result["unclassified_extras"] or result["classified_hash_mismatch"]:
        raise SystemExit("abort: drift or unexplained extras detected")
    paths = sorted(set(result["missing"]) | set(result["updates"]))
    if paths:
        p = run(["ssh", "-o", "BatchMode=yes", host, f"tar -C {shlex.quote(docroot)} -xf -"], make_tar(paths))
        if p.returncode:
            sys.stderr.write(p.stderr.decode("utf-8", "replace")); raise SystemExit(p.returncode)
    if result["classified_delete"]:
        ssh(host, "cd " + shlex.quote(docroot) + " && rm -f -- " + " ".join(shlex.quote(p) for p in result["classified_delete"]))
    payload = json.dumps({"schema": 1, "target": target_name, "site": target["site"], "managed_dirs": MANAGED_DIRS, "files": local}, sort_keys=True, indent=2).encode()
    p = run(["ssh", "-o", "BatchMode=yes", host, f"cat > {shlex.quote(docroot + '/' + target['remote_manifest'])}"], payload)
    if p.returncode:
        sys.stderr.write(p.stderr.decode("utf-8", "replace")); raise SystemExit(p.returncode)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("command", choices=("manifest", "dry-run", "deploy"))
    ap.add_argument("--target", choices=("staging", "production"), required=True)
    ap.add_argument("--confirm-production")
    args = ap.parse_args()
    target = load_target(args.target)
    local = local_manifest()
    if args.command == "manifest":
        print(json.dumps({"target": args.target, "site": target["site"], "managed_dirs": MANAGED_DIRS, "files": local}, sort_keys=True, indent=2)); return 0
    host, docroot = host_and_root(target)
    remote, previous = scan_remote(host, docroot, target)
    result = compare(local, remote, previous, classified(target))
    report(args.target, target, docroot, result)
    unsafe = result["drift"] or result["unclassified_extras"] or result["classified_hash_mismatch"]
    if args.command == "dry-run": return 2 if unsafe else 0
    deploy(args.target, target, host, docroot, local, result, args.confirm_production)
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
