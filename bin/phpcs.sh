#!/usr/bin/env bash
# WordPress coding-standard check (PHPCS + WPCS). Installs the tools once into /tmp/bfd-phpcs via a throwaway
# composer container, then runs phpcs inside the WordPress container. Usage: bin/phpcs.sh [--fix]
set -euo pipefail
cd "$(dirname "$0")/.."
TOOLS=/tmp/bfd-phpcs
if [ ! -x "$TOOLS/vendor/bin/phpcs" ]; then
  echo "Installing PHPCS + WordPress standards (one time)…"
  mkdir -p "$TOOLS"
  docker run --rm --user "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/c -v "$TOOLS":/app -w /app composer:2 sh -c '
    composer init --no-interaction --name=x/x -q
    composer config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
    composer require --dev --no-interaction --no-progress -q squizlabs/php_codesniffer wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer phpcompatibility/phpcompatibility-wp' >/dev/null
fi
docker compose exec -T wordpress rm -rf /tmp/tools
docker compose cp "$TOOLS" wordpress:/tmp/tools >/dev/null 2>&1
if [ "${1:-}" = "--fix" ]; then
  docker compose exec -T wordpress sh -c 'cd /var/www/html && php /tmp/tools/vendor/bin/phpcbf -q || true'
fi
docker compose exec -T wordpress sh -c 'cd /var/www/html && php /tmp/tools/vendor/bin/phpcs --report=summary -q --no-colors'
