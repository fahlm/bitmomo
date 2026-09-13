#!/usr/bin/env python3
"""Verify that staging HTML and CDN assets match the exact checked-out UI source.

This closes the gap between filesystem parity and what a guest browser actually
receives after Hostinger/LiteSpeed/CDN caching. It intentionally checks both the
canonical homepage URL and a cache-busted request.
"""

from __future__ import annotations

import argparse
import hashlib
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / "website" / "wp-content" / "themes" / "bitmomo-child-v3"
DEFAULT_BASE_URL = "https://seagreen-snail-158456.hostingersite.com"

ASSETS = (
    "assets/css/design-system.css",
    "assets/css/public-readability.css",
    "assets/css/public-surfaces.css",
    "assets/css/navigation-footer.css",
    "assets/css/home.css",
    "assets/css/home-conversion.css",
    "assets/js/bitmomo-frontend.js",
)


class AssetParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.urls: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = dict(attrs)
        if tag.lower() == "link":
            rel = str(values.get("rel") or "").lower().split()
            href = values.get("href")
            if "stylesheet" in rel and href:
                self.urls.append(href)
        elif tag.lower() == "script":
            src = values.get("src")
            if src:
                self.urls.append(src)


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def request_bytes(url: str, *, no_cache: bool) -> bytes:
    headers = {
        "User-Agent": "BitmomoReleaseAudit/1.0",
        "Accept": "text/html,application/xhtml+xml,application/javascript,text/css,*/*;q=0.8",
    }
    if no_cache:
        headers["Cache-Control"] = "no-cache"
        headers["Pragma"] = "no-cache"
    request = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            status = getattr(response, "status", 200)
            if status != 200:
                raise SystemExit(f"{url} returned HTTP {status}")
            return response.read()
    except urllib.error.HTTPError as exc:
        raise SystemExit(f"{url} returned HTTP {exc.code}") from exc
    except urllib.error.URLError as exc:
        raise SystemExit(f"failed to fetch {url}: {exc}") from exc


def normalize_asset_url(base_url: str, value: str) -> str:
    return urllib.parse.urljoin(base_url.rstrip("/") + "/", value)


def expected_assets() -> dict[str, tuple[Path, str, str]]:
    result: dict[str, tuple[Path, str, str]] = {}
    for relative in ASSETS:
        local = THEME / relative
        if not local.is_file():
            raise SystemExit(f"missing local canonical asset: {local.relative_to(ROOT)}")
        digest = sha256_file(local)
        runtime_suffix = f"/wp-content/themes/bitmomo-child-v3/{relative}"
        result[relative] = (local, runtime_suffix, digest)
    return result


def parse_html_asset_urls(base_url: str, html: bytes) -> list[str]:
    parser = AssetParser()
    parser.feed(html.decode("utf-8", errors="replace"))
    return [normalize_asset_url(base_url, value) for value in parser.urls]


def find_rendered_asset(urls: list[str], suffix: str) -> str | None:
    matches: list[str] = []
    for url in urls:
        parsed = urllib.parse.urlsplit(url)
        if urllib.parse.unquote(parsed.path).endswith(suffix):
            matches.append(url)
    if len(matches) > 1:
        raise SystemExit(f"duplicate rendered asset for {suffix}: {matches}")
    return matches[0] if matches else None


def verify_snapshot(label: str, page_url: str, *, no_cache: bool, assets: dict[str, tuple[Path, str, str]]) -> None:
    html = request_bytes(page_url, no_cache=no_cache)
    rendered = parse_html_asset_urls(page_url, html)
    print(f"snapshot={label} url={page_url} rendered_assets={len(rendered)}")

    failures: list[str] = []
    for relative, (_local, suffix, digest) in assets.items():
        asset_url = find_rendered_asset(rendered, suffix)
        if not asset_url:
            failures.append(f"{relative}: canonical first-party asset URL missing from rendered HTML")
            continue

        parsed = urllib.parse.urlsplit(asset_url)
        query = urllib.parse.parse_qs(parsed.query)
        versions = query.get("ver", [])
        expected_version = digest[:12]
        if versions != [expected_version]:
            failures.append(
                f"{relative}: rendered version={versions or 'missing'} expected={expected_version}; url={asset_url}"
            )

        remote = request_bytes(asset_url, no_cache=no_cache)
        remote_digest = sha256_bytes(remote)
        if remote_digest != digest:
            failures.append(
                f"{relative}: CDN/browser bytes sha256={remote_digest} expected={digest}; url={asset_url}"
            )
        else:
            print(f"PASS {label} {relative} ver={expected_version} sha256={digest}")

    if failures:
        print(f"FAIL asset coherence for {label}: {len(failures)} issue(s)")
        for failure in failures:
            print(f"  - {failure}")
        raise SystemExit(1)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL)
    args = parser.parse_args()

    base_url = args.base_url.rstrip("/")
    assets = expected_assets()

    # Canonical guest URL is intentionally checked without no-cache headers. If
    # this is stale, a real returning/guest browser can be stale too.
    verify_snapshot("canonical-cache", base_url + "/", no_cache=False, assets=assets)

    separator = "&" if "?" in base_url else "?"
    bypass_url = base_url + "/" + separator + urllib.parse.urlencode({"bm_asset_audit": int(time.time())})
    verify_snapshot("cache-busted", bypass_url, no_cache=True, assets=assets)

    print("PASS staging asset coherence: canonical and cache-busted HTML serve exact candidate asset bytes")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
