#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

STATE="${HL_STATE_JSON:-data/runtime/autonomous_state.json}"
SESSION="${HL_CANARY_SESSION_JSON:-data/runtime/live_canary_session.json}"
ENABLE="${HL_CANARY_ENABLE_FILE:-data/runtime/ENABLE_LIVE_CANARY}"
KILL="${HL_CANARY_KILL_FILE:-data/runtime/KILL_LIVE_CANARY}"
LOCK="${HL_CANARY_AUTHORITY_LOCK:-data/runtime/live_canary.lock}"

mkdir -p "$(dirname "$ENABLE")" "$(dirname "$KILL")" "$(dirname "$LOCK")"

python -u -m market_selector.canary_preflight \
  --scanner-state "$STATE" \
  --session-state "$SESSION" \
  --enable-file "$ENABLE" \
  --kill-file "$KILL" \
  --authority-lock "$LOCK"

# Preflight requires execution to still be OFF and kill absent. Only a successful
# read-only preflight reaches this atomic enable-file creation.
tmp="${ENABLE}.tmp.$$"
printf '%s\n' 'ENABLE_BITMOMO_CANARY_V0' > "$tmp"
chmod 600 "$tmp"
mv "$tmp" "$ENABLE"
echo "CANARY ENABLED: operator gate is ON; live runtime still requires its separate explicit mainnet acknowledgement and signer environment."
