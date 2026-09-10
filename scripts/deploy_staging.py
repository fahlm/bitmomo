#!/usr/bin/env python3
"""Drift-safe deploy for Bitmomo staging.

The scanner is intentionally wider than the deploy set: anything in the staging
WordPress theme/plugin roots that Git would not deploy is visible as an extra.
"""
from __future__ import annotations

import argparse
import dataclasses
import datetime as dt
import errno
import hashlib
import json
import os
import posixpath
import stat
import time
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any

import paramiko

STAGING_HOST = "seagreen-snail-158456.hostingersite.com"
REMOTE_ROOT_REQUIRED_PARTS = (STAGING_HOST, "public_html")
DEPLOY_ROOTS = {
    "website/wp-content/themes/bitmomo-child-v3": "wp-content/themes/bitmomo-child-v3",
    "website/wp-content/plugins/bitmomo-ai": "wp-content/plugins/bitmomo-ai",
    "website/wp-content/plugins/bitmomo-pro": "wp-content/plugins/bitmomo-pro",
    "website/wp-content/plugins/bitmomo-regime": "wp-content/plugins/bitmomo-regime",
    "website/wp-content/plugins/bitmomo-btc-intelligence": "wp-content/plugins/bitmomo-btc-intelligence",
}
SCAN_ROOTS = ("wp-content/themes", "wp-content/plugins")
EXCLUDED_NAMES = {".DS_Store", "Thumbs.db"}
EXCLUDED_DIR_NAMES = {".git", "node_modules", "tests"}
EXCLUDED_SEGMENT_PATHS = {("vendor", "bin")}
EXCLUDED_SUFFIXES = (".zip", ".tar", ".tar.gz")
TMP_SUFFIX = ".bmdeploy-tmp"
SMOKE_PATHS = ("/", "/pro/", "/btc-intelligence/")
TOKEN_NEEDLE = "--bm-font-sans"


@dataclasses.dataclass(frozen=True)
class FileRecord:
    local_path: str
    remote_path: str
    sha256: str
    size: int


@dataclasses.dataclass(frozen=True)
class RemoteFile:
    rel: str
    path: str
    sha256: str
    size: int


@dataclasses.dataclass(frozen=True)
class RemoteDir:
    rel: str
    path: str
    size: int


@dataclasses.dataclass(frozen=True)
class RemoteSymlink:
    rel: str
    path: str
    target: str


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def parts(path: str) -> tuple[str, ...]:
    return tuple(p for p in path.replace("\\", "/").split("/") if p)


def should_scan(path: str) -> bool:
    return ".git" not in parts(path)


def should_deploy(path: str, *, is_dir: bool = False) -> bool:
    p = parts(path)
    if not p or any(x in EXCLUDED_NAMES for x in p) or any(x in EXCLUDED_DIR_NAMES for x in p):
        return False
    for excluded in EXCLUDED_SEGMENT_PATHS:
        if any(p[i : i + len(excluded)] == excluded for i in range(max(len(p) - len(excluded) + 1, 0))):
            return False
    return is_dir or not any(p[-1].lower().endswith(s) for s in EXCLUDED_SUFFIXES)


def collect_local_files(repo_root: Path, remote_root: str) -> dict[str, FileRecord]:
    out: dict[str, FileRecord] = {}
    for local_root_text, remote_root_text in DEPLOY_ROOTS.items():
        local_root = repo_root / local_root_text
        if not local_root.is_dir():
            raise SystemExit(f"Missing deploy root: {local_root_text}")
        for path in sorted(local_root.rglob("*")):
            if not path.is_file():
                continue
            rel = path.relative_to(local_root).as_posix()
            remote_rel = posixpath.join(remote_root_text, rel)
            if not should_deploy(remote_rel):
                continue
            out[remote_rel] = FileRecord(
                path.relative_to(repo_root).as_posix(),
                posixpath.join(remote_root, remote_rel),
                sha256_file(path),
                path.stat().st_size,
            )
    return dict(sorted(out.items()))


def load_manifest(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text()) if path.exists() else {"version": 1, "files": {}}


