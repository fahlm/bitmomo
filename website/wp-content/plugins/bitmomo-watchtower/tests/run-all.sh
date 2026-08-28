#!/usr/bin/env bash
#
# Runs every standalone Watchtower test suite in this directory and
# reports an aggregate result. Each test-*.php file is self-contained
# (own require chain, own ABSPATH stub via wp-stubs.php) and already
# exits 0/1 on its own — this script only aggregates that, it does not
# re-implement any assertion logic.
#
# Usage: bash tests/run-all.sh   (run from anywhere; resolves its own directory)

set -u
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

TOTAL_SUITES=0
FAILED_SUITES=0

for test_file in test-*.php; do
	TOTAL_SUITES=$((TOTAL_SUITES + 1))
	echo "=================================================================="
	echo "Running: $test_file"
	echo "=================================================================="
	php "$test_file"
	status=$?
	if [ $status -ne 0 ]; then
		FAILED_SUITES=$((FAILED_SUITES + 1))
		echo ">>> FAILED: $test_file (exit $status)"
	fi
	echo
done

echo "=================================================================="
echo "$((TOTAL_SUITES - FAILED_SUITES))/$TOTAL_SUITES test suites passed."
echo "=================================================================="

if [ "$FAILED_SUITES" -ne 0 ]; then
	exit 1
fi
exit 0
