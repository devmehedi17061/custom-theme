<?php
/**
 * Non-destructive smoke tests for bfd-core. Run from the repo root:
 *   docker compose exec -T wordpress php /var/www/html/bin/smoke-test.php
 */
define( 'WP_USE_THEMES', false );
require dirname( __DIR__ ) . '/wp-load.php';

use BFD\Core\Fields\Sanitizer;
use BFD\Core\Forms\Form_Definition;
use BFD\Core\Sections\Section_Registry;
use BFD\Core\Sections\Template_Engine;

$pass = 0;
$fail = 0;
function check( string $name, bool $ok ): void {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	printf( "%s %s\n", $ok ? 'PASS' : 'FAIL', $name );
}

if ( ! class_exists( Section_Registry::class ) ) {
	echo "FAIL bfd-core is not active\n";
	exit( 1 );
}

/* Sanitizer */
$sel = array( 'key' => 's', 'type' => 'select', 'options' => array( 'a' => 'A', 'b' => 'B' ), 'default' => 'a' );
check( 'select rejects unknown value', 'a' === Sanitizer::value( $sel, 'zzz' ) );
check( 'select accepts valid value', 'b' === Sanitizer::value( $sel, 'b' ) );
check( 'url strips javascript:', '' === Sanitizer::value( array( 'key' => 'u', 'type' => 'url' ), 'javascript:alert(1)' ) );
check( 'text strips tags', 'Hi' === Sanitizer::value( array( 'key' => 't', 'type' => 'text' ), '<script>x</script>Hi' ) );
check( 'image rejects non-attachment id', 0 === Sanitizer::value( array( 'key' => 'i', 'type' => 'image' ), '999999' ) );
check( 'color rejects junk', '' === Sanitizer::value( array( 'key' => 'c', 'type' => 'color' ), 'red;}' ) );
check( 'email validated', '' === Sanitizer::value( array( 'key' => 'e', 'type' => 'email' ), 'not-an-email' ) );

/* Section registry */
$clean = Section_Registry::sanitize_sections(
	array(
		array( 'type' => 'hero', 'settings' => array( 'background' => 'evil' ), 'fields' => array( 'heading' => 'H', 'rogue' => 'x' ) ),
		array( 'type' => 'does_not_exist' ),
		'garbage',
	)
);
check( 'unknown section types dropped', 1 === count( $clean ) );
check( 'invalid setting falls back', 'none' === $clean[0]['settings']['background'] );
check( 'unknown field dropped', ! array_key_exists( 'rogue', $clean[0]['fields'] ) );
foreach ( array( 'hero', 'features', 'cta', 'faq', 'rich_text', 'custom_html', 'form', 'image_text', 'posts', 'testimonials', 'pricing', 'team', 'stats', 'logos', 'gallery', 'video' ) as $type ) {
	check( "built-in section registered: $type", null !== Section_Registry::get( $type ) );
	check( "theme template exists: $type", file_exists( get_theme_root() . '/bfd-theme/template-parts/sections/' . $type . '.php' ) );
}

/* Section options */
$keys = wp_list_pluck( Section_Registry::settings_fields(), 'key' );
foreach ( array( 'background', 'background_image', 'overlay', 'align', 'padding', 'hide', 'anchor', 'css_class' ) as $k ) {
	check( "section option exists: $k", in_array( $k, $keys, true ) );
}
check( 'slug type sanitized', 'my-anchor' === Sanitizer::value( array( 'key' => 'a', 'type' => 'slug' ), 'My Anchor!' ) );
check( 'classes type sanitized', 'foo bar' === Sanitizer::value( array( 'key' => 'c', 'type' => 'classes' ), 'foo "><script> bar' ) || 'foo script bar' === Sanitizer::value( array( 'key' => 'c', 'type' => 'classes' ), 'foo "><script> bar' ) );
check( 'no raw markup survives in classes', false === strpbrk( Sanitizer::value( array( 'key' => 'c', 'type' => 'classes' ), 'x"><script>y' ), '<>"' ) );

