#!/usr/bin/env python3
"""Deploy the Bitmomo staging WordPress code with drift protection.

The script is intentionally boring: it hashes what Git wants to deploy, hashes
what is already on the staging server by downloading those files over SFTP, and
refuses to write when the server no longer matches the last committed manifest.
"""
from __future__ import annotations

import argparse
import dataclasses
import datetime as dt
import hashlib
import json
import os
import posixpath
import time
import urllib.request
from pathlib import Path
from typing import Any

import paramiko

DEPLOY_ROOTS = {
    "website/wp-content/themes/bitmomo-child-v3": "wp-content/themes/bitmomo-child-v3",
    "website/wp-content/plugins/bitmomo-ai": "wp-content/plugins/bitmomo-ai",
    "website/wp-content/plugins/bitmomo-pro": "wp-content/plugins/bitmomo-pro",
    "website/wp-content/plugins/bitmomo-regime": "wp-content/plugins/bitmomo-regime",
    "website/wp-content/plugins/bitmomo-btc-intelligence": "wp-content/plugins/bitmomo-btc-intelligence",
}

EXCLUDED_NAMES = {".DS_Store", "Thumbs.db"}
EXCLUDED_SUFFIXES = (".zip", ".tar", ".tar.gz")
SMOKE_PATHS = ("/", "/pro/", "/btc-intelligence/")
TOKEN_NEEDLE = "--bm-font-sans"


@dataclasses.dataclass(frozen=True)
class FileRecord:
    local_path: str
    remote_path: str
    sha256: str
    size: int


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def should_include(path: Path) -> bool:
    if path.name in EXCLUDED_NAMES:
        return False
    name = path.name.lower()
    return not any(name.endswith(suffix) for suffix in EXCLUDED_SUFFIXES)


def collect_local_files(repo_root: Path, remote_root: str) -> dict[str, FileRecord]:
    files: dict[str, FileRecord] = {}
    for local_root_text, remote_root_text in DEPLOY_ROOTS.items():
        local_root = repo_root / local_root_text
        if not local_root.is_dir():
            raise SystemExit(f"Missing deploy root: {local_root_text}")
        for path in sorted(local_root.rglob("*")):
            if not path.is_file() or not should_include(path):
                continue
            rel = path.relative_to(local_root).as_posix()
            repo_rel = path.relative_to(repo_root).as_posix()
            remote_rel = posixpath.join(remote_root_text, rel)
            remote_path = posixpath.join(remote_root, remote_rel)
            files[remote_rel] = FileRecord(repo_rel, remote_path, sha256_file(path), path.stat().st_size)
    return dict(sorted(files.items()))


def load_manifest(path: Path) -> dict[str, Any]:
    if not path.exists():
        return {"version": 1, "files": {}}
    return json.loads(path.read_text(encoding="utf-8"))


