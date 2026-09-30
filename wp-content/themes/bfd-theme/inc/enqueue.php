<?php
defined( 'ABSPATH' ) || exit;

function bfd_enqueue_assets() {
	$css = BFD_THEME_DIR . '/assets/css/main.css';
	$js  = BFD_THEME_DIR . '/assets/js/main.js';

	wp_enqueue_style(
		'bfd-main',
		BFD_THEME_URI . '/assets/css/main.css',
		array(),
		file_exists( $css ) ? filemtime( $css ) : BFD_THEME_VERSION
	);

	wp_enqueue_script(
		'bfd-main',
		BFD_THEME_URI . '/assets/js/main.js',
		array(),
		file_exists( $js ) ? filemtime( $js ) : BFD_THEME_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'bfd_enqueue_assets' );
