#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage: bash scripts/run-release-preflight.sh --candidate-sha <40-char-sha> [--output-dir <dir>] [--source-only]

Runs the same authoritative deterministic/source gates used by Full Release Safety
without requiring GitHub-hosted Actions. By default it also builds the runtime twice,
checks byte-for-byte determinism, validates runtime file count/exclusions, and verifies
artifact provenance against the exact checked-out commit/tree.

Environment overrides:
  PHP_BIN      PHP executable (default: php)
  NODE_BIN     Node executable (default: node)
  PYTHON_BIN   Python executable (default: python3)
EOF
}

candidate_sha=""
output_dir="dist/local-preflight"
source_only=0

while (($#)); do
  case "$1" in
    --candidate-sha)
      candidate_sha="${2:-}"
      shift 2
      ;;
    --output-dir)
      output_dir="${2:-}"
      shift 2
      ;;
    --source-only)
      source_only=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "ERROR unknown argument: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

[[ "$candidate_sha" =~ ^[0-9a-f]{40}$ ]] || {
  echo "ERROR --candidate-sha must be an exact 40-character lowercase Git SHA" >&2
  exit 2
}

PHP_BIN="${PHP_BIN:-php}"
NODE_BIN="${NODE_BIN:-node}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

for tool in git "$PHP_BIN" "$NODE_BIN" "$PYTHON_BIN" bash find sort xargs tar cmp grep wc; do
  command -v "$tool" >/dev/null 2>&1 || {
    echo "ERROR required tool not found: $tool" >&2
    exit 2
  }
done

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

actual_sha="$(git rev-parse HEAD)"
actual_tree="$(git rev-parse 'HEAD^{tree}')"

if [[ "$actual_sha" != "$candidate_sha" ]]; then
  echo "ERROR candidate mismatch: checkout=$actual_sha expected=$candidate_sha" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "ERROR tracked working tree is dirty; release evidence must come from a clean exact-SHA checkout" >&2
  exit 1
fi

echo "PASS candidate identity commit=$actual_sha tree=$actual_tree"

group() {
  local title="$1"
  shift
  echo
  echo "=== $title ==="
  "$@"
}

roots=(
  website/wp-content/themes/bitmomo-child-v3
  website/wp-content/plugins/bitmomo-ai
  website/wp-content/plugins/bitmomo-btc-intelligence
  website/wp-content/plugins/bitmomo-pro
  website/wp-content/plugins/bitmomo-regime
)

group "CI governance" "$NODE_BIN" scripts/check-ci-governance.mjs

echo
echo "=== Managed PHP lint ==="
find "${roots[@]}" -type f -name '*.php' -print0 | sort -z | while IFS= read -r -d '' file; do
  "$PHP_BIN" -l "$file" >/dev/null
  echo "PASS php -l $file"
done

echo
echo "=== Deterministic PHP suites ==="
for plugin in bitmomo-ai bitmomo-btc-intelligence bitmomo-pro bitmomo-regime; do
  plugin_dir="website/wp-content/plugins/${plugin}"
  while IFS= read -r test; do
    echo "RUN $test"
    (cd "$plugin_dir" && "$PHP_BIN" "${test#${plugin_dir}/}")
  done < <(find "$plugin_dir/tests" -maxdepth 1 -type f -name 'test-*.php' | sort)
done

echo
echo "=== First-party runtime JS syntax ==="
find "${roots[@]}" -type f -name '*.js' ! -path '*/tests/*' -print0 | sort -z | while IFS= read -r -d '' file; do
  "$NODE_BIN" --check "$file"
done

node_scripts=(
  scripts/check-ci-governance.mjs
  scripts/check-m2-launch-surfaces.mjs
  scripts/check-navigation-footer.mjs
  scripts/check-ui-architecture.mjs
  scripts/check-terminal-grade-contract.mjs
  scripts/check-institutional-copy.mjs
  scripts/check-home-research-boundary.mjs
  scripts/check-public-design-consistency.mjs
  scripts/check-social-preview.mjs
  scripts/audit-css-debt.mjs
)

echo
echo "=== Release tooling syntax ==="
for script in "${node_scripts[@]}"; do
  "$NODE_BIN" --check "$script"
done

"$PYTHON_BIN" - <<'PY'
from pathlib import Path
for name in (
    'scripts/build-production-artifact.py',
    'scripts/check-staging-artifact.py',
    'scripts/check-staging-asset-coherence.py',
):
    source = Path(name).read_text(encoding='utf-8')
    compile(source, name, 'exec')
    print(f'PASS python syntax {name}')
