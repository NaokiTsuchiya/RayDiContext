#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
support="${root}/.github/scripts/changelog.php"
cli="${root}/.github/scripts/resolve-release-tag.php"
changelog="${root}/CHANGELOG.md"

fail() {
    echo "changelog-check: $1" >&2
    exit 1
}

# Independent oracle, read directly off CHANGELOG.md instead of through the function under test.
expected="$(grep -m1 -E '^## \[[0-9]+\.[0-9]+\.[0-9]+\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$' "${changelog}" \
    | sed -E 's/^## \[([0-9]+\.[0-9]+\.[0-9]+)\].*/\1/')"
[ -n "${expected}" ] || fail "could not read a confirmed version heading from ${changelog}"

php "${root}/tests/changelog-check-probe.php" "${support}" "${changelog}" "${expected}" \
    || fail "changelog.php's pure functions failed synthetic verification"

# Entry-point check: the CLI the workflow actually invokes, not just the functions it wraps.
cli_out="$(php "${cli}" "${expected}" "${changelog}")" \
    || fail "resolve-release-tag.php failed for the current CHANGELOG.md's own latest version"
[ "${cli_out}" = "${expected}" ] || fail "resolve-release-tag.php printed '${cli_out}', expected '${expected}'"

php "${cli}" "9.9.9" "${changelog}" >/dev/null 2>&1 \
    && fail "resolve-release-tag.php accepted a version that does not match CHANGELOG.md" || true

echo "changelog-check: OK — latestReleasedVersion()/resolveReleaseTag() and the CLI agree with CHANGELOG.md (${expected})"
