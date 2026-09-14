#!/usr/bin/env bash
set -euo pipefail

mode="${1:-fast}"
case "${mode}" in
  fast|full) ;;
  *)
    echo "Usage: bash scripts/preflight-source.sh [fast|full]" >&2
    exit 2
    ;;
esac

repo_root="$(git rev-parse --show-toplevel)"
cd "${repo_root}"

required_tools=(bash php node python3 git)
for tool in "${required_tools[@]}"; do
  command -v "${tool}" >/dev/null 2>&1 || {
    echo "MISSING TOOL: ${tool}" >&2
    exit 1
  }
done

echo "== Bitmomo source preflight: ${mode} =="
echo "commit=$(git rev-parse HEAD)"

managed_roots=(
  website/wp-content/themes/bitmomo-child-v3
  website/wp-content/plugins/bitmomo-ai
  website/wp-content/plugins/bitmomo-btc-intelligence
  website/wp-content/plugins/bitmomo-pro
  website/wp-content/plugins/bitmomo-regime
)

source_shell=(
  scripts/check-theme-source-contract.sh
  scripts/check-authority-source-contract.sh
)

source_node=(
  scripts/check-m2-launch-surfaces.mjs
  scripts/check-navigation-footer.mjs
  scripts/check-ui-architecture.mjs
  scripts/check-terminal-grade-contract.mjs
  scripts/check-institutional-copy.mjs
  scripts/check-home-research-boundary.mjs
  scripts/audit-css-debt.mjs
)

echo "-- shell syntax"
for script in "${source_shell[@]}"; do
  bash -n "${script}"
done

echo "-- JavaScript syntax"
while IFS= read -r -d '' file; do
  node --check "${file}" >/dev/null
done < <(find "${managed_roots[@]}" -type f -name '*.js' ! -path '*/tests/*' -print0 | sort -z)
for script in "${source_node[@]}"; do
  node --check "${script}" >/dev/null
done

echo "-- managed PHP syntax"
while IFS= read -r -d '' file; do
  php -l "${file}" >/dev/null
done < <(find "${managed_roots[@]}" -type f -name '*.php' -print0 | sort -z)

echo "-- canonical source contracts"
bash scripts/check-theme-source-contract.sh
bash scripts/check-authority-source-contract.sh
node scripts/check-m2-launch-surfaces.mjs
node scripts/check-navigation-footer.mjs
node scripts/check-ui-architecture.mjs
node scripts/check-terminal-grade-contract.mjs
node scripts/check-institutional-copy.mjs
node scripts/check-home-research-boundary.mjs
node scripts/audit-css-debt.mjs

if [[ "${mode}" == "full" ]]; then
  echo "-- deterministic PHP suites"
  for plugin in bitmomo-ai bitmomo-btc-intelligence bitmomo-pro bitmomo-regime; do
    plugin_dir="website/wp-content/plugins/${plugin}"
    while IFS= read -r test; do
      echo "RUN ${test}"
      (cd "${plugin_dir}" && php "${test#${plugin_dir}/}")
    done < <(find "${plugin_dir}/tests" -maxdepth 1 -type f -name 'test-*.php' | sort)
  done
fi

cat <<EOF
PASS source preflight (${mode}).

This is local deterministic feedback only.
It does NOT authorize staging or production, build a release artifact,
run browser QA, or replace the explicit Full Release Safety gate.
EOF
