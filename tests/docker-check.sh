#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
example="${root}/examples/docker"

fail() {
    echo "docker-check: $1" >&2
    exit 1
}

# Docker-free check of examples/docker/bin/build-check-support.php's pure functions — see
# tests/docker-check-probe.php for what it exercises and why.
support="${example}/bin/build-check-support.php"
probe_dir="$(mktemp -d)"
php "${root}/tests/docker-check-probe.php" "${support}" "${probe_dir}" \
    || fail "examples/docker/bin/build-check-support.php's pure functions failed synthetic verification"
rm -rf "${probe_dir}"
echo "docker-check: OK — filterCompileDirFiles()/relativePath() synthetic verification"

command -v docker >/dev/null 2>&1 || fail "docker was not found on PATH"

work="$(mktemp -d)"
tag="ray-di-context-docker-example:check"
broken_tag="ray-di-context-docker-example:check-broken"
cleanup() {
    docker rmi -f "${tag}" >/dev/null 2>&1 || true
    docker rmi -f "${broken_tag}" >/dev/null 2>&1 || true
    rm -rf "${work}"
}
trap cleanup EXIT

mkdir "${work}/consumer" "${work}/consumer/package"
cp -R "${example}/." "${work}/consumer/"
cp "${root}/composer.json" "${work}/consumer/package/composer.json"
cp -R "${root}/src" "${root}/bin" "${work}/consumer/package/"

pkg_version="0.3.0"
php -r '
$path = $argv[1];
$json = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$json["repositories"] = [
    [
        "type" => "path",
        "url" => "./package",
        "options" => [
            "symlink" => false,
            "versions" => [
                "naoki-tsuchiya/ray-di-context" => $argv[2],
            ],
        ],
    ],
];
file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "${work}/consumer/composer.json" "${pkg_version}"

build_log="$(docker build -f "${work}/consumer/Dockerfile" -t "${tag}" "${work}/consumer" 2>&1)" \
    || fail "docker build failed: ${build_log}"

case "${build_log}" in
    *"build-check: resolved GreeterInterface"*) ;;
    *) fail "docker build succeeded but the build-stage check did not report success: ${build_log}" ;;
esac

docker run --rm --read-only --tmpfs /app/var/tmp --entrypoint sh "${tag}" \
    -c 'test "$(id -u)" -ne 0' \
    || fail "container ran as root"

output="$(docker run --rm --read-only --tmpfs /app/var/tmp "${tag}" 2>&1)" \
    || fail "docker run failed under --read-only with tmpDir mounted as tmpfs: ${output}"

case "${output}" in
    *"resolved GreeterInterface: hello from the compiled injector"*) ;;
    *) fail "container did not report resolving the binding: ${output}" ;;
esac

echo "docker-check: OK — ${output}"

# Prove the build-stage check is load-bearing: break the one binding it resolves in a way the
# compile step itself does not catch (nothing else references GreeterInterface as a constructor
# argument, so ray-di-compile stays green) and confirm `docker build` — not just `docker run` —
# now fails because of it.
broken="${work}/consumer-broken"
cp -R "${work}/consumer" "${broken}"
sed -i.bak '/bind(GreeterInterface::class)->to(Greeter::class)/d' "${broken}/bootstrap.php"
rm -f "${broken}/bootstrap.php.bak"
grep -q 'bind(GreeterInterface::class)' "${broken}/bootstrap.php" \
    && fail "sed did not remove the GreeterInterface binding from the broken fixture"

broken_log="$(docker build -f "${broken}/Dockerfile" -t "${broken_tag}" "${broken}" 2>&1)" \
    && fail "docker build succeeded with GreeterInterface unbound — the build-stage check did not catch it: ${broken_log}"

case "${broken_log}" in
    *"build-check:"*"Unbound"*) ;;
    *) fail "docker build failed but not with the expected build-check/Unbound message: ${broken_log}" ;;
esac

echo "docker-check: OK — a broken binding correctly fails the build stage"
