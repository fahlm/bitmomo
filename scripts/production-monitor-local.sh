#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-https://bitmomo.id}"
BASE_URL="${BASE_URL%/}"
PRODUCTION_HOST="bitmomo.id"
MAX_SNAPSHOT_AGE_SECONDS="${BITMOMO_MAX_SNAPSHOT_AGE_SECONDS:-108000}"

need() { command -v "$1" >/dev/null 2>&1 || { echo "ERROR missing required tool: $1" >&2; exit 2; }; }
for tool in curl grep python3; do need "$tool"; done

host="$(python3 - "$BASE_URL" <<'PY'
from urllib.parse import urlparse
import sys
print(urlparse(sys.argv[1]).hostname or '')
PY
)"

has_robots_noindex() {
  python3 - "$1" <<'PY'
import re, sys
html = open(sys.argv[1], encoding='utf-8', errors='ignore').read()
for tag in re.findall(r'<meta\b[^>]*>', html, flags=re.I):
    if re.search(r"\bname\s*=\s*['\"]robots['\"]", tag, flags=re.I) and re.search(r"\bcontent\s*=\s*['\"][^'\"]*\bnoindex\b", tag, flags=re.I):
        raise SystemExit(0)
raise SystemExit(1)
PY
}

check_page() {
  local path="$1" marker="$2" body code url
  url="${BASE_URL}${path}"; body="$(mktemp)"
  code="$(curl --location --silent --show-error --max-time 20 --output "$body" --write-out '%{http_code}' "$url")"
  if [ "$code" != "200" ]; then echo "FAIL HTTP $code $url" >&2; rm -f "$body"; return 1; fi
  if ! grep -Fiq "$marker" "$body"; then echo "FAIL missing stable marker '$marker' at $url" >&2; rm -f "$body"; return 1; fi
  if grep -Eqi 'Fatal error|Parse error|Uncaught (Error|Exception)|<b>Warning</b>|<b>Notice</b>' "$body"; then echo "FAIL PHP/runtime leakage at $url" >&2; rm -f "$body"; return 1; fi
  if [ "$host" = "$PRODUCTION_HOST" ] && grep -Eqi 'seagreen|staging\.bitmomo|\.hostingersite\.|\.hostingerapp\.' "$body"; then echo "FAIL staging/preview hostname leaked at $url" >&2; rm -f "$body"; return 1; fi
  if [ "$host" = "$PRODUCTION_HOST" ] && has_robots_noindex "$body"; then echo "FAIL production page unexpectedly noindex: $url" >&2; rm -f "$body"; return 1; fi
  rm -f "$body"; echo "PASS $url"
}

check_page "/" "BTC Intelligence"
check_page "/btc-intelligence/" "BTC INTELLIGENCE"
check_page "/pro/" "FOUNDING MEMBERSHIP"
check_page "/help/" "Help Center"
check_page "/category/riset/" "Riset"
check_page "/tentang-kami/" "Tentang Kami"
check_page "/kebijakan-privasi/" "Kebijakan Privasi"
check_page "/disclaimer/" "Disclaimer"

health="$(mktemp)"; trap 'rm -f "$health"' EXIT
curl --location --silent --show-error --max-time 20 "${BASE_URL}/wp-admin/admin-ajax.php?action=bitmomo_health" > "$health"
theme_version="$(python3 - "$health" <<'PY'
import json, sys
try:
    payload=json.load(open(sys.argv[1], encoding='utf-8'))
    if not isinstance(payload, dict): raise ValueError('health payload is not an object')
    print(payload.get('version','0'))
except Exception as exc:
    print(f'ERROR:{exc}')
PY
)"
case "$theme_version" in ERROR:*) echo "FAIL invalid health endpoint: ${theme_version#ERROR:}" >&2; exit 1 ;; esac
echo "INFO theme_version=$theme_version"

