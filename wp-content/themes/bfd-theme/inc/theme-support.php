<?php
defined( 'ABSPATH' ) || exit;

function bfd_theme_setup() {
	load_theme_textdomain( 'bfd-theme', BFD_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_image_size( 'bfd-card', 640, 400, true );
	add_image_size( 'bfd-hero', 1600, 800, true );
}
add_action( 'after_setup_theme', 'bfd_theme_setup' );

function bfd_content_width() {
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'bfd_content_width', 0 );