PY

bash -n \
  scripts/check-theme-source-contract.sh \
  scripts/check-authority-source-contract.sh \
  scripts/check-staging-safety.sh \
  scripts/check-staging-readiness.sh

group "M2 launch contract" "$NODE_BIN" scripts/check-m2-launch-surfaces.mjs
group "Canonical theme source contract" bash scripts/check-theme-source-contract.sh
group "Canonical authority source contract" bash scripts/check-authority-source-contract.sh
group "Navigation/footer contract" "$NODE_BIN" scripts/check-navigation-footer.mjs
group "UI architecture and WCAG source contract" "$NODE_BIN" scripts/check-ui-architecture.mjs
group "Terminal-grade public contract" "$NODE_BIN" scripts/check-terminal-grade-contract.mjs
group "Institutional copy contract" "$NODE_BIN" scripts/check-institutional-copy.mjs
group "Homepage research boundary" "$NODE_BIN" scripts/check-home-research-boundary.mjs
group "Institutional design consistency" "$NODE_BIN" scripts/check-public-design-consistency.mjs
group "CSS debt baseline" "$NODE_BIN" scripts/audit-css-debt.mjs

if ((source_only)); then
  echo
  echo "PASS source-only release preflight commit=$actual_sha tree=$actual_tree"
  exit 0
fi

first_dir="$output_dir/first"
second_dir="$output_dir/second"
rm -rf "$first_dir" "$second_dir"
mkdir -p "$first_dir" "$second_dir"

echo
echo "=== Deterministic artifact build x2 ==="
"$PYTHON_BIN" scripts/build-production-artifact.py --output-dir "$first_dir"
"$PYTHON_BIN" scripts/build-production-artifact.py --output-dir "$second_dir"
cmp "$first_dir/bitmomo-runtime.tar" "$second_dir/bitmomo-runtime.tar"

expected_count="$($PYTHON_BIN -c 'import json; print(json.load(open("config/production-runtime.json"))["expected_file_count"])')"
actual_count="$(tar -tf "$first_dir/bitmomo-runtime.tar" | wc -l | tr -d ' ')"
if [[ "$actual_count" -ne "$expected_count" ]]; then
  echo "ERROR runtime file count mismatch: actual=$actual_count expected=$expected_count" >&2
  exit 1
fi

if tar -tf "$first_dir/bitmomo-runtime.tar" | grep -Eq '(^|/)tests/|\.md$|\.env($|\.)|\.(key|pem|tmp|temp)$'; then
  echo "ERROR forbidden/non-runtime file found in artifact" >&2
  exit 1
fi

EXPECTED_COMMIT="$actual_sha" EXPECTED_TREE="$actual_tree" FIRST_MANIFEST="$first_dir/bitmomo-runtime-manifest.json" SECOND_MANIFEST="$second_dir/bitmomo-runtime-manifest.json" "$PYTHON_BIN" - <<'PY'
import json
import os

expected_commit = os.environ['EXPECTED_COMMIT']
expected_tree = os.environ['EXPECTED_TREE']
paths = [os.environ['FIRST_MANIFEST'], os.environ['SECOND_MANIFEST']]
values = []
for path in paths:
    with open(path, encoding='utf-8') as handle:
        value = json.load(handle)
    assert value.get('source_commit') == expected_commit, (path, value.get('source_commit'), expected_commit)
    assert value.get('source_tree') == expected_tree, (path, value.get('source_tree'), expected_tree)
    values.append(value)

first, second = values
assert first['artifact_sha256'] == second['artifact_sha256']
assert first['file_count'] == second['file_count']
assert first['files'] == second['files']
assert first['packaging_definition_sha256'] == second['packaging_definition_sha256']
print(f"PASS exact candidate provenance commit={expected_commit} tree={expected_tree}")
print(f"PASS deterministic artifact sha256={first['artifact_sha256']}")
print(f"PASS runtime file_count={first['file_count']}")
PY

echo
echo "PASS FULL LOCAL RELEASE PREFLIGHT"
echo "candidate_commit=$actual_sha"
echo "candidate_tree=$actual_tree"
echo "artifact=$first_dir/bitmomo-runtime.tar"
echo "manifest=$first_dir/bitmomo-runtime-manifest.json"
