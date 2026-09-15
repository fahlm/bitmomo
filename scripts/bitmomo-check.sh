#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-test}"
BASE_REF="${BITMOMO_BASE_REF:-origin/main}"
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

runtime_roots=(
  website/wp-content/themes/bitmomo-child-v3
  website/wp-content/plugins/bitmomo-ai
  website/wp-content/plugins/bitmomo-btc-intelligence
  website/wp-content/plugins/bitmomo-pro
  website/wp-content/plugins/bitmomo-regime
)

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERROR missing required tool: $1" >&2
    exit 2
  }
}

changed_files_to() {
  local output="$1"
  {
    if git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
      git diff --name-only "$BASE_REF"...HEAD
    elif git rev-parse --verify HEAD~1 >/dev/null 2>&1; then
      git diff --name-only HEAD~1...HEAD
    fi
    git diff --name-only
    git diff --cached --name-only
  } | awk 'NF' | sort -u > "$output"
}

lint_file() {
  local file="$1"
  case "$file" in
    *.php) php -l "$file" >/dev/null && echo "PASS php $file" ;;
    *.js|*.mjs) node --check "$file" >/dev/null && echo "PASS js $file" ;;
    *.sh) bash -n "$file" && echo "PASS sh $file" ;;
    *.py) python3 - "$file" <<'PY'
import pathlib, sys
path = sys.argv[1]
compile(pathlib.Path(path).read_text(encoding='utf-8'), path, 'exec')
print(f'PASS py {path}')
PY
      ;;
  esac
}

run_plugin_tests() {
  local plugin="$1"
  local dir="website/wp-content/plugins/$plugin"
  test -d "$dir/tests" || return 0
  find "$dir/tests" -maxdepth 1 -type f -name 'test-*.php' | sort |
    while IFS= read -r test_file; do
      echo "RUN $test_file"
      (cd "$dir" && php "${test_file#${dir}/}")
    done
}

