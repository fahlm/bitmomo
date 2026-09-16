#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-test}"
BASE_REF="${BITMOMO_BASE_REF:-origin/main}"
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

runtime_roots=(
  website/wp-content/themes/bitmomo-child-v3
  website/wp-content/plugins/bitmomo-ai
  website/wp-content/plugins/bitmomo-btc-intelligence
  website/wp-content/plugins/bitmomo-pro
  website/wp-content/plugins/bitmomo-regime
)

need() { command -v "$1" >/dev/null 2>&1 || { echo "ERROR missing required tool: $1" >&2; exit 2; }; }
for tool in git bash "$PHP_BIN" "$NODE_BIN" "$PYTHON_BIN"; do need "$tool"; done

changed_files_to() {
  local output="$1"
  {
    if git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then git diff --name-only "$BASE_REF"...HEAD
    elif git rev-parse --verify HEAD~1 >/dev/null 2>&1; then git diff --name-only HEAD~1...HEAD; fi
    git diff --name-only
    git diff --cached --name-only
  } | awk 'NF' | sort -u > "$output"
}

lint_file() {
  local file="$1"
  case "$file" in
    *.php) "$PHP_BIN" -l "$file" >/dev/null && echo "PASS php $file" ;;
    *.js|*.mjs) "$NODE_BIN" --check "$file" >/dev/null && echo "PASS js $file" ;;
    *.sh|.githooks/*) bash -n "$file" && echo "PASS sh $file" ;;
    *.py) "$PYTHON_BIN" - "$file" <<'PY'
import pathlib, sys
path=sys.argv[1]; compile(pathlib.Path(path).read_text(encoding='utf-8'), path, 'exec'); print(f'PASS py {path}')
PY
      ;;
  esac
}

run_plugin_tests() {
  local plugin="$1" dir="website/wp-content/plugins/$1"
  test -d "$dir/tests" || return 0
  find "$dir/tests" -maxdepth 1 -type f -name 'test-*.php' | sort |
    while IFS= read -r test_file; do echo "RUN $test_file"; (cd "$dir" && "$PHP_BIN" "${test_file#${dir}/}"); done
}
node_if_present() { local script="$1"; test -f "$script" || return 0; echo "RUN $script"; "$NODE_BIN" "$script"; }
bash_if_present() { local script="$1"; test -f "$script" || return 0; echo "RUN $script"; bash "$script"; }

doctor() {
  echo "Bitmomo engineering doctor"
  echo "branch=$(git symbolic-ref --quiet --short HEAD || echo detached)"
  echo "commit=$(git rev-parse HEAD)"
  echo "tree=$(git rev-parse 'HEAD^{tree}')"
  echo "php=$($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
  echo "node=$($NODE_BIN --version 2>/dev/null || echo unknown)"
  echo "python=$($PYTHON_BIN --version 2>&1 || echo unknown)"
  "$NODE_BIN" scripts/check-engineering-policy.mjs
  "$NODE_BIN" scripts/check-release-state.mjs
  "$NODE_BIN" scripts/audit-frontend-debt.mjs
  bash -n .githooks/pre-push
  echo "PASS local pre-push hook syntax"

  local branch="$(git symbolic-ref --quiet --short HEAD || true)"
  case "$branch" in
    chatgpt/*|claude/*|codex/*) echo "WARN legacy tool-identity branch '$branch'; new work should use purpose-first names" ;;
    rc-*)
      if ! git diff --quiet || ! git diff --cached --quiet; then echo "ERROR immutable RC checkout has tracked changes" >&2; exit 1; fi
      echo "PASS immutable RC checkout is clean"
      ;;
  esac

  if git remote get-url origin >/dev/null 2>&1; then
    local count="$(git ls-remote --heads origin 2>/dev/null | wc -l | tr -d ' ' || true)"
    local target="$($NODE_BIN -e "console.log(require('./config/engineering-policy.json').target_active_branches)")"
    if [ -n "$count" ]; then
      echo "remote_branches=$count target_active_branches=$target"
      if [ "$count" -gt "$target" ]; then echo "WARN branch inventory is above target; cleanup debt is visible but does not block scoped work"; fi
    fi
  fi
  echo "PASS engineering doctor"
}

quick() {
  "$NODE_BIN" scripts/check-engineering-policy.mjs
  local list="$(mktemp)"; changed_files_to "$list"
  local count="$(wc -l < "$list" | tr -d ' ')"; echo "Changed files: $count"
  if [ "$count" -eq 0 ]; then rm -f "$list"; echo "PASS no local/source delta to verify"; return 0; fi
  local theme_changed=0 launch_changed=0 css_changed=0 release_state_changed=0 file
  while IFS= read -r file; do
    test -f "$file" || continue
    case "$file" in website/wp-content/*|scripts/*|.githooks/*) lint_file "$file" ;; esac
    case "$file" in website/wp-content/themes/bitmomo-child-v3/*|scripts/check-ui-architecture.mjs|scripts/audit-css-debt.mjs|scripts/audit-frontend-debt.mjs) theme_changed=1 ;; esac
    case "$file" in *.css) css_changed=1 ;; esac
    case "$file" in website/wp-content/*|config/production-runtime.json|scripts/build-production-artifact.py|scripts/check-m2-launch-surfaces.mjs) launch_changed=1 ;; esac
    case "$file" in config/release-state.json|docs/CURRENT_RELEASE.md|scripts/check-release-state.mjs) release_state_changed=1 ;; esac
  done < "$list"
  if [ "$theme_changed" -eq 1 ]; then node_if_present scripts/check-ui-architecture.mjs; node_if_present scripts/audit-css-debt.mjs; fi
  if [ "$css_changed" -eq 1 ]; then "$NODE_BIN" scripts/audit-frontend-debt.mjs; fi
  if [ "$launch_changed" -eq 1 ]; then node_if_present scripts/check-m2-launch-surfaces.mjs; fi
  if [ "$release_state_changed" -eq 1 ]; then "$NODE_BIN" scripts/check-release-state.mjs; fi
  rm -f "$list"; echo "PASS quick local checks"
}

test_touched() {
  quick
  local list="$(mktemp)"; changed_files_to "$list"; local plugin
  for plugin in bitmomo-ai bitmomo-btc-intelligence bitmomo-pro bitmomo-regime; do if grep -q "^website/wp-content/plugins/${plugin}/" "$list"; then run_plugin_tests "$plugin"; fi; done
  rm -f "$list"; echo "PASS touched deterministic tests"
}

full() {
  for tool in cmp tar grep wc; do need "$tool"; done
  local expected_sha="${2:-}"
  [[ "$expected_sha" =~ ^[0-9a-f]{40}$ ]] || { echo "Usage: bash scripts/bitmomo-check.sh full <exact-40-char-sha>" >&2; exit 2; }
  local actual_sha="$(git rev-parse HEAD)" actual_tree="$(git rev-parse 'HEAD^{tree}')"
  test "$actual_sha" = "$expected_sha" || { echo "ERROR checkout=$actual_sha expected=$expected_sha" >&2; exit 1; }
  git diff --quiet && git diff --cached --quiet || { echo "ERROR full release verification requires a clean tracked checkout" >&2; exit 1; }
  echo "Candidate commit=$actual_sha tree=$actual_tree"
  "$NODE_BIN" scripts/check-engineering-policy.mjs
  "$NODE_BIN" scripts/check-release-state.mjs

  find "${runtime_roots[@]}" -type f -name '*.php' -print0 | while IFS= read -r -d '' file; do "$PHP_BIN" -l "$file" >/dev/null; done
  local plugin; for plugin in bitmomo-ai bitmomo-btc-intelligence bitmomo-pro bitmomo-regime; do run_plugin_tests "$plugin"; done
  find "${runtime_roots[@]}" -type f \( -name '*.js' -o -name '*.mjs' \) ! -path '*/tests/*' -print0 | while IFS= read -r -d '' file; do "$NODE_BIN" --check "$file" >/dev/null; done

  node_if_present scripts/check-m2-launch-surfaces.mjs
  bash_if_present scripts/check-theme-source-contract.sh
  bash_if_present scripts/check-authority-source-contract.sh
  node_if_present scripts/check-navigation-footer.mjs
  node_if_present scripts/check-ui-architecture.mjs
  node_if_present scripts/check-terminal-grade-contract.mjs
  node_if_present scripts/check-institutional-copy.mjs
  node_if_present scripts/check-home-research-boundary.mjs
  node_if_present scripts/check-public-design-consistency.mjs
  "$NODE_BIN" scripts/audit-frontend-debt.mjs

  local out="dist/local-release"; rm -rf "$out" && mkdir -p "$out/first" "$out/second"
  "$PYTHON_BIN" scripts/build-production-artifact.py --output-dir "$out/first"
  "$PYTHON_BIN" scripts/build-production-artifact.py --output-dir "$out/second"
  cmp "$out/first/bitmomo-runtime.tar" "$out/second/bitmomo-runtime.tar"
  local expected_count="$($PYTHON_BIN -c 'import json; print(json.load(open("config/production-runtime.json"))["expected_file_count"])')"
  local actual_count="$(tar -tf "$out/first/bitmomo-runtime.tar" | wc -l | tr -d ' ')"
  test "$actual_count" -eq "$expected_count" || { echo "ERROR artifact file count=$actual_count expected=$expected_count" >&2; exit 1; }
  if tar -tf "$out/first/bitmomo-runtime.tar" | grep -Eq '(^|/)tests/|\.md$|\.env($|\.)|\.(key|pem|tmp|temp)$'; then echo "ERROR forbidden/non-runtime file found in artifact" >&2; exit 1; fi

  EXPECTED_COMMIT="$actual_sha" EXPECTED_TREE="$actual_tree" FIRST="$out/first/bitmomo-runtime-manifest.json" SECOND="$out/second/bitmomo-runtime-manifest.json" "$PYTHON_BIN" - <<'PY'
import json, os
a,b=[json.load(open(p,encoding='utf-8')) for p in (os.environ['FIRST'],os.environ['SECOND'])]
assert a.get('source_commit')==os.environ['EXPECTED_COMMIT']; assert a.get('source_tree')==os.environ['EXPECTED_TREE']
assert b.get('source_commit')==os.environ['EXPECTED_COMMIT']; assert b.get('source_tree')==os.environ['EXPECTED_TREE']
assert a['artifact_sha256']==b['artifact_sha256']; assert a['file_count']==b['file_count']; assert a['files']==b['files']
if 'packaging_definition_sha256' in a or 'packaging_definition_sha256' in b: assert a.get('packaging_definition_sha256')==b.get('packaging_definition_sha256')
print(f"PASS deterministic artifact sha256={a['artifact_sha256']} files={a['file_count']}")
PY
  echo "PASS full exact-SHA local release verification"
}

equivalence() {
  local accepted="${2:-}" candidate="${3:-}"
  test -n "$accepted" && test -n "$candidate" || { echo "Usage: bash scripts/bitmomo-check.sh equivalence <accepted-ref> <candidate-ref>" >&2; exit 2; }
  "$PYTHON_BIN" scripts/check-runtime-equivalence.py "$accepted" "$candidate"
}

smoke() { local base="${2:-https://bitmomo.id}"; test -f scripts/production-monitor-local.sh || { echo "ERROR production monitor script missing" >&2; exit 2; }; bash scripts/production-monitor-local.sh "$base"; }

case "$MODE" in
  doctor) doctor ;;
  quick) quick ;;
  test) test_touched ;;
  full) full "$@" ;;
  equivalence) equivalence "$@" ;;
  smoke) smoke "$@" ;;
  *) echo "Usage: bash scripts/bitmomo-check.sh {doctor|quick|test|full <sha>|equivalence <accepted-ref> <candidate-ref>|smoke [base_url]}" >&2; exit 2 ;;
esac
