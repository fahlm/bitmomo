#!/usr/bin/env bash
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

REMOTE="${BITMOMO_REMOTE:-origin}"

git config core.hooksPath .githooks
git config pull.ff only
git config fetch.prune true
git config rerere.enabled true

chmod +x .githooks/pre-push scripts/bitmomo-check.sh scripts/production-monitor-local.sh scripts/branch-hygiene-report.sh 2>/dev/null || true

if git remote get-url "$REMOTE" >/dev/null 2>&1; then
  echo "Refreshing remote refs from $REMOTE..."
  git fetch --prune "$REMOTE"
else
  echo "ERROR configured Bitmomo remote '$REMOTE' is missing" >&2
  exit 2
fi

echo 'Bitmomo engineer defaults configured:'
echo '  core.hooksPath=.githooks'
echo '  pull.ff=only'
echo '  fetch.prune=true'
echo '  rerere.enabled=true'
echo "  release identity remote=$REMOTE"
echo
bash scripts/bitmomo-check.sh doctor
