#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

ENABLE="${HL_CANARY_ENABLE_FILE:-data/runtime/ENABLE_LIVE_CANARY}"
KILL="${HL_CANARY_KILL_FILE:-data/runtime/KILL_LIVE_CANARY}"

mkdir -p "$(dirname "$KILL")"
tmp="${KILL}.tmp.$$"
printf '%s\n' "KILL $(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$tmp"
chmod 600 "$tmp"
mv "$tmp" "$KILL"
rm -f "$ENABLE"
echo "CANARY KILL SWITCH SET: new entries are disabled; a running canary will take the reduce/flatten path before halting."
