#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
packages=(ray/di ray/compiler)

fail() {
    echo "lowest-bound-check: $1" >&2
    exit 1
}

command -v jq >/dev/null 2>&1 || fail "jq was not found on PATH"

# A bare two-segment caret range (^X.Y) names its own lower bound: the written
# numbers plus a trailing .0. Deriving it this way — instead of asking Packagist for
# every published version and picking the minimum — needs no network access beyond
# what `composer update --prefer-lowest` already made, and stays exact for the only
# constraint shape this project declares. Anything else is refused rather than
# guessed at.
lower_bound_of() {
    local constraint="$1"
    if [[ "${constraint}" =~ ^\^([0-9]+)\.([0-9]+)$ ]]; then
        echo "${BASH_REMATCH[1]}.${BASH_REMATCH[2]}.0"
        return 0
    fi
    return 1
}

installed_json="$(composer show --direct --format=json)"

for package in "${packages[@]}"; do
    constraint="$(jq -r --arg pkg "${package}" '.require[$pkg] // empty' "${root}/composer.json")"
    [ -n "${constraint}" ] || fail "${package} is not declared in composer.json's require"

    expected="$(lower_bound_of "${constraint}")" \
        || fail "composer.json's ${package} constraint '${constraint}' is not a bare two-segment caret range (^X.Y); cannot derive its lower bound without hardcoding it"

    actual="$(printf '%s' "${installed_json}" | jq -r --arg pkg "${package}" '.installed[] | select(.name == $pkg) | .version')"
    [ -n "${actual}" ] || fail "${package} is not among the installed direct dependencies (composer show --direct)"

    [ "${actual}" = "${expected}" ] \
        || fail "${package} resolved to ${actual}, expected the declared lower bound ${expected} (from composer.json constraint ${constraint})"

    echo "lowest-bound-check: OK — ${package} resolved to its declared lower bound ${expected}"
done
