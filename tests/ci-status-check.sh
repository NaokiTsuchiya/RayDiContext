#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
support="${root}/.github/scripts/ci-status.php"
cli="${root}/.github/scripts/require-ci-success.php"

fail() {
    echo "ci-status-check: $1" >&2
    exit 1
}

php "${root}/tests/ci-status-check-probe.php" "${support}" \
    || fail "ci-status.php's assertCiConclusionSuccess() failed synthetic verification"

# Entry-point check: the CLI the workflow actually invokes.
php "${cli}" "success" \
    || fail "require-ci-success.php rejected a 'success' conclusion"
php "${cli}" "failure" >/dev/null 2>&1 \
    && fail "require-ci-success.php accepted a 'failure' conclusion" || true
php "${cli}" "" >/dev/null 2>&1 \
    && fail "require-ci-success.php accepted an empty (not-found) conclusion" || true

echo "ci-status-check: OK — assertCiConclusionSuccess() and the CLI reject anything but success"
