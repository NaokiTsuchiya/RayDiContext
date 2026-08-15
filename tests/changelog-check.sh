#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
support="${root}/.github/scripts/changelog.php"
cli="${root}/.github/scripts/resolve-release-tag.php"
extract_cli="${root}/.github/scripts/extract-changelog-section.php"
format_cli="${root}/.github/scripts/validate-version-format.php"
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

if php "${cli}" "9.9.9" "${changelog}" >/dev/null 2>&1; then
    fail "resolve-release-tag.php accepted a version that does not match CHANGELOG.md"
fi

# Entry-point check: extract-changelog-section.php against the current CHANGELOG.md's own latest
# section.
extract_out="$(php "${extract_cli}" "${expected}" "${changelog}")" \
    || fail "extract-changelog-section.php failed for the current CHANGELOG.md's own latest version"
[ -n "${extract_out}" ] || fail "extract-changelog-section.php printed an empty body for '${expected}'"

if php "${extract_cli}" "9.9.9" "${changelog}" >/dev/null 2>&1; then
    fail "extract-changelog-section.php accepted a version with no CHANGELOG.md section"
fi

# Entry-point check: validate-version-format.php accepts the current latest version and rejects
# a "v"-prefixed one — recovery mode's only guard once a tag already exists.
format_out="$(php "${format_cli}" "${expected}")" \
    || fail "validate-version-format.php rejected the current CHANGELOG.md's own latest version"
[ "${format_out}" = "${expected}" ] || fail "validate-version-format.php printed '${format_out}', expected '${expected}'"

if php "${format_cli}" "v${expected}" >/dev/null 2>&1; then
    fail "validate-version-format.php accepted a \"v\"-prefixed version"
fi

echo "changelog-check: OK — latestReleasedVersion()/resolveReleaseTag() and the CLI agree with CHANGELOG.md (${expected})"
