#!/usr/bin/env bash
# Real-browser tests of the admin UI: headless Chrome, logged in, real TinyMCE, real saves.
# Needs: docker compose up, google-chrome. Installs a TEMPORARY login helper (mu-plugin) and always removes it.
set -u
cd "$(dirname "$0")/.."
CHROME="$(command -v google-chrome || command -v chromium || true)"
[ -z "$CHROME" ] && { echo "Chrome/Chromium not found"; exit 2; }
BASE="http://localhost:8081"
SECRET="$(head -c 24 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 24)"
MU="wp-content/mu-plugins/zz-bfd-ui-test.php"

cleanup() {
  rm -f "$MU"; rmdir wp-content/mu-plugins 2>/dev/null || true
  docker compose exec -T wordpress php /var/www/html/bin/ui-tests/fixture.php teardown >/dev/null 2>&1 || true
}
trap cleanup EXIT

mkdir -p wp-content/mu-plugins
cat > "$MU" <<PHP
<?php
// TEMPORARY: created by bin/ui-test.sh, removed on exit. Never ship this file.
add_action( 'init', function () {
	if ( ( \$_GET['bfd_login'] ?? '' ) !== '$SECRET' ) { return; }
	\$u = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
	wp_set_current_user( \$u->ID );
	wp_set_auth_cookie( \$u->ID, true );
	\$to = (string) ( \$_GET['to'] ?? '/wp-admin/' );
	wp_safe_redirect( home_url( 0 === strpos( \$to, '/wp-admin/' ) ? \$to : '/wp-admin/' ) );
	exit;
}, 1 );
add_action( 'wp_footer', function () {
	\$t = (string) ( \$_GET['bfd_t'] ?? '' );
	if ( ( \$_GET['bfd_s'] ?? '' ) !== '$SECRET' || ! preg_match( '/^[a-z0-9_]+\$/', \$t ) ) { return; }
	\$dir = ABSPATH . 'bin/ui-tests/';
	if ( is_readable( \$dir . '_lib.js' ) && is_readable( \$dir . \$t . '.js' ) ) {
		echo '<script>' . file_get_contents( \$dir . '_lib.js' ) . file_get_contents( \$dir . \$t . '.js' ) . '</script>';
	}
} );
add_action( 'admin_footer', function () {
	\$t = (string) ( \$_GET['bfd_t'] ?? '' );
	if ( ! current_user_can( 'manage_options' ) || ! preg_match( '/^[a-z0-9_]+\$/', \$t ) ) { return; }
	\$dir = ABSPATH . 'bin/ui-tests/';
	if ( is_readable( \$dir . '_lib.js' ) && is_readable( \$dir . \$t . '.js' ) ) {
		echo '<script>' . file_get_contents( \$dir . '_lib.js' ) . file_get_contents( \$dir . \$t . '.js' ) . '</script>';
	}
} );
PHP

FIX="$(docker compose exec -T wordpress php /var/www/html/bin/ui-tests/fixture.php setup 2>/dev/null | tail -1)"
PAGE="$(python3 -c "import json,sys;print(json.loads(sys.argv[1])['page'])" "$FIX")"
CUSTOM="$(python3 -c "import json,sys;print(json.loads(sys.argv[1])['custom'])" "$FIX")"
FORMPAGE="$(python3 -c "import json,sys;print(json.loads(sys.argv[1])['formpage'])" "$FIX")"

check_output() { # name, url
  local name="$1" url="$2" out dom
  dom="$(timeout 120 "$CHROME" --headless=new --no-sandbox --disable-gpu --virtual-time-budget=30000 --dump-dom "$url" 2>/dev/null)"
  out="$(python3 -c "import sys,re,html;m=re.search(r'<pre id=\"uitest\">(.*?)</pre>',sys.stdin.read(),re.S);print(html.unescape(m.group(1)) if m else '')" <<<"$dom")"
  echo "== $name"
  if [ -z "$out" ]; then
    echo "FAIL no result (page did not load or test crashed). Page title: $(grep -o '<title>[^<]*' <<<"$dom" | head -1 | sed 's/<title>//')"
    [ -n "${UI_DEBUG:-}" ] && echo "$dom" > "/tmp/ui-$name.html"
    return 1
  fi
  echo "$out"
  ! grep -qE '^(FAIL|ERROR)' <<<"$out"
}

run() { # name, admin-path (logs in first)
  local enc sep="?"; [[ "$2" == *"?"* ]] && sep="&"
  enc="$(python3 -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1],safe=''))" "$2${sep}bfd_t=$1")"
  check_output "$1" "$BASE/?bfd_login=$SECRET&to=$enc"
}

run_front() { # name, front-end path (no login)
  local sep="?"; [[ "$2" == *"?"* ]] && sep="&"
  check_output "$1" "$BASE$2${sep}bfd_t=$1&bfd_s=$SECRET"
}

rc=0
run builder        "/wp-admin/post.php?post=$PAGE&action=edit"          || rc=1
run forms          "/wp-admin/post-new.php?post_type=bfd_form"          || rc=1
if [ "$CUSTOM" != "0" ]; then run custom_section "/wp-admin/post.php?post=$CUSTOM&action=edit" || rc=1; fi
run options        "/wp-admin/options-general.php?page=bfd-options"     || rc=1
run profile_2fa    "/wp-admin/profile.php"                              || rc=1
run_front frontend_form "/?page_id=$FORMPAGE"                            || rc=1
echo; [ $rc -eq 0 ] && echo "ui-test: ALL PASSED" || echo "ui-test: FAILURES"
exit $rc
