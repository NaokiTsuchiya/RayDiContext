#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
ref="${1:-HEAD}"

fail() {
    echo "gitattributes-check: $1" >&2
    exit 1
}

top_level_files=(CHANGELOG.md LICENSE README.md composer.json)
top_level_dirs=(bin src)

expected="$(mktemp)"
actual="$(mktemp)"
trap 'rm -f "${expected}" "${actual}"' EXIT

{
    printf '%s\n' "${top_level_files[@]}"
    for dir in "${top_level_dirs[@]}"; do
        git -C "${root}" ls-files "${dir}"
    done
} | sort -u > "${expected}"

git -C "${root}" archive "${ref}" | tar -t | grep -v '/$' | sort -u > "${actual}"

extra="$(comm -23 "${actual}" "${expected}")"
missing="$(comm -13 "${actual}" "${expected}")"

if [ -n "${extra}" ] || [ -n "${missing}" ]; then
    [ -z "${extra}" ]   || { echo "unexpected entries in the archive:" >&2; echo "${extra}" >&2; }
    [ -z "${missing}" ] || { echo "expected entries missing from the archive:" >&2; echo "${missing}" >&2; }
    fail "archive contents for ${ref} do not match the expected distribution"
fi

count="$(wc -l < "${expected}" | tr -d ' ')"
echo "gitattributes-check: OK — ${ref} archive matches the expected ${count} files"
