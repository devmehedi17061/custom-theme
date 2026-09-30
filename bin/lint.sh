#!/usr/bin/env bash
# Syntax-check all PHP (inside the WordPress container) and JS (with node). Run from the repo root.
set -u
cd "$(dirname "$0")/.."
fail=0

while IFS= read -r f; do
  out=$(docker compose exec -T wordpress php -l "/var/www/html/$f" 2>&1)
  if ! grep -q "^No syntax errors" <<<"$out"; then echo "$out"; fail=1; fi
done < <(find wp-content/themes/bfd-theme wp-content/plugins/bfd-core -name '*.php')

while IFS= read -r f; do
  node --check "$f" || fail=1
done < <(find wp-content/themes/bfd-theme wp-content/plugins/bfd-core -name '*.js')

[ $fail -eq 0 ] && echo "lint: OK" || { echo "lint: FAILED"; exit 1; }
