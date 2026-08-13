#!/usr/bin/env bash

# CLI glue for .github/workflows/tag-release.yml's validate job: exits non-zero with a message on
# stderr unless the given conclusion is "success". Tested by tests/ci-status-check.sh.
#
# Usage: require-ci-success.sh <conclusion> (empty string means "no check run found")

set -euo pipefail

conclusion="${1}"

if [ -z "${conclusion}" ]; then
    echo 'ci job conclusion is <not found>, not success' >&2
    exit 1
fi

if [ "${conclusion}" != "success" ]; then
    echo "ci job conclusion is \"${conclusion}\", not success" >&2
    exit 1
fi
