<?php
defined( 'ABSPATH' ) || exit;

function bfd_register_menus() {
	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'bfd-theme' ),
			'footer'  => __( 'Footer Menu', 'bfd-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'bfd_register_menus' );
