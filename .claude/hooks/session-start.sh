#!/bin/bash
set -euo pipefail

cd "$CLAUDE_PROJECT_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install -n
