#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
ci_file="${root}/.github/workflows/ci.yml"
codecov_file="${root}/.github/codecov.yml"

fail() {
    echo "ci-matrix-check: $1" >&2
    exit 1
}

command -v yq >/dev/null 2>&1 || fail "yq was not found on PATH"
[ -f "${ci_file}" ] || fail "${ci_file} not found"
[ -f "${codecov_file}" ] || fail "${codecov_file} not found"

# jobs.test.strategy.matrix.php is the only PHP matrix that drives a codecov upload
# (jobs.lowest has its own, unrelated one) — query it by path instead of by text
# position so the check keeps working regardless of where the job sits in the file.
matrix_count="$(yq '.jobs.test.strategy.matrix.php | length' "${ci_file}")"
[[ "${matrix_count}" =~ ^[0-9]+$ ]] && [ "${matrix_count}" -gt 0 ] \
    || fail "could not read jobs.test.strategy.matrix.php from ${ci_file}"

after_n_builds="$(yq '.codecov.notify.after_n_builds' "${codecov_file}")"
[[ "${after_n_builds}" =~ ^[0-9]+$ ]] \
    || fail "could not read codecov.notify.after_n_builds from ${codecov_file}"

[ "${after_n_builds}" = "${matrix_count}" ] \
    || fail "after_n_builds (${after_n_builds}) in ${codecov_file} does not match the test job's PHP matrix size (${matrix_count}) in ${ci_file}"

echo "ci-matrix-check: OK — after_n_builds (${after_n_builds}) matches the test job's PHP matrix size"
