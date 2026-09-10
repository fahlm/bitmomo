#!/usr/bin/env python3
"""Deploy the Bitmomo staging WordPress code with drift protection.

The deploy is fail-closed by design. It scans managed Bitmomo deploy roots
deeply, scans WordPress theme/plugin roots shallowly for server-only Bitmomo
strays, classifies remote paths as known, drifted, stale, reconciled, or extra,
sweeps old Bitmomo deploy temp files, and refuses to continue when the server
contains anything that could be a hand edit or server-only code.
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
REMOTE_ROOT_REQUIRED_PARTS = ("seagreen-snail-158456.hostingersite.com", "public_html")

DEPLOY_ROOTS = {
    "website/wp-content/themes/bitmomo-child-v3": "wp-content/themes/bitmomo-child-v3",
    "website/wp-content/plugins/bitmomo-ai": "wp-content/plugins/bitmomo-ai",
    "website/wp-content/plugins/bitmomo-pro": "wp-content/plugins/bitmomo-pro",
    "website/wp-content/plugins/bitmomo-regime": "wp-content/plugins/bitmomo-regime",
    "website/wp-content/plugins/bitmomo-btc-intelligence": "wp-content/plugins/bitmomo-btc-intelligence",
}

# Deep scan roots are recursively walked and hashed. Keep them to the exact
# deploy roots so third-party WordPress packages are never downloaded just to
# prove they are not ours.
DEEP_SCAN_ROOTS = tuple(DEPLOY_ROOTS.values())

# Shallow scan roots are listed one level deep only. They catch misplaced
# Bitmomo folders and root-level files such as leftover archives without
# recursing into Elementor/Astra/SEO plugin trees on shared hosting.
SHALLOW_SCAN_ROOTS = (
    "wp-content/themes",
    "wp-content/plugins",
)

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
class RemoteRecord:
    rel_path: str
    remote_path: str
    sha256: str
    size: int


@dataclasses.dataclass(frozen=True)
class RemoteDirRecord:
    rel_path: str
    remote_path: str
    size: int


@dataclasses.dataclass(frozen=True)
class RemoteSymlinkRecord:
    rel_path: str
    remote_path: str
    target: str


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def path_segments(path_text: str) -> tuple[str, ...]:
    return tuple(part for part in path_text.replace("\\", "/").split("/") if part)


def should_scan_path(path_text: str) -> bool:
    """Return whether a remote path should be visible to drift detection.

    Scanning is intentionally near-total inside managed Bitmomo roots. Do not
    hide tests, archives, or deploy leftovers there; if they exist on the
    server, they should be reported as extras. Only `.git` is skipped to avoid
    repository internals.
    """
    return ".git" not in path_segments(path_text)


def should_deploy_path(path_text: str, *, is_dir: bool = False) -> bool:
    parts = path_segments(path_text)
    if not parts:
        return False
    if any(part in EXCLUDED_NAMES for part in parts):
        return False
    if any(part in EXCLUDED_DIR_NAMES for part in parts):
        return False
    for excluded in EXCLUDED_SEGMENT_PATHS:
        if any(parts[i : i + len(excluded)] == excluded for i in range(0, max(len(parts) - len(excluded) + 1, 0))):
            return False
    if not is_dir:
        name = parts[-1].lower()
        if any(name.endswith(suffix) for suffix in EXCLUDED_SUFFIXES):
            return False
    return True


def collect_local_files(repo_root: Path, remote_root: str) -> dict[str, FileRecord]:
    files: dict[str, FileRecord] = {}
    for local_root_text, remote_root_text in DEPLOY_ROOTS.items():
        local_root = repo_root / local_root_text
        if not local_root.is_dir():
            raise SystemExit(f"Missing deploy root: {local_root_text}")
        for path in sorted(local_root.rglob("*")):
            if not path.is_file():
                continue
            rel = path.relative_to(local_root).as_posix()
            repo_rel = path.relative_to(repo_root).as_posix()
            remote_rel = posixpath.join(remote_root_text, rel)
            if not should_deploy_path(remote_rel):
                continue
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
        "deep_scan_roots": DEEP_SCAN_ROOTS,
        "shallow_scan_roots": SHALLOW_SCAN_ROOTS,
        "excluded_dir_names": sorted(EXCLUDED_DIR_NAMES),
        "excluded_suffixes": sorted(EXCLUDED_SUFFIXES),
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


def assert_staging_destination(remote_root: str, site_url: str) -> None:
    parsed = urllib.parse.urlparse(site_url)
    if parsed.hostname != STAGING_HOST:
        raise SystemExit(f"Refusing deploy: STAGING_SITE_URL must point to {STAGING_HOST}, got {site_url!r}")
    normalized = remote_root.rstrip("/")
    missing = [part for part in REMOTE_ROOT_REQUIRED_PARTS if part not in normalized]
    if missing:
        raise SystemExit(
            "Refusing deploy before SFTP connection: STAGING_REMOTE_ROOT does not look like the staging public_html path. "
            f"Expected path containing {REMOTE_ROOT_REQUIRED_PARTS!r}, got {remote_root!r}."
        )


def parse_private_key(key_text: str, passphrase: str | None) -> Any:
    import io

    attempted = (
        ("Ed25519", paramiko.Ed25519Key),
        ("ECDSA", paramiko.ECDSAKey),
        ("RSA", paramiko.RSAKey),
    )
    errors: list[str] = []
    for label, key_class in attempted:
        try:
            return key_class.from_private_key(io.StringIO(key_text), password=passphrase)
        except Exception as exc:  # Paramiko raises several SSHException flavors.
            errors.append(f"{label}: {exc}")
    raise SystemExit("Could not parse STAGING_SFTP_PRIVATE_KEY. Tried Ed25519, ECDSA, and RSA. " + " | ".join(errors))


def connect_sftp() -> tuple[paramiko.SSHClient, paramiko.SFTPClient]:
    host = required_env("STAGING_SFTP_HOST")
    username = required_env("STAGING_SFTP_USER")
    password = os.environ.get("STAGING_SFTP_PASSWORD")
    port = int(os.environ.get("STAGING_SFTP_PORT", "22"))
    known_hosts = os.environ.get("STAGING_SFTP_KNOWN_HOSTS")
    if not known_hosts:
        raise SystemExit(
            "Missing required environment variable: STAGING_SFTP_KNOWN_HOSTS. "
            f"Generate it with: ssh-keyscan -p {port} {host}"
        )

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    known_hosts_path = Path(os.environ.get("RUNNER_TEMP", "/tmp")) / "bitmomo_staging_known_hosts"
    known_hosts_path.write_text(known_hosts + "\n", encoding="utf-8")
    client.load_host_keys(str(known_hosts_path))
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
        kwargs["pkey"] = parse_private_key(key_text, os.environ.get("STAGING_SFTP_PRIVATE_KEY_PASSPHRASE"))
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
        if getattr(exc, "errno", None) == errno.ENOENT:
            return None
        raise


def remote_sha256(sftp: paramiko.SFTPClient, path: str) -> str | None:
    data = remote_read(sftp, path)
    return None if data is None else sha256_bytes(data)


def remote_lstat(sftp: paramiko.SFTPClient, path: str) -> paramiko.SFTPAttributes | None:
    try:
        return sftp.lstat(path)
    except FileNotFoundError:
        return None
    except IOError as exc:
        if getattr(exc, "errno", None) == errno.ENOENT:
            return None
        raise


def walk_remote_root(
    sftp: paramiko.SFTPClient,
    *,
    absolute_root: str,
    relative_root: str,
    files: dict[str, RemoteRecord],
    dirs: dict[str, RemoteDirRecord],
    symlinks: dict[str, RemoteSymlinkRecord],
) -> None:
    try:
        entries = sftp.listdir_attr(absolute_root)
    except FileNotFoundError:
        return
    except IOError as exc:
        if getattr(exc, "errno", None) == errno.ENOENT:
            return
        raise

    for entry in entries:
        rel = posixpath.join(relative_root, entry.filename)
        remote_path = posixpath.join(absolute_root, entry.filename)
        if not should_scan_path(rel):
            continue
        mode = entry.st_mode or 0
        if stat.S_ISLNK(mode):
            try:
                target = sftp.readlink(remote_path)
            except IOError as exc:
                target = f"unreadable: {exc}"
            symlinks[rel] = RemoteSymlinkRecord(rel, remote_path, target)
            continue
        if stat.S_ISDIR(mode):
            dirs[rel] = RemoteDirRecord(rel, remote_path, int(entry.st_size or 0))
            if entry.filename.endswith(TMP_SUFFIX):
                continue
            walk_remote_root(sftp, absolute_root=remote_path, relative_root=rel, files=files, dirs=dirs, symlinks=symlinks)
            continue
        if not stat.S_ISREG(mode):
            continue
        digest = remote_sha256(sftp, remote_path)
        if digest is None:
            continue
        files[rel] = RemoteRecord(rel, remote_path, digest, int(entry.st_size or 0))


def is_deploy_root(remote_rel: str) -> bool:
    return remote_rel in DEPLOY_ROOTS.values()


def should_report_shallow_entry(root_rel: str, entry: paramiko.SFTPAttributes) -> bool:
    rel = posixpath.join(root_rel, entry.filename)
    if not should_scan_path(rel):
        return False
    if entry.filename.startswith("bitmomo") and not is_deploy_root(rel):
        return True
    mode = entry.st_mode or 0
    if not stat.S_ISDIR(mode) and entry.filename != "index.php":
        return True
    return False


def scan_remote_root_shallow(
    sftp: paramiko.SFTPClient,
    *,
    absolute_root: str,
    relative_root: str,
    files: dict[str, RemoteRecord],
    dirs: dict[str, RemoteDirRecord],
    symlinks: dict[str, RemoteSymlinkRecord],
) -> None:
    try:
        entries = sftp.listdir_attr(absolute_root)
    except FileNotFoundError:
        return
    except IOError as exc:
        if getattr(exc, "errno", None) == errno.ENOENT:
            return
        raise

    for entry in entries:
        if not should_report_shallow_entry(relative_root, entry):
            continue
        rel = posixpath.join(relative_root, entry.filename)
        remote_path = posixpath.join(absolute_root, entry.filename)
        mode = entry.st_mode or 0
        if stat.S_ISLNK(mode):
            try:
                target = sftp.readlink(remote_path)
            except IOError as exc:
                target = f"unreadable: {exc}"
            symlinks[rel] = RemoteSymlinkRecord(rel, remote_path, target)
        elif stat.S_ISDIR(mode):
            dirs[rel] = RemoteDirRecord(rel, remote_path, int(entry.st_size or 0))
        elif stat.S_ISREG(mode):
            files[rel] = RemoteRecord(rel, remote_path, "", int(entry.st_size or 0))


def collect_remote_state(
    sftp: paramiko.SFTPClient, remote_root: str
) -> tuple[dict[str, RemoteRecord], dict[str, RemoteDirRecord], dict[str, RemoteSymlinkRecord]]:
    files: dict[str, RemoteRecord] = {}
    dirs: dict[str, RemoteDirRecord] = {}
    symlinks: dict[str, RemoteSymlinkRecord] = {}
    for remote_rel in DEEP_SCAN_ROOTS:
        walk_remote_root(
            sftp,
            absolute_root=posixpath.join(remote_root, remote_rel),
            relative_root=remote_rel,
            files=files,
            dirs=dirs,
            symlinks=symlinks,
        )
    for remote_rel in SHALLOW_SCAN_ROOTS:
        scan_remote_root_shallow(
            sftp,
            absolute_root=posixpath.join(remote_root, remote_rel),
            relative_root=remote_rel,
            files=files,
            dirs=dirs,
            symlinks=symlinks,
        )
    return dict(sorted(files.items())), dict(sorted(dirs.items())), dict(sorted(symlinks.items()))


def remove_remote_tree(sftp: paramiko.SFTPClient, remote_path: str) -> None:
    try:
        entries = sftp.listdir_attr(remote_path)
    except IOError:
        sftp.remove(remote_path)
        return
    for entry in entries:
        child = posixpath.join(remote_path, entry.filename)
        mode = entry.st_mode or 0
        if stat.S_ISDIR(mode) and not stat.S_ISLNK(mode):
            remove_remote_tree(sftp, child)
        else:
            sftp.remove(child)
    sftp.rmdir(remote_path)


def sweep_temp_files(
    sftp: paramiko.SFTPClient,
    remote_files: dict[str, RemoteRecord],
    remote_dirs: dict[str, RemoteDirRecord],
    remote_symlinks: dict[str, RemoteSymlinkRecord],
    *,
    dry_run: bool,
) -> list[str]:
    swept: list[str] = []
    for rel, record in remote_files.items():
        if rel.endswith(TMP_SUFFIX):
            swept.append(rel)
            if not dry_run:
                sftp.remove(record.remote_path)
    for rel, record in remote_symlinks.items():
        if rel.endswith(TMP_SUFFIX):
            swept.append(rel)
            if not dry_run:
                sftp.remove(record.remote_path)
    for rel, record in sorted(remote_dirs.items(), reverse=True):
        if rel.endswith(TMP_SUFFIX):
            swept.append(rel + "/")
            if not dry_run:
                remove_remote_tree(sftp, record.remote_path)
    if swept:
        action = "Would sweep" if dry_run else "Swept"
        print(f"{action} {len(swept)} stale deploy temp paths:")
        for rel in swept[:40]:
            print(f"  {rel}")
    return swept


def parent_dirs_for(paths: set[str]) -> set[str]:
    dirs: set[str] = set(DEEP_SCAN_ROOTS) | set(SHALLOW_SCAN_ROOTS)
    for path in paths:
        current = posixpath.dirname(path)
        while current and current != ".":
            dirs.add(current)
            next_current = posixpath.dirname(current)
            if next_current == current:
                break
            current = next_current
    return dirs


def classify_remote(
    desired: dict[str, FileRecord],
    previous_manifest: dict[str, Any],
    remote_files: dict[str, RemoteRecord],
    remote_dirs: dict[str, RemoteDirRecord],
    remote_symlinks: dict[str, RemoteSymlinkRecord],
) -> tuple[list[str], list[str], list[str], list[str], list[str]]:
    previous_files = previous_manifest.get("files") or {}
    allowed_files = set(previous_files) | set(desired)
    allowed_dirs = parent_dirs_for(allowed_files)
    drifted: list[str] = []
    stale: list[str] = []
    reconciled: list[str] = []
    extra: list[str] = []
    known: list[str] = []

    for rel, old in sorted(previous_files.items()):
        remote = remote_files.get(rel)
        expected = old["sha256"]
        if rel not in desired:
            if remote is None:
                reconciled.append(rel)
            elif remote.sha256 == expected:
                stale.append(rel)
                known.append(rel)
            else:
                drifted.append(f"{rel}: remote={remote.sha256} manifest={expected}")
            continue
        if remote and remote.sha256 == expected:
            known.append(rel)
        else:
            drifted.append(f"{rel}: remote={(remote.sha256 if remote else 'missing')} manifest={expected}")

    for rel, record in remote_files.items():
        if rel not in allowed_files and not rel.endswith(TMP_SUFFIX):
            suffix = f" sha256={record.sha256}" if record.sha256 else " sha256=<not-hashed-shallow-scan>"
            extra.append(f"{rel}: size={record.size}{suffix}")
    for rel, record in remote_dirs.items():
        if rel not in allowed_dirs and not rel.endswith(TMP_SUFFIX):
            extra.append(f"{rel}/: directory size={record.size}")
    for rel, record in remote_symlinks.items():
        if rel not in allowed_files and rel not in allowed_dirs and not rel.endswith(TMP_SUFFIX):
            extra.append(f"{rel}: symlink -> {record.target}")
    return known, drifted, stale, reconciled, sorted(extra)


def verify_remote_state(
    *,
    desired: dict[str, FileRecord],
    previous_manifest: dict[str, Any],
    remote_files: dict[str, RemoteRecord],
    remote_dirs: dict[str, RemoteDirRecord],
    remote_symlinks: dict[str, RemoteSymlinkRecord],
    allow_initialize: bool,
    allow_extras: bool,
    allow_delete: bool,
) -> tuple[list[str], list[str]]:
    previous_files = previous_manifest.get("files") or {}
    if not previous_files:
        if not allow_initialize:
            raise SystemExit("No previous deploy manifest exists. Re-run with --allow-initialize only after confirming staging matches the branch.")
        mismatches = []
        for rel, record in desired.items():
            remote = remote_files.get(rel)
            if not remote or remote.sha256 != record.sha256:
                mismatches.append(f"{rel}: remote={(remote.sha256 if remote else 'missing')} local={record.sha256}")
        _, _, _, _, extra = classify_remote(desired, previous_manifest, remote_files, remote_dirs, remote_symlinks)
        if extra and not allow_extras:
            raise SystemExit("Remote extras detected during baseline initialization; aborting before write:\n" + "\n".join(extra[:80]))
        if mismatches:
            raise SystemExit("Cannot initialize manifest; staging differs from Git:\n" + "\n".join(mismatches[:80]))
        print(f"Initialized drift baseline from {len(desired)} matching files.")
        if extra:
            print(f"Allowed {len(extra)} remote extras by explicit operator override.")
        return [], []

    known, drifted, stale, reconciled, extra = classify_remote(desired, previous_manifest, remote_files, remote_dirs, remote_symlinks)
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
    parts = [part for part in directory.split("/") if part]
    current = "/" if directory.startswith("/") else ""
    for part in parts:
        current = posixpath.join(current, part)
        try:
            sftp.stat(current)
        except IOError as exc:
            if getattr(exc, "errno", None) == errno.ENOENT:
                sftp.mkdir(current)
            else:
                raise


def upload_file(sftp: paramiko.SFTPClient, repo_root: Path, record: FileRecord) -> None:
    ensure_remote_dir(sftp, posixpath.dirname(record.remote_path))
    tmp_remote = f"{record.remote_path}{TMP_SUFFIX}"
    sftp.put(str(repo_root / record.local_path), tmp_remote)
    uploaded = remote_sha256(sftp, tmp_remote)
    if uploaded != record.sha256:
        try:
            sftp.remove(tmp_remote)
        finally:
            raise SystemExit(f"Uploaded hash mismatch for {record.remote_path}")
    sftp.rename(tmp_remote, record.remote_path)


def delete_stale_files(sftp: paramiko.SFTPClient, previous_manifest: dict[str, Any], desired: dict[str, FileRecord], remote_files: dict[str, RemoteRecord]) -> int:
    previous_files = previous_manifest.get("files") or {}
    removed = 0
    for rel, old in sorted(previous_files.items()):
        if rel in desired:
            continue
        remote = remote_files.get(rel)
        if not remote:
            continue
        if remote.sha256 != old["sha256"]:
            raise SystemExit(f"Refusing to delete stale file that drifted after deploy: {rel}")
        sftp.remove(remote.remote_path)
        removed += 1
    if removed:
        print(f"Deleted {removed} stale files that matched the manifest.")
    return removed


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


def first_stylesheet_hrefs(html: str) -> list[str]:
    import re

    hrefs: list[str] = []
    for match in re.finditer(r"<link[^>]+rel=[\"']stylesheet[\"'][^>]*>", html):
        href = re.search(r"href=[\"']([^\"']+)", match.group(0))
        if href:
            hrefs.append(href.group(1))
    return hrefs


def smoke_check(site_url: str) -> None:
    base = site_url.rstrip("/")
    for path in SMOKE_PATHS:
        url = base + path + ("&" if "?" in path else "?") + f"v={int(time.time())}"
        with urllib.request.urlopen(url, timeout=30) as response:
            html = response.read().decode("utf-8", "replace")
            if response.status != 200:
                raise SystemExit(f"Smoke failed: {path} HTTP {response.status}")
            if path == "/pro/":
                hrefs = first_stylesheet_hrefs(html)
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
    commit_sha = os.environ.get("GITHUB_SHA", "unknown")

    desired = collect_local_files(repo_root, remote_root)
    previous = load_manifest(manifest_path)
    client, sftp = connect_sftp()
    try:
        remote_files, remote_dirs, remote_symlinks = collect_remote_state(sftp, remote_root)
        swept = sweep_temp_files(sftp, remote_files, remote_dirs, remote_symlinks, dry_run=args.dry_run)
        if swept and not args.dry_run:
            remote_files, remote_dirs, remote_symlinks = collect_remote_state(sftp, remote_root)
        stale, reconciled = verify_remote_state(
            desired=desired,
            previous_manifest=previous,
            remote_files=remote_files,
            remote_dirs=remote_dirs,
            remote_symlinks=remote_symlinks,
            allow_initialize=args.allow_initialize,
            allow_extras=args.allow_extras,
            allow_delete=args.allow_delete,
        )
        changed = [record for rel, record in desired.items() if rel not in remote_files or remote_files[rel].sha256 != record.sha256]
        stale_count = len(stale)
        print(f"Deploy set contains {len(desired)} files.")
        if not changed and not (args.allow_delete and stale_count) and not reconciled:
            print("No deploy changes; remote already matches Git.")
        elif args.dry_run:
            print(f"Dry run: {len(changed)} files would be uploaded and {stale_count if args.allow_delete else 0} stale files would be deleted.")
            for record in changed[:40]:
                print(record.local_path)
        else:
            if args.allow_delete:
                delete_stale_files(sftp, previous, desired, remote_files)
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
