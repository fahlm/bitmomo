#!/usr/bin/env bash
#
# Consolidated developer test runner for Bitmomo's two intelligence
# products (Product A: bitmomo-regime, Product B: bitmomo-watchtower).
#
# Runs every standalone `tests/test-*.php` suite in both plugins directly
# via `php` — no WordPress runtime, no PHPUnit, no Docker, no database.
# Each suite is already self-contained (a minimal ABSPATH stub plus the
# plugin's own pure-PHP domain classes) and already prints its own
# "<passed>/<total> passed." line; this script just runs all of them,
# aggregates those totals, and gives one clear pass/fail verdict.
#
# Usage:
#   bash scripts/test-bitmomo-intelligence.sh
#
# Exit code: 0 if every suite passed, 1 if any suite failed or `php` is
# missing. This is deliberately NOT a CI framework — no parallelism, no
# coverage, no XML reports; it is the one-command developer entrypoint the
# Integration Hardening Phase brief asked for.

set -u
set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

REGIME_DIR="${REPO_ROOT}/website/wp-content/plugins/bitmomo-regime"
WATCHTOWER_DIR="${REPO_ROOT}/website/wp-content/plugins/bitmomo-watchtower"

if ! command -v php >/dev/null 2>&1; then
	echo "ERROR: php is not on PATH. This script runs each suite with plain 'php', no WordPress/PHPUnit required." >&2
	exit 1
fi

overall_pass=0
overall_total=0
overall_exit=0
declare -a failed_suites=()

run_suite_dir() {
	local label="$1"
	local dir="$2"

	if [ ! -d "${dir}" ]; then
		echo "SKIP: ${label} — directory not found: ${dir}"
		return
	fi

	echo ""
	echo "=================================================================="
	echo " ${label}  (${dir#"${REPO_ROOT}"/})"
	echo "=================================================================="

	local found_any=0
	for test_file in "${dir}"/tests/test-*.php; do
		[ -e "${test_file}" ] || continue
		found_any=1
		local name
		name="$(basename "${test_file}")"
		echo ""
		echo "--- ${name} ---"

		local output
		output="$(php "${test_file}" 2>&1)"
		local exit_code=$?
		echo "${output}"

		# Every suite in both plugins prints a trailing "<passed>/<total> passed." line.
		local summary_line
		summary_line="$(printf '%s\n' "${output}" | grep -Eo '[0-9]+/[0-9]+ passed\.' | tail -n1)"

		if [ -n "${summary_line}" ]; then
			local passed total
			passed="$(printf '%s' "${summary_line}" | cut -d'/' -f1)"
			total="$(printf '%s' "${summary_line}" | cut -d'/' -f2 | cut -d' ' -f1)"
			overall_pass=$((overall_pass + passed))
			overall_total=$((overall_total + total))
		else
			echo "WARNING: could not parse a '<passed>/<total> passed.' summary line from ${name} — treating its exit code as the sole signal." >&2
		fi

		if [ "${exit_code}" -ne 0 ]; then
			overall_exit=1
			failed_suites+=("${label}/${name}")
			echo ">>> FAILED (exit ${exit_code}): ${name}"
		fi
	done

	if [ "${found_any}" -eq 0 ]; then
		echo "SKIP: no tests/test-*.php files found under ${dir}"
	fi
}

run_suite_dir "Product A — Bitmomo Market Regime (bitmomo-regime)" "${REGIME_DIR}"
run_suite_dir "Product B — Bitmomo Watchtower (bitmomo-watchtower)" "${WATCHTOWER_DIR}"

echo ""
echo "=================================================================="
echo " SUMMARY"
echo "=================================================================="
echo "Total assertions: ${overall_pass}/${overall_total} passed."

if [ "${overall_exit}" -ne 0 ]; then
	echo ""
	echo "FAILED SUITES:"
	for s in "${failed_suites[@]}"; do
		echo "  - ${s}"
	done
	echo ""
	echo "RESULT: FAIL"
else
	echo ""
	echo "RESULT: PASS"
fi

exit "${overall_exit}"
