#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="${root}/tests/dist/consumer"

fail() {
    echo "dist-check: $1" >&2
    exit 1
}

command -v composer >/dev/null 2>&1 || fail "composer was not found on PATH"

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

mkdir "${work}/package" "${work}/consumer"
cp "${root}/composer.json" "${work}/package/composer.json"
cp -R "${root}/src" "${root}/bin" "${work}/package/"
cp -R "${fixture}/." "${work}/consumer/"

cd "${work}/consumer"
consumer="$(pwd)"

composer install --no-interaction --no-progress --no-plugins \
    || fail "composer install failed in the consumer project"

[ -f vendor/bin/ray-di-compile ] || fail "composer did not install the vendor/bin/ray-di-compile binary"
[ ! -e vendor/naoki-tsuchiya/ray-di-context/vendor/autoload.php ] \
    || fail "the installed package has a vendor dir of its own; this no longer tests the consumer autoload path"

php vendor/bin/ray-di-compile bootstrap.php "${consumer}" prod \
    || fail "vendor/bin/ray-di-compile exited non-zero"

compiled=(var/di/prod/*ConsumerCarInterface*.php)
[ -f "${compiled[0]}" ] || fail "the compile produced no script for ConsumerCarInterface"

echo "dist-check: OK — compiled $(basename "${compiled[0]}") through vendor/bin/ray-di-compile"
