#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

mkdir -p data/runtime

exec python -u -m market_selector.autonomous_runtime \
  --scan-top "${HL_SCAN_TOP:-24}" \
  --retain-buffer "${HL_RETAIN_BUFFER:-8}" \
  --universe-refresh "${HL_UNIVERSE_REFRESH_SECONDS:-300}" \
  --report-interval "${HL_REPORT_INTERVAL_SECONDS:-30}" \
  --transport-stale "${HL_TRANSPORT_STALE_SECONDS:-60}" \
  --state-json "${HL_STATE_JSON:-data/runtime/autonomous_state.json}" \
  --shadow-feedback-jsonl "${HL_SHADOW_FEEDBACK_JSONL:-data/runtime/autonomous_shadow_feedback.jsonl}" \
  "$@"