/* Template engine */
$defs = array(
	array( 'key' => 'title', 'type' => 'text' ),
	array( 'key' => 'items', 'type' => 'repeater', 'sub_fields' => array( array( 'key' => 'n', 'type' => 'text' ) ) ),
);
$out = Template_Engine::render( '<h1>{{title}}</h1>{{#items}}<i>{{_index}}{{n}}</i>{{/items}}{{^items}}none{{/items}}', array( 'title' => '<b>x</b> {{n}}', 'items' => array( array( 'n' => 'A&B' ) ) ), $defs );
check( 'engine escapes values', false === strpos( $out, '<b>' ) );
check( 'engine does not re-expand values', false !== strpos( $out, '{{n}}' ) );
check( 'engine loops repeaters', false !== strpos( $out, '<i>1A&amp;B</i>' ) );
check( 'engine inverse block hidden when non-empty', false === strpos( $out, 'none' ) );
check( 'engine inverse block shown when empty', false !== strpos( Template_Engine::render( '{{^items}}none{{/items}}', array( 'items' => array() ), $defs ), 'none' ) );

/* Forms */
$fields = Form_Definition::sanitize_fields( array( array( 'label' => 'Name', 'type' => 'text' ), array( 'label' => 'Name', 'type' => 'evil' ) ) );
check( 'form field keys de-duplicated', $fields[0]['key'] !== $fields[1]['key'] );
check( 'form field type whitelisted', 'text' === $fields[1]['type'] );
$conf = Form_Definition::sanitize_settings( array( 'recipients' => 'a@example.com, bad', 'subject' => "Hi\r\nBcc: x@y.z" ) );
check( 'form recipients validated', 'a@example.com' === $conf['recipients'] );
check( 'form subject single line', false === strpbrk( $conf['subject'], "\r\n" ) );

/* Security */
wp_set_current_user( 0 );
$endpoints = apply_filters( 'rest_endpoints', array( '/wp/v2/users' => array(), '/wp/v2/posts' => array() ) );
check( 'anonymous REST users endpoint hidden', ! isset( $endpoints['/wp/v2/users'] ) && isset( $endpoints['/wp/v2/posts'] ) );
check( 'xmlrpc disabled', false === apply_filters( 'xmlrpc_enabled', true ) );
check( 'file editing disabled', defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT );

/* Options + integrations */
$o = new BFD\Core\Admin\Options_Page();
$clean = $o->sanitize( array( 'primary_color' => 'red;}</style>', 'container_width' => '99999', 'ga_id' => 'nope', 'heading_font' => 'HACK', 'custom_css' => '.a{}</style><script>x</script>' ) );
check( 'options: bad colour rejected', '' === $clean['primary_color'] );
check( 'options: absurd width rejected', '' === $clean['container_width'] );
check( 'options: bad analytics id rejected', '' === $clean['ga_id'] );
check( 'options: unknown font falls back', 'system' === $clean['heading_font'] );
check( 'options: custom CSS has no tags', false === strpos( $clean['custom_css'], '<' ) );
check( 'redirect rules ignore javascript: targets', array() === array_filter( BFD\Core\Seo\Redirects::rules(), fn( $r ) => 0 === stripos( $r['to'], 'javascript' ) ) );
check( 'uninstaller keeps data by default', 0 === array_sum( BFD\Core\Uninstaller::run( true ) ) || ! empty( get_option( 'bfd_options' )['delete_on_uninstall'] ) );

/* Post types */
foreach ( array( 'service', 'project', 'testimonial', 'bfd_section', 'bfd_form' ) as $pt ) {
	check( "post type registered: $pt", post_type_exists( $pt ) );
}
check( 'pages do not use the block editor', ! use_block_editor_for_post_type( 'page' ) );

printf( "\n%d passed, %d failed\n", $pass, $fail );
exit( $fail ? 1 : 0 );
