#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
example="${root}/examples/docker"

fail() {
    echo "docker-check: $1" >&2
    exit 1
}

command -v docker >/dev/null 2>&1 || fail "docker was not found on PATH"

readme_block="$(mktemp)"
awk '/^```dockerfile$/{flag=1;next} /^```$/{if(flag){exit}} flag' "${root}/README.md" > "${readme_block}"
cmp -s "${readme_block}" "${example}/Dockerfile" \
    || fail "README's Docker Dockerfile block no longer matches examples/docker/Dockerfile"
rm -f "${readme_block}"

work="$(mktemp -d)"
tag="ray-di-context-docker-example:check"
cleanup() {
    docker rmi -f "${tag}" >/dev/null 2>&1 || true
    rm -rf "${work}"
}
trap cleanup EXIT

mkdir "${work}/consumer" "${work}/consumer/package"
cp -R "${example}/." "${work}/consumer/"
cp "${root}/composer.json" "${work}/consumer/package/composer.json"
cp -R "${root}/src" "${root}/bin" "${work}/consumer/package/"

cat > "${work}/consumer/composer.json" <<'JSON'
{
    "repositories": [
        {
            "type": "path",
            "url": "./package",
            "options": {
                "symlink": false,
                "versions": {
                    "naoki-tsuchiya/ray-di-context": "1.0.0"
                }
            }
        }
    ],
    "require": {
        "naoki-tsuchiya/ray-di-context": "1.0.0"
    }
}
JSON

docker build -f "${work}/consumer/Dockerfile" -t "${tag}" "${work}/consumer" \
    || fail "docker build failed"

output="$(docker run --rm --read-only --tmpfs /app/var/tmp "${tag}" 2>&1)" \
    || fail "docker run failed under --read-only with tmpDir mounted as tmpfs: ${output}"

case "${output}" in
    *"resolved GreeterInterface: hello from the compiled injector"*) ;;
    *) fail "container did not report resolving the binding: ${output}" ;;
esac

echo "docker-check: OK — ${output}"
