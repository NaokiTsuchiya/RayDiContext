#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
ci_file="${root}/.github/workflows/ci.yml"
codecov_file="${root}/.github/codecov.yml"

fail() {
    echo "ci-matrix-check: $1" >&2
    exit 1
}

[ -f "${ci_file}" ] || fail "${ci_file} not found"
[ -f "${codecov_file}" ] || fail "${codecov_file} not found"

# The `test:` job is the only one whose matrix drives a codecov upload — scope the
# search to its block, not the whole file, since the `lowest:` job has its own
# unrelated `php:` matrix key.
test_block="$(awk '/^  test:$/{flag=1; next} /^  [a-zA-Z_-]+:$/{flag=0} flag' "${ci_file}")"
[ -n "${test_block}" ] || fail "could not locate the 'test:' job block in ${ci_file}"

php_line="$(printf '%s\n' "${test_block}" | grep -E "^[[:space:]]*php:[[:space:]]*\[" | head -1)"
[ -n "${php_line}" ] || fail "could not find the php matrix line inside the 'test:' job block"

matrix_count="$(printf '%s\n' "${php_line}" | grep -oE "'[^']*'" | wc -l | tr -d ' ')"
[ "${matrix_count}" -gt 0 ] || fail "the php matrix in the 'test:' job appears empty"

after_n_builds="$(grep -E '^[[:space:]]*after_n_builds:[[:space:]]*[0-9]+' "${codecov_file}" | grep -oE '[0-9]+' | head -1)"
[ -n "${after_n_builds}" ] || fail "could not find after_n_builds in ${codecov_file}"

[ "${after_n_builds}" = "${matrix_count}" ] \
    || fail "after_n_builds (${after_n_builds}) in ${codecov_file} does not match the test job's PHP matrix size (${matrix_count}) in ${ci_file}"

echo "ci-matrix-check: OK — after_n_builds (${after_n_builds}) matches the test job's PHP matrix size"