def write_manifest(path: Path, *, files: dict[str, FileRecord], remote_root: str, site_url: str, commit_sha: str) -> None:
    payload = {
        "version": 1,
        "site": site_url,
        "remote_root": remote_root,
        "deployed_at": dt.datetime.now(dt.timezone.utc).isoformat(),
        "source_commit": commit_sha,
        "deploy_roots": DEPLOY_ROOTS,
        "scan_roots": SCAN_ROOTS,
        "excluded_dir_names": sorted(EXCLUDED_DIR_NAMES),
        "excluded_suffixes": sorted(EXCLUDED_SUFFIXES),
        "files": {rel: dataclasses.asdict(rec) for rel, rec in files.items()},
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n")


def required_env(name: str) -> str:
    value = os.environ.get(name)
    if not value:
        raise SystemExit(f"Missing required environment variable: {name}")
    return value


def assert_staging_destination(remote_root: str, site_url: str) -> None:
    if urllib.parse.urlparse(site_url).hostname != STAGING_HOST:
        raise SystemExit(f"Refusing deploy: STAGING_SITE_URL must point to {STAGING_HOST}, got {site_url!r}")
    if any(part not in remote_root.rstrip("/") for part in REMOTE_ROOT_REQUIRED_PARTS):
        raise SystemExit(
            "Refusing deploy before SFTP connection: STAGING_REMOTE_ROOT does not look like the staging public_html path. "
            f"Expected path containing {REMOTE_ROOT_REQUIRED_PARTS!r}, got {remote_root!r}."
        )


def parse_private_key(key_text: str, passphrase: str | None) -> Any:
    import io

    attempted = (("Ed25519", paramiko.Ed25519Key), ("ECDSA", paramiko.ECDSAKey), ("RSA", paramiko.RSAKey))
    errors: list[str] = []
    for label, cls in attempted:
        try:
            return cls.from_private_key(io.StringIO(key_text), password=passphrase)
        except Exception as exc:
            errors.append(f"{label}: {exc}")
    raise SystemExit("Could not parse STAGING_SFTP_PRIVATE_KEY. Tried Ed25519, ECDSA, and RSA. " + " | ".join(errors))


def connect_sftp() -> tuple[paramiko.SSHClient, paramiko.SFTPClient]:
    host = required_env("STAGING_SFTP_HOST")
    user = required_env("STAGING_SFTP_USER")
    port = int(os.environ.get("STAGING_SFTP_PORT", "22"))
    known_hosts = os.environ.get("STAGING_SFTP_KNOWN_HOSTS")
    if not known_hosts:
        raise SystemExit(f"Missing required environment variable: STAGING_SFTP_KNOWN_HOSTS. Generate it with: ssh-keyscan -p {port} {host}")

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    known_hosts_path = Path(os.environ.get("RUNNER_TEMP", "/tmp")) / "bitmomo_staging_known_hosts"
    known_hosts_path.write_text(known_hosts + "\n")
    client.load_host_keys(str(known_hosts_path))
    client.set_missing_host_key_policy(paramiko.RejectPolicy())

    kwargs: dict[str, Any] = {"hostname": host, "username": user, "port": port, "timeout": 30, "banner_timeout": 30, "auth_timeout": 30}
    key_text = os.environ.get("STAGING_SFTP_PRIVATE_KEY")
    if key_text:
        kwargs["pkey"] = parse_private_key(key_text, os.environ.get("STAGING_SFTP_PRIVATE_KEY_PASSPHRASE"))
    elif os.environ.get("STAGING_SFTP_PASSWORD"):
        kwargs["password"] = os.environ["STAGING_SFTP_PASSWORD"]
    else:
        raise SystemExit("Set STAGING_SFTP_PRIVATE_KEY or STAGING_SFTP_PASSWORD")
    client.connect(**kwargs)
    return client, client.open_sftp()


def missing(exc: IOError) -> bool:
    return getattr(exc, "errno", None) == errno.ENOENT


def remote_read(sftp: paramiko.SFTPClient, path: str) -> bytes | None:
    try:
        with sftp.open(path, "rb") as f:
            return f.read()
    except FileNotFoundError:
        return None
    except IOError as exc:
        if missing(exc):
            return None
        raise


def remote_sha256(sftp: paramiko.SFTPClient, path: str) -> str | None:
    data = remote_read(sftp, path)
    return None if data is None else sha256_bytes(data)


def walk_remote_root(sftp: paramiko.SFTPClient, *, abs_root: str, rel_root: str, files: dict[str, RemoteFile], dirs: dict[str, RemoteDir], links: dict[str, RemoteSymlink]) -> None:
    try:
        entries = sftp.listdir_attr(abs_root)
    except FileNotFoundError:
        return
    except IOError as exc:
        if missing(exc):
            return
        raise
    for entry in entries:
        rel = posixpath.join(rel_root, entry.filename)
        path = posixpath.join(abs_root, entry.filename)
        if not should_scan(rel):
            continue
        mode = entry.st_mode or 0
        if stat.S_ISLNK(mode):
            try:
                target = sftp.readlink(path)
            except IOError as exc:
                target = f"unreadable: {exc}"
            links[rel] = RemoteSymlink(rel, path, target)
        elif stat.S_ISDIR(mode):
            dirs[rel] = RemoteDir(rel, path, int(entry.st_size or 0))
            if not entry.filename.endswith(TMP_SUFFIX):
                walk_remote_root(sftp, abs_root=path, rel_root=rel, files=files, dirs=dirs, links=links)
        elif stat.S_ISREG(mode):
            digest = remote_sha256(sftp, path)
            if digest:
                files[rel] = RemoteFile(rel, path, digest, int(entry.st_size or 0))


def collect_remote_state(sftp: paramiko.SFTPClient, remote_root: str) -> tuple[dict[str, RemoteFile], dict[str, RemoteDir], dict[str, RemoteSymlink]]:
    files: dict[str, RemoteFile] = {}
    dirs: dict[str, RemoteDir] = {}
    links: dict[str, RemoteSymlink] = {}
    for rel in SCAN_ROOTS:
        walk_remote_root(sftp, abs_root=posixpath.join(remote_root, rel), rel_root=rel, files=files, dirs=dirs, links=links)
    return dict(sorted(files.items())), dict(sorted(dirs.items())), dict(sorted(links.items()))


def remove_remote_tree(sftp: paramiko.SFTPClient, path: str) -> None:
    try:
        entries = sftp.listdir_attr(path)
    except IOError:
        sftp.remove(path)
        return
    for entry in entries:
        child = posixpath.join(path, entry.filename)
        mode = entry.st_mode or 0
        if stat.S_ISDIR(mode) and not stat.S_ISLNK(mode):
            remove_remote_tree(sftp, child)
        else:
            sftp.remove(child)
    sftp.rmdir(path)


def sweep_temp_files(sftp: paramiko.SFTPClient, files: dict[str, RemoteFile], dirs: dict[str, RemoteDir], links: dict[str, RemoteSymlink], *, dry_run: bool) -> list[str]:
    swept: list[str] = []
    for rel, rec in files.items():
        if rel.endswith(TMP_SUFFIX):
            swept.append(rel)
            if not dry_run:
                sftp.remove(rec.path)
    for rel, rec in links.items():
        if rel.endswith(TMP_SUFFIX):
            swept.append(rel)
            if not dry_run:
                sftp.remove(rec.path)
    for rel, rec in sorted(dirs.items(), reverse=True):
        if rel.endswith(TMP_SUFFIX):
            swept.append(rel + "/")
            if not dry_run:
                remove_remote_tree(sftp, rec.path)
    if swept:
        print(("Would sweep" if dry_run else "Swept") + f" {len(swept)} stale deploy temp paths:")
        for rel in swept[:80]:
            print(f"  {rel}")
    return swept


def parent_dirs_for(paths: set[str]) -> set[str]:
    out = set(SCAN_ROOTS)
    for path in paths:
        cur = posixpath.dirname(path)
        while cur and cur != ".":
            out.add(cur)
            nxt = posixpath.dirname(cur)
            if nxt == cur:
                break
            cur = nxt
    return out


def classify_remote(desired: dict[str, FileRecord], manifest: dict[str, Any], files: dict[str, RemoteFile], dirs: dict[str, RemoteDir], links: dict[str, RemoteSymlink]) -> tuple[list[str], list[str], list[str], list[str], list[str]]:
    previous = manifest.get("files") or {}
    allowed_files = set(previous) | set(desired)
    allowed_dirs = parent_dirs_for(allowed_files)
    known: list[str] = []
    drifted: list[str] = []
    stale: list[str] = []
    reconciled: list[str] = []
    extra: list[str] = []

    for rel, old in sorted(previous.items()):
        remote = files.get(rel)
        expected = old["sha256"]
        if rel not in desired:
            if remote is None:
                reconciled.append(rel)
            elif remote.sha256 == expected:
                stale.append(rel)
                known.append(rel)
            else:
                drifted.append(f"{rel}: remote={remote.sha256} manifest={expected}")
        elif remote and remote.sha256 == expected:
            known.append(rel)
        else:
            drifted.append(f"{rel}: remote={(remote.sha256 if remote else 'missing')} manifest={expected}")

    for rel, rec in files.items():
        if rel not in allowed_files and not rel.endswith(TMP_SUFFIX):
            extra.append(f"{rel}: size={rec.size} sha256={rec.sha256}")
    for rel, rec in dirs.items():
        if rel not in allowed_dirs and not rel.endswith(TMP_SUFFIX):
            extra.append(f"{rel}/: directory size={rec.size}")
    for rel, rec in links.items():
        if rel not in allowed_files and rel not in allowed_dirs and not rel.endswith(TMP_SUFFIX):
            extra.append(f"{rel}: symlink -> {rec.target}")
    return known, drifted, stale, reconciled, sorted(extra)


def verify_remote_state(*, desired: dict[str, FileRecord], manifest: dict[str, Any], files: dict[str, RemoteFile], dirs: dict[str, RemoteDir], links: dict[str, RemoteSymlink], allow_initialize: bool, allow_extras: bool, allow_delete: bool) -> tuple[list[str], list[str]]:
    previous = manifest.get("files") or {}
    known, drifted, stale, reconciled, extra = classify_remote(desired, manifest, files, dirs, links)
    if not previous:
        if not allow_initialize:
            raise SystemExit("No previous deploy manifest exists. Re-run with --allow-initialize only after confirming staging matches the branch.")
        mismatches = [f"{rel}: remote={(files[rel].sha256 if rel in files else 'missing')} local={rec.sha256}" for rel, rec in desired.items() if rel not in files or files[rel].sha256 != rec.sha256]
        if extra and not allow_extras:
            raise SystemExit("Remote extras detected during baseline initialization; aborting before write:\n" + "\n".join(extra[:80]))
        if mismatches:
            raise SystemExit("Cannot initialize manifest; staging differs from Git:\n" + "\n".join(mismatches[:80]))
        print(f"Initialized drift baseline from {len(desired)} matching files.")
        if extra:
            print(f"Allowed {len(extra)} remote extras by explicit operator override.")
        return [], []

    failures: list[str] = []
    if drifted:
        failures.append("Remote drift detected:\n" + "\n".join(drifted[:80]))
    if extra and not allow_extras:
        failures.append("Remote extras detected:\n" + "\n".join(extra[:80]))
    if stale and not allow_delete:
        failures.append("Remote stale files would remain; rerun with --allow-delete to remove after review:\n" + "\n".join(stale[:80]))
    if failures:
        raise SystemExit("\n\n".join(failures) + "\n\nAborting before write.")
    print(f"Drift check passed for {len(known)} manifest entries.")
    if extra:
        print(f"Allowed {len(extra)} remote extras by explicit operator override.")
    if stale:
        print(f"Allowed {len(stale)} stale removals by explicit operator override.")
    if reconciled:
        print(f"Reconciled {len(reconciled)} manifest entries already absent from Git and server.")
    return stale, reconciled


def ensure_remote_dir(sftp: paramiko.SFTPClient, directory: str) -> None:
    current = "/" if directory.startswith("/") else ""
    for part in [p for p in directory.split("/") if p]:
        current = posixpath.join(current, part)
        try:
            sftp.stat(current)
        except IOError as exc:
            if missing(exc):
                sftp.mkdir(current)
            else:
                raise


def upload_file(sftp: paramiko.SFTPClient, repo_root: Path, rec: FileRecord) -> None:
    ensure_remote_dir(sftp, posixpath.dirname(rec.remote_path))
    tmp = f"{rec.remote_path}{TMP_SUFFIX}"
    sftp.put(str(repo_root / rec.local_path), tmp)
    if remote_sha256(sftp, tmp) != rec.sha256:
        try:
            sftp.remove(tmp)
        finally:
            raise SystemExit(f"Uploaded hash mismatch for {rec.remote_path}")
    sftp.rename(tmp, rec.remote_path)


def delete_stale_files(sftp: paramiko.SFTPClient, manifest: dict[str, Any], desired: dict[str, FileRecord], files: dict[str, RemoteFile]) -> int:
    removed = 0
    for rel, old in sorted((manifest.get("files") or {}).items()):
        if rel in desired or rel not in files:
            continue
        if files[rel].sha256 != old["sha256"]:
            raise SystemExit(f"Refusing to delete stale file that drifted after deploy: {rel}")
        sftp.remove(files[rel].path)
        removed += 1
    if removed:
        print(f"Deleted {removed} stale files that matched the manifest.")
    return removed


def purge_cache() -> None:
    url = os.environ.get("STAGING_CACHE_PURGE_URL")
    if not url:
        print("No STAGING_CACHE_PURGE_URL set; skipping cache purge.")
        return
    req = urllib.request.Request(url, method=os.environ.get("STAGING_CACHE_PURGE_METHOD", "POST"))
    if os.environ.get("STAGING_CACHE_PURGE_TOKEN"):
        req.add_header("Authorization", f"Bearer {os.environ['STAGING_CACHE_PURGE_TOKEN']}")
    with urllib.request.urlopen(req, timeout=30) as response:
        print(f"Cache purge returned HTTP {response.status}.")


def stylesheet_hrefs(html: str) -> list[str]:
    import re

    return [m.group(1) for m in re.finditer(r"<link[^>]+rel=[\"']stylesheet[\"'][^>]*href=[\"']([^\"']+)", html)]


def smoke_check(site_url: str) -> None:
    base = site_url.rstrip("/")
    for path in SMOKE_PATHS:
        url = base + path + ("&" if "?" in path else "?") + f"v={int(time.time())}"
        with urllib.request.urlopen(url, timeout=30) as response:
            html = response.read().decode("utf-8", "replace")
            if response.status != 200:
                raise SystemExit(f"Smoke failed: {path} HTTP {response.status}")
            if path == "/pro/":
                hrefs = stylesheet_hrefs(html)
                if len(hrefs) != 1:
                    raise SystemExit(f"Smoke failed: /pro/ expected 1 stylesheet link, found {len(hrefs)}")
                css_href = hrefs[0]
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
    parser.add_argument("--allow-extras", action="store_true")
    parser.add_argument("--allow-delete", action="store_true")
    args = parser.parse_args()

    repo_root = Path(args.repo_root).resolve()
    manifest_path = repo_root / args.manifest
    remote_root = required_env("STAGING_REMOTE_ROOT").rstrip("/")
    site_url = os.environ.get("STAGING_SITE_URL", f"https://{STAGING_HOST}")
    assert_staging_destination(remote_root, site_url)
    desired = collect_local_files(repo_root, remote_root)
    manifest = load_manifest(manifest_path)

    client, sftp = connect_sftp()
    try:
        files, dirs, links = collect_remote_state(sftp, remote_root)
        swept = sweep_temp_files(sftp, files, dirs, links, dry_run=args.dry_run)
        if swept and not args.dry_run:
            files, dirs, links = collect_remote_state(sftp, remote_root)
        stale, reconciled = verify_remote_state(
            desired=desired,
            manifest=manifest,
            files=files,
            dirs=dirs,
            links=links,
            allow_initialize=args.allow_initialize,
            allow_extras=args.allow_extras,
            allow_delete=args.allow_delete,
        )
        changed = [rec for rel, rec in desired.items() if rel not in files or files[rel].sha256 != rec.sha256]
        print(f"Deploy set contains {len(desired)} files.")
        if not changed and not (args.allow_delete and stale) and not reconciled:
            print("No deploy changes; remote already matches Git.")
        elif args.dry_run:
            print(f"Dry run: {len(changed)} files would be uploaded and {len(stale) if args.allow_delete else 0} stale files would be deleted.")
            for rec in changed[:80]:
                print(rec.local_path)
        else:
            if args.allow_delete:
                delete_stale_files(sftp, manifest, desired, files)
            for rec in changed:
                upload_file(sftp, repo_root, rec)
            print(f"Uploaded {len(changed)} files.")
        if not args.dry_run:
            write_manifest(
                manifest_path,
                files=desired,
                remote_root=remote_root,
                site_url=site_url,
                commit_sha=os.environ.get("GITHUB_SHA", "unknown"),
            )
            purge_cache()
            smoke_check(site_url)
    finally:
        sftp.close()
        client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
