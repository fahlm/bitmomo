#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

STATE="${HL_STATE_JSON:-data/runtime/autonomous_state.json}"
SESSION="${HL_CANARY_SESSION_JSON:-data/runtime/live_canary_session.json}"
ENABLE="${HL_CANARY_ENABLE_FILE:-data/runtime/ENABLE_LIVE_CANARY}"
KILL="${HL_CANARY_KILL_FILE:-data/runtime/KILL_LIVE_CANARY}"
LOCK="${HL_CANARY_AUTHORITY_LOCK:-data/runtime/live_canary.lock}"

: "${HL_ACCOUNT_ADDRESS:?HL_ACCOUNT_ADDRESS is required}"
: "${HL_SECRET_KEY:?HL_SECRET_KEY is required and must be supplied only through the host secret environment}"
: "${HL_CANARY_ACK:?HL_CANARY_ACK is required}"

exec python -u -m market_selector.live_canary_runtime \
  --live-mainnet-canary \
  --scanner-state "$STATE" \
  --session-state "$SESSION" \
  --enable-file "$ENABLE" \
  --kill-file "$KILL" \
  --authority-lock "$LOCK"
