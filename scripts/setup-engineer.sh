#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

git config core.hooksPath .githooks
git config pull.ff only
git config fetch.prune true
git config rerere.enabled true

chmod +x .githooks/pre-push scripts/bitmomo-check.sh scripts/production-monitor-local.sh 2>/dev/null || true

echo 'Bitmomo engineer defaults configured:'
echo '  core.hooksPath=.githooks'
echo '  pull.ff=only'
echo '  fetch.prune=true'
echo '  rerere.enabled=true'
echo
bash scripts/bitmomo-check.sh doctor
