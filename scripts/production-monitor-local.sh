#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-https://bitmomo.id}"
BASE_URL="${BASE_URL%/}"
PRODUCTION_HOST="bitmomo.id"

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERROR missing required tool: $1" >&2
    exit 2
  }
}

need curl
need grep
need python3

host="$(python3 - "$BASE_URL" <<'PY'
from urllib.parse import urlparse
import sys
print(urlparse(sys.argv[1]).hostname or "")
PY
)"

has_robots_noindex() {
  python3 - "$1" <<'PY'
import re, sys
html = open(sys.argv[1], encoding="utf-8", errors="ignore").read()
for tag in re.findall(r"<meta\b[^>]*>", html, flags=re.I):
    if re.search(r"\bname\s*=\s*['\"]robots['\"]", tag, flags=re.I) and \
       re.search(r"\bcontent\s*=\s*['\"][^'\"]*\bnoindex\b", tag, flags=re.I):
        raise SystemExit(0)
raise SystemExit(1)
PY
}

check_page() {
  local path="$1"
  local marker="$2"
  local body code url
  url="${BASE_URL}${path}"
  body="$(mktemp)"
  code="$(curl --location --silent --show-error --max-time 20 --output "$body" --write-out '%{http_code}' "$url")"

  if [ "$code" != "200" ]; then
    echo "FAIL HTTP $code $url" >&2
    rm -f "$body"
    return 1
  fi
  if ! grep -Fiq "$marker" "$body"; then
    echo "FAIL missing stable marker '$marker' at $url" >&2
    rm -f "$body"
    return 1
  fi
  if grep -Eqi 'Fatal error|Parse error|Uncaught (Error|Exception)|<b>Warning</b>|<b>Notice</b>' "$body"; then
    echo "FAIL PHP/runtime leakage at $url" >&2
    rm -f "$body"
    return 1
  fi
  if [ "$host" = "$PRODUCTION_HOST" ] && grep -Eqi 'seagreen|staging\.bitmomo|\.hostingersite\.|\.hostingerapp\.' "$body"; then
    echo "FAIL staging/preview hostname leaked at $url" >&2
    rm -f "$body"
    return 1
  fi
  if [ "$host" = "$PRODUCTION_HOST" ] && has_robots_noindex "$body"; then
    echo "FAIL production page unexpectedly noindex: $url" >&2
    rm -f "$body"
    return 1
  fi

  rm -f "$body"
  echo "PASS $url"
}

check_page "/" "BTC Intelligence"
check_page "/btc-intelligence/" "BTC INTELLIGENCE"
check_page "/pro/" "FOUNDING MEMBERSHIP"
check_page "/help/" "Help Center"
check_page "/category/riset/" "Riset"
check_page "/tentang-kami/" "Tentang Kami"
check_page "/kebijakan-privasi/" "Kebijakan Privasi"
check_page "/disclaimer/" "Disclaimer"

health="$(mktemp)"
trap 'rm -f "$health"' EXIT
curl --location --silent --show-error --max-time 20 \
  "${BASE_URL}/wp-admin/admin-ajax.php?action=bitmomo_health" > "$health"

theme_version="$(python3 - "$health" <<'PY'
import json, sys
try:
    print(json.load(open(sys.argv[1], encoding="utf-8")).get("version", "0"))
except Exception:
    print("0")
PY
)"
echo "INFO theme_version=$theme_version"

if python3 - "$theme_version" <<'PY'
import re, sys
def v(s):
    return tuple(int(x) for x in re.findall(r"\d+", s)[:3] or [0])
raise SystemExit(0 if v(sys.argv[1]) >= (4,4) else 1)
PY
then
  btc="$(mktemp)"
  code="$(curl --location --silent --show-error --max-time 20 --output "$btc" --write-out '%{http_code}' "${BASE_URL}/btc-intelligence/")"
  test "$code" = "200"
  grep -Fq 'Sumber data:' "$btc"
  grep -Fq 'WIB' "$btc"
  rm -f "$btc"
  echo "PASS v4.4 BTC trust metadata"

  home="$(mktemp)"
  btc="$(mktemp)"
  live="$(mktemp)"
  nonce="$(date +%s)"
  curl --location --silent --show-error --max-time 20 "${BASE_URL}/?bm_monitor=${nonce}" > "$home"
  curl --location --silent --show-error --max-time 20 "${BASE_URL}/btc-intelligence/?bm_monitor=${nonce}" > "$btc"
  curl --location --silent --show-error --max-time 20 \
    "${BASE_URL}/wp-admin/admin-ajax.php?action=bitmomo_snapshot_contract&_=${nonce}" > "$live"

  python3 - "$home" "$btc" "$live" <<'PY'
import json, sys
from html.parser import HTMLParser

class ContractParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.contract = None
    def handle_starttag(self, tag, attrs):
        if tag.lower() != "meta":
            return
        data = dict(attrs)
        if data.get("name") == "bitmomo-snapshot-contract":
            self.contract = json.loads(data.get("content", ""))

def page_contract(path):
    parser = ContractParser()
    parser.feed(open(path, encoding="utf-8", errors="ignore").read())
    if not isinstance(parser.contract, dict):
        raise SystemExit(f"missing snapshot contract: {path}")
    return parser.contract

home = page_contract(sys.argv[1])
btc = page_contract(sys.argv[2])
payload = json.load(open(sys.argv[3], encoding="utf-8"))
live = payload.get("data") if payload.get("success") is True else None
if not isinstance(live, dict):
    raise SystemExit("invalid canonical snapshot endpoint")

fields = ("schema", "available", "status", "as_of", "directional_bias", "confidence")
project = lambda x: {f: x.get(f) for f in fields}
expected = project(live)
if expected.get("schema") != 2:
    raise SystemExit(f"unexpected snapshot schema {expected.get('schema')}")
if project(home) != expected or project(btc) != expected:
    raise SystemExit("homepage/BTC Intelligence disagree with canonical snapshot")
print("PASS canonical BTC snapshot consistency")
PY
  rm -f "$home" "$btc" "$live"
else
  echo "SKIP v4.4-only trust/snapshot contract for theme $theme_version"
fi

if [ "$host" = "$PRODUCTION_HOST" ]; then
  set +e
  code="$(curl --location --silent --show-error --connect-timeout 5 --max-time 10 \
    --output /tmp/bitmomo-binance-ping --write-out '%{http_code}' \
    'https://fapi.binance.com/fapi/v1/ping')"
  rc=$?
  set -e
  if [ "$rc" -ne 0 ] || [ "$code" != "200" ]; then
    echo "WARN Binance USD-M diagnostic unavailable (curl=$rc http=${code:-000}); tracked separately"
  else
    echo "PASS Binance USD-M reachability"
  fi
fi

echo "PASS Bitmomo production monitor: $BASE_URL"
