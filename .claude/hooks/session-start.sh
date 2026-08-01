#!/bin/bash
set -euo pipefail

cd "$CLAUDE_PROJECT_DIR"
if [ "$(id -u)" -eq 0 ]; then
  export COMPOSER_ALLOW_SUPERUSER=1
fi
composer install -n
