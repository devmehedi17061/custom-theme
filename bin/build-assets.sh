#!/usr/bin/env bash
# Minify the front-end assets with esbuild (downloaded on demand via npx). Writes *.min.css / *.min.js next to the sources.
# The theme/plugin serve the .min file automatically unless SCRIPT_DEBUG is on. Re-run after editing the sources.
set -euo pipefail
cd "$(dirname "$0")/.."
ES="npx --yes esbuild@0.28.2"
THEME=wp-content/themes/bfd-theme/assets
CORE=wp-content/plugins/bfd-core/assets

build() { # source file
  local src="$1" out="${1%.*}.min.${1##*.}"
  $ES "$src" --minify --legal-comments=none --log-level=warning --outfile="$out"
  printf "%-62s %6d -> %6d bytes\n" "$src" "$(wc -c <"$src")" "$(wc -c <"$out")"
}

build "$THEME/css/main.css"
build "$THEME/js/main.js"
build "$CORE/js/form.js"
