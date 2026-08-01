#!/bin/bash
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"
if [ "$(id -u)" -eq 0 ]; then
  export COMPOSER_ALLOW_SUPERUSER=1
fi
composer install -n