def write_manifest(path: Path, *, files: dict[str, FileRecord], remote_root: str, site_url: str, commit_sha: str) -> None:
    payload = {
        "version": 1,
        "site": site_url,
        "remote_root": remote_root,
        "deployed_at": dt.datetime.now(dt.timezone.utc).isoformat(),
        "source_commit": commit_sha,
        "deploy_roots": DEPLOY_ROOTS,
        "files": {
            rel: {
                "local_path": record.local_path,
                "remote_path": record.remote_path,
                "sha256": record.sha256,
                "size": record.size,
            }
            for rel, record in files.items()
        },
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def required_env(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        raise SystemExit(f"Missing required environment variable: {name}")
    return value


def connect_sftp() -> tuple[paramiko.SSHClient, paramiko.SFTPClient]:
    host = required_env("STAGING_SFTP_HOST")
    username = required_env("STAGING_SFTP_USER")
    password = os.environ.get("STAGING_SFTP_PASSWORD")
    port = int(os.environ.get("STAGING_SFTP_PORT", "22"))

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    kwargs: dict[str, Any] = {
        "hostname": host,
        "username": username,
        "port": port,
        "timeout": 30,
        "banner_timeout": 30,
        "auth_timeout": 30,
    }
    key_text = os.environ.get("STAGING_SFTP_PRIVATE_KEY")
    if key_text:
        import io

        kwargs["pkey"] = paramiko.RSAKey.from_private_key(io.StringIO(key_text))
    elif password:
        kwargs["password"] = password
    else:
        raise SystemExit("Set STAGING_SFTP_PRIVATE_KEY or STAGING_SFTP_PASSWORD")
    client.connect(**kwargs)
    return client, client.open_sftp()


def remote_read(sftp: paramiko.SFTPClient, path: str) -> bytes | None:
    try:
        with sftp.open(path, "rb") as handle:
            return handle.read()
    except FileNotFoundError:
        return None
    except IOError as exc:
        if getattr(exc, "errno", None) == 2:
            return None
        raise


def remote_sha256(sftp: paramiko.SFTPClient, path: str) -> str | None:
    data = remote_read(sftp, path)
    return None if data is None else sha256_bytes(data)


def ensure_remote_dir(sftp: paramiko.SFTPClient, directory: str) -> None:
    parts = [part for part in directory.split("/") if part]
    current = "/" if directory.startswith("/") else ""
    for part in parts:
        current = posixpath.join(current, part)
        try:
            sftp.stat(current)
        except IOError:
            sftp.mkdir(current)


def upload_file(sftp: paramiko.SFTPClient, repo_root: Path, record: FileRecord) -> None:
    ensure_remote_dir(sftp, posixpath.dirname(record.remote_path))
    tmp_remote = f"{record.remote_path}.tmp-{int(time.time())}"
    sftp.put(str(repo_root / record.local_path), tmp_remote)
    uploaded = remote_sha256(sftp, tmp_remote)
    if uploaded != record.sha256:
        try:
            sftp.remove(tmp_remote)
        finally:
            raise SystemExit(f"Uploaded hash mismatch for {record.remote_path}")
    sftp.rename(tmp_remote, record.remote_path)


def verify_no_drift(sftp: paramiko.SFTPClient, desired: dict[str, FileRecord], previous: dict[str, Any], *, allow_initialize: bool) -> None:
    previous_files = previous.get("files") or {}
    if not previous_files:
        if not allow_initialize:
            raise SystemExit("No previous deploy manifest exists. Re-run with --allow-initialize only after confirming staging matches the branch.")
        mismatches: list[str] = []
        for rel, record in desired.items():
            remote_hash = remote_sha256(sftp, record.remote_path)
            if remote_hash != record.sha256:
                mismatches.append(f"{rel}: remote={remote_hash or 'missing'} local={record.sha256}")
        if mismatches:
            raise SystemExit("Cannot initialize manifest; staging differs from Git:\n" + "\n".join(mismatches[:40]))
        print(f"Initialized drift baseline from {len(desired)} matching files.")
        return

    drift: list[str] = []
    for rel, old in sorted(previous_files.items()):
        if rel not in desired:
            continue
        remote_hash = remote_sha256(sftp, desired[rel].remote_path)
        expected = old["sha256"]
        if remote_hash != expected:
            drift.append(f"{rel}: remote={remote_hash or 'missing'} manifest={expected}")
    if drift:
        raise SystemExit("Remote drift detected; aborting before write:\n" + "\n".join(drift[:40]))
    print(f"Drift check passed for {len(previous_files)} manifest entries.")


def purge_cache() -> None:
    url = os.environ.get("STAGING_CACHE_PURGE_URL")
    if not url:
        print("No STAGING_CACHE_PURGE_URL set; skipping cache purge.")
        return
    request = urllib.request.Request(url, method=os.environ.get("STAGING_CACHE_PURGE_METHOD", "POST"))
    token = os.environ.get("STAGING_CACHE_PURGE_TOKEN")
    if token:
        request.add_header("Authorization", f"Bearer {token}")
    with urllib.request.urlopen(request, timeout=30) as response:
        print(f"Cache purge returned HTTP {response.status}.")


def first_stylesheet_href(html: str) -> str | None:
    import re

    for match in re.finditer(r"<link[^>]+rel=[\"']stylesheet[\"'][^>]*>", html):
        href = re.search(r"href=[\"']([^\"']+)", match.group(0))
        if href:
            return href.group(1)
    return None


def smoke_check(site_url: str) -> None:
    base = site_url.rstrip("/")
    for path in SMOKE_PATHS:
        url = base + path + ("&" if "?" in path else "?") + f"v={int(time.time())}"
        with urllib.request.urlopen(url, timeout=30) as response:
            html = response.read().decode("utf-8", "replace")
            if response.status != 200:
                raise SystemExit(f"Smoke failed: {path} HTTP {response.status}")
            if path == "/pro/":
                css_href = first_stylesheet_href(html)
                if not css_href:
                    raise SystemExit("Smoke failed: /pro/ has no stylesheet link")
                if css_href.startswith("/"):
                    css_href = base + css_href
                css_href = css_href.replace("&#038;", "&").replace("&amp;", "&")
                with urllib.request.urlopen(css_href, timeout=30) as css_response:
                    css = css_response.read().decode("utf-8", "replace")
                    if TOKEN_NEEDLE not in css:
                        raise SystemExit(f"Smoke failed: combined CSS is missing {TOKEN_NEEDLE}")
        print(f"Smoke passed: {path}")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--repo-root", default=".")
    parser.add_argument("--manifest", default="deploy/staging-manifest.json")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--allow-initialize", action="store_true")
    args = parser.parse_args()

    repo_root = Path(args.repo_root).resolve()
    manifest_path = repo_root / args.manifest
    remote_root = required_env("STAGING_REMOTE_ROOT").rstrip("/")
    site_url = os.environ.get("STAGING_SITE_URL", "https://seagreen-snail-158456.hostingersite.com")
    commit_sha = os.environ.get("GITHUB_SHA", "unknown")

    desired = collect_local_files(repo_root, remote_root)
    previous = load_manifest(manifest_path)
    client, sftp = connect_sftp()
    try:
        verify_no_drift(sftp, desired, previous, allow_initialize=args.allow_initialize)
        changed: list[FileRecord] = []
        for record in desired.values():
            if remote_sha256(sftp, record.remote_path) != record.sha256:
                changed.append(record)
        if not changed:
            print("No deploy changes; remote already matches Git.")
        elif args.dry_run:
            print(f"Dry run: {len(changed)} files would be uploaded.")
            for record in changed[:40]:
                print(record.local_path)
        else:
            for record in changed:
                upload_file(sftp, repo_root, record)
            print(f"Uploaded {len(changed)} files.")
        if not args.dry_run:
            write_manifest(manifest_path, files=desired, remote_root=remote_root, site_url=site_url, commit_sha=commit_sha)
            purge_cache()
            smoke_check(site_url)
    finally:
        sftp.close()
        client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
