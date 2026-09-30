<?php
/**
 * Front-end assets. Serves *.min.css/js (built by bin/build-assets.sh) unless SCRIPT_DEBUG is on.
 *
 * @package bfd-theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Relative asset path, preferring the minified build when it exists.
 *
 * @param string $rel Path under assets/, e.g. 'css/main.css'.
 */
function bfd_asset( $rel ) {
	$min = preg_replace( '/\.(css|js)$/', '.min.$1', $rel );
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( BFD_THEME_DIR . '/assets/' . $min ) ) {
		return $min;
	}
	return $rel;
}

function bfd_enqueue_assets() {
	$css_rel  = bfd_asset( 'css/main.css' );
	$css_file = BFD_THEME_DIR . '/assets/' . $css_rel;
	$css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : BFD_THEME_VERSION;

	if ( function_exists( 'bfd_option' ) && bfd_option( 'inline_css' ) && file_exists( $css_file ) && filesize( $css_file ) < 65536 ) {
		// One less request: print the (small) stylesheet in <head>. Trade-off: not cached between pages.
		wp_register_style( 'bfd-main', false, array(), $css_ver );
		wp_enqueue_style( 'bfd-main' );
		wp_add_inline_style( 'bfd-main', (string) file_get_contents( $css_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	} else {
		wp_enqueue_style( 'bfd-main', BFD_THEME_URI . '/assets/' . $css_rel, array(), $css_ver );
	}

	$js_rel  = bfd_asset( 'js/main.js' );
	$js_file = BFD_THEME_DIR . '/assets/' . $js_rel;
	wp_enqueue_script(
		'bfd-main',
		BFD_THEME_URI . '/assets/' . $js_rel,
		array(),
		file_exists( $js_file ) ? filemtime( $js_file ) : BFD_THEME_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'bfd_enqueue_assets' );