if python3 - "$theme_version" <<'PY'
import re, sys
def v(s): return tuple(int(x) for x in re.findall(r'\d+', s)[:3] or [0])
raise SystemExit(0 if v(sys.argv[1]) >= (4,4) else 1)
PY
then
  btc="$(mktemp)"; code="$(curl --location --silent --show-error --max-time 20 --output "$btc" --write-out '%{http_code}' "${BASE_URL}/btc-intelligence/")"
  test "$code" = "200"; grep -Fq 'Sumber data:' "$btc"; grep -Fq 'WIB' "$btc"; rm -f "$btc"
  echo "PASS BTC trust metadata"

  home="$(mktemp)"; btc="$(mktemp)"; live="$(mktemp)"; nonce="$(date +%s)"
  curl --location --silent --show-error --max-time 20 "${BASE_URL}/?bm_monitor=${nonce}" > "$home"
  curl --location --silent --show-error --max-time 20 "${BASE_URL}/btc-intelligence/?bm_monitor=${nonce}" > "$btc"
  curl --location --silent --show-error --max-time 20 "${BASE_URL}/wp-admin/admin-ajax.php?action=bitmomo_snapshot_contract&_=${nonce}" > "$live"

  python3 - "$home" "$btc" "$live" "$host" "$PRODUCTION_HOST" "$MAX_SNAPSHOT_AGE_SECONDS" <<'PY'
import json, sys
from datetime import datetime, timezone
from html.parser import HTMLParser

class ContractParser(HTMLParser):
    def __init__(self): super().__init__(convert_charrefs=True); self.contract=None
    def handle_starttag(self, tag, attrs):
        data=dict(attrs)
        if tag.lower()=='meta' and data.get('name')=='bitmomo-snapshot-contract': self.contract=json.loads(data.get('content',''))

def page_contract(path):
    p=ContractParser(); p.feed(open(path,encoding='utf-8',errors='ignore').read())
    if not isinstance(p.contract,dict): raise SystemExit(f'missing snapshot contract: {path}')
    return p.contract

home=page_contract(sys.argv[1]); btc=page_contract(sys.argv[2])
payload=json.load(open(sys.argv[3],encoding='utf-8')); live=payload.get('data') if payload.get('success') is True else None
if not isinstance(live,dict): raise SystemExit('invalid canonical snapshot endpoint')
fields=('schema','available','status','as_of','directional_bias','confidence')
project=lambda x:{f:x.get(f) for f in fields}; expected=project(live)
if expected.get('schema') != 2: raise SystemExit(f"unexpected snapshot schema {expected.get('schema')}")
if project(home) != expected or project(btc) != expected: raise SystemExit('homepage/BTC Intelligence disagree with canonical snapshot')
print('PASS canonical BTC snapshot consistency')

if sys.argv[4] == sys.argv[5]:
    if expected.get('available') is not True: raise SystemExit('production intelligence is unavailable')
    raw=str(expected.get('as_of') or '').strip()
    if not raw: raise SystemExit('production intelligence has no as_of timestamp')
    try:
        stamp=datetime.fromisoformat(raw.replace('Z','+00:00'))
        if stamp.tzinfo is None: stamp=stamp.replace(tzinfo=timezone.utc)
    except Exception as exc: raise SystemExit(f'invalid as_of timestamp: {raw}: {exc}')
    age=(datetime.now(timezone.utc)-stamp.astimezone(timezone.utc)).total_seconds()
    max_age=int(sys.argv[6])
    if age < -300: raise SystemExit(f'production intelligence timestamp is {abs(int(age))}s in the future')
    if age > max_age: raise SystemExit(f'production intelligence stale: age={int(age)}s max={max_age}s')
    print(f'PASS production intelligence freshness age={int(age)}s max={max_age}s')
PY
  rm -f "$home" "$btc" "$live"
else
  echo "SKIP snapshot contract for theme $theme_version < 4.4"
fi

if [ "$host" = "$PRODUCTION_HOST" ]; then
  set +e
  code="$(curl --location --silent --show-error --connect-timeout 5 --max-time 10 --output /tmp/bitmomo-binance-ping --write-out '%{http_code}' 'https://fapi.binance.com/fapi/v1/ping')"; rc=$?
  set -e
  if [ "$rc" -ne 0 ] || [ "$code" != "200" ]; then echo "WARN Binance USD-M diagnostic unavailable (curl=$rc http=${code:-000}); tracked separately"; else echo "PASS Binance USD-M reachability"; fi
fi

echo "PASS Bitmomo runtime monitor: $BASE_URL"