quick() {
  need php
  need node
  need python3

  local list
  list="$(mktemp)"
  changed_files_to "$list"
  local count
  count="$(wc -l < "$list" | tr -d ' ')"
  echo "Changed files: $count"
  if [ "$count" -eq 0 ]; then
    echo "PASS no local/source delta to verify"
    rm -f "$list"
    return 0
  fi

  local theme_changed=0
  local launch_changed=0
  local file
  while IFS= read -r file; do
    test -f "$file" || continue
    case "$file" in
      website/wp-content/*|scripts/*) lint_file "$file" ;;
    esac
    case "$file" in
      website/wp-content/themes/bitmomo-child-v3/*|scripts/check-ui-architecture.mjs|scripts/audit-css-debt.mjs)
        theme_changed=1
        ;;
    esac
    case "$file" in
      website/wp-content/*|config/production-runtime.json|scripts/build-production-artifact.py|scripts/check-m2-launch-surfaces.mjs)
        launch_changed=1
        ;;
    esac
  done < "$list"

  if [ "$theme_changed" -eq 1 ]; then
    node scripts/check-ui-architecture.mjs
    node scripts/audit-css-debt.mjs
  fi
  if [ "$launch_changed" -eq 1 ]; then
    node scripts/check-m2-launch-surfaces.mjs
  fi
  rm -f "$list"
  echo "PASS quick local checks"
}

test_touched() {
  quick
  local list
  list="$(mktemp)"
  changed_files_to "$list"
  local plugin
  for plugin in bitmomo-ai bitmomo-btc-intelligence bitmomo-pro bitmomo-regime; do
    if grep -q "^website/wp-content/plugins/${plugin}/" "$list"; then
      run_plugin_tests "$plugin"
    fi
  done
  rm -f "$list"
  echo "PASS touched deterministic tests"
}

full() {
  need php
  need node
  need python3
  need cmp

  local expected_sha="${2:-}"
  [[ "$expected_sha" =~ ^[0-9a-f]{40}$ ]] || {
    echo "Usage: bash scripts/bitmomo-check.sh full <exact-40-char-sha>" >&2
    exit 2
  }

  local actual_sha actual_tree
  actual_sha="$(git rev-parse HEAD)"
  actual_tree="$(git rev-parse 'HEAD^{tree}')"
  test "$actual_sha" = "$expected_sha" || {
    echo "ERROR checkout=$actual_sha expected=$expected_sha" >&2
    exit 1
  }
  git diff --quiet && git diff --cached --quiet || {
    echo "ERROR full release verification requires a clean tracked checkout" >&2
    exit 1
  }

  echo "Candidate commit=$actual_sha tree=$actual_tree"

  find "${runtime_roots[@]}" -type f -name '*.php' -print0 |
    while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done

  local plugin
  for plugin in bitmomo-ai bitmomo-btc-intelligence bitmomo-pro bitmomo-regime; do
    run_plugin_tests "$plugin"
  done

  find "${runtime_roots[@]}" -type f \( -name '*.js' -o -name '*.mjs' \) ! -path '*/tests/*' -print0 |
    while IFS= read -r -d '' file; do node --check "$file" >/dev/null; done

  node scripts/check-m2-launch-surfaces.mjs
  node scripts/check-ui-architecture.mjs
  node scripts/audit-css-debt.mjs

  local out="dist/local-release"
  rm -rf "$out"
  mkdir -p "$out/first" "$out/second"
  python3 scripts/build-production-artifact.py --output-dir "$out/first"
  python3 scripts/build-production-artifact.py --output-dir "$out/second"
  cmp "$out/first/bitmomo-runtime.tar" "$out/second/bitmomo-runtime.tar"

  EXPECTED_COMMIT="$actual_sha" EXPECTED_TREE="$actual_tree" \
  FIRST="$out/first/bitmomo-runtime-manifest.json" SECOND="$out/second/bitmomo-runtime-manifest.json" \
  python3 - <<'PY'
import json, os
paths = [os.environ["FIRST"], os.environ["SECOND"]]
values = [json.load(open(path, encoding="utf-8")) for path in paths]
a, b = values
expected_commit = os.environ["EXPECTED_COMMIT"]
expected_tree = os.environ["EXPECTED_TREE"]
assert a.get("source_commit") == expected_commit, (a.get("source_commit"), expected_commit)
assert a.get("source_tree") == expected_tree, (a.get("source_tree"), expected_tree)
assert b.get("source_commit") == expected_commit
assert b.get("source_tree") == expected_tree
assert a["artifact_sha256"] == b["artifact_sha256"]
assert a["file_count"] == b["file_count"]
assert a["files"] == b["files"]
print(f"PASS deterministic artifact sha256={a['artifact_sha256']} files={a['file_count']}")
PY
  echo "PASS full exact-SHA local release verification"
}

smoke() {
  need curl
  local base="${2:-https://bitmomo.id}"
  base="${base%/}"
  local path body code
  for path in "/" "/btc-intelligence/" "/pro/"; do
    body="$(mktemp)"
    code="$(curl --location --silent --show-error --max-time 20 --output "$body" --write-out '%{http_code}' "${base}${path}")"
    test "$code" = "200" || {
      echo "ERROR ${base}${path} HTTP $code" >&2
      rm -f "$body"
      exit 1
    }
    ! grep -Eqi 'Fatal error|Parse error|Uncaught (Error|Exception)|<b>Warning</b>|<b>Notice</b>' "$body" || {
      echo "ERROR runtime leakage at ${base}${path}" >&2
      rm -f "$body"
      exit 1
    }
    rm -f "$body"
    echo "PASS ${base}${path}"
  done
  echo "PASS HTTP smoke"
}

case "$MODE" in
  quick) quick ;;
  test) test_touched ;;
  full) full "$@" ;;
  smoke) smoke "$@" ;;
  *)
    echo "Usage: bash scripts/bitmomo-check.sh {quick|test|full <sha>|smoke [base_url]}" >&2
    exit 2
    ;;
esac
