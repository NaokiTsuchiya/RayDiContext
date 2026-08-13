#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cli="${root}/.github/scripts/require-ci-success.sh"

fail() {
    echo "ci-status-check: $1" >&2
    exit 1
}

"${cli}" "success" \
    || fail "require-ci-success.sh rejected a 'success' conclusion"
"${cli}" "failure" >/dev/null 2>&1 \
    && fail "require-ci-success.sh accepted a 'failure' conclusion" || true
"${cli}" "" >/dev/null 2>&1 \
    && fail "require-ci-success.sh accepted an empty (not-found) conclusion" || true

echo "ci-status-check: OK — require-ci-success.sh rejects anything but success"
