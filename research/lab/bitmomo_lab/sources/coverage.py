"""Empirical archive coverage: list what data.binance.vision actually holds.

Uses the public S3 bucket listing (read-only, unauthenticated). Produces the
earliest/latest file stamp and any missing files between them for each series.
"""

from __future__ import annotations

import datetime as dt
import re
import urllib.parse
import xml.etree.ElementTree as ET

from bitmomo_lab.sources.binance_archive import Fetcher, http_fetch

LISTING = "https://s3-ap-northeast-1.amazonaws.com/data.binance.vision"
NS = "{http://s3.amazonaws.com/doc/2006-03-01/}"
STAMP = re.compile(r"-(\d{4}-\d{2}(?:-\d{2})?)\.zip$")


def list_keys(prefix: str, fetch: Fetcher = http_fetch) -> list[str]:
    keys: list[str] = []
    marker = ""
    while True:
        query = urllib.parse.urlencode({"prefix": prefix, "marker": marker, "max-keys": "1000"})
        root = ET.fromstring(fetch(f"{LISTING}?{query}"))
        page = [c.findtext(f"{NS}Key") for c in root.iter(f"{NS}Contents")]
        keys += page
        if root.findtext(f"{NS}IsTruncated") != "true" or not page:
            return keys
        marker = page[-1]


def _next(stamp: str) -> str:
    if len(stamp) == 10:
        return (dt.date.fromisoformat(stamp) + dt.timedelta(days=1)).isoformat()
    year, month = map(int, stamp.split("-"))
    return f"{year + (month == 12):04d}-{month % 12 + 1:02d}"


def series_coverage(prefix: str, fetch: Fetcher = http_fetch) -> dict:
    keys = list_keys(prefix, fetch)
    stamps = sorted({m.group(1) for k in keys if k.endswith(".zip") and (m := STAMP.search(k))})
    checksums = {k[: -len(".CHECKSUM")] for k in keys if k.endswith(".CHECKSUM")}
    zips = [k for k in keys if k.endswith(".zip")]
    missing: list[str] = []
    if stamps:
        present = set(stamps)
        cursor = stamps[0]
        while cursor < stamps[-1]:
            if cursor not in present:
                missing.append(cursor)
            cursor = _next(cursor)
    return {
        "prefix": prefix,
        "files": len(stamps),
        "earliest": stamps[0] if stamps else None,
        "latest": stamps[-1] if stamps else None,
        "missing_between": missing,
        "zips_without_checksum": sorted(z for z in zips if z not in checksums),
    }
