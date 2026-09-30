<?php
/**
 * Design options from Settings > BFD Options > Design, output as CSS variables.
 * Every value is re-validated here; nothing user-supplied is echoed unchecked.
 */
defined( 'ABSPATH' ) || exit;

function bfd_design_css() {
	if ( ! function_exists( 'bfd_option' ) ) {
		return;
	}

	$vars   = array();
	$colors = array(
		'primary_color' => '--color-primary',
		'primary_dark'  => '--color-primary-dark',
		'text_color'    => '--color-text',
		'bg_color'      => '--color-bg',
		'surface_color' => '--color-surface',
	);
	foreach ( $colors as $key => $var ) {
		$hex = sanitize_hex_color( (string) bfd_option( $key ) );
		if ( $hex ) {
			$vars[ $var ] = $hex;
		}
	}
	if ( isset( $vars['--color-primary'] ) && ! isset( $vars['--color-primary-dark'] ) ) {
		$vars['--color-primary-dark'] = 'color-mix(in srgb, ' . $vars['--color-primary'] . ' 80%, black)';
	}

	if ( function_exists( 'bfd_font_stacks' ) ) {
		$stacks = bfd_font_stacks();
		foreach ( array( 'heading_font' => '--font-heading', 'body_font' => '--font-body' ) as $key => $var ) {
			$choice = (string) bfd_option( $key, 'system' );
			if ( 'system' !== $choice && isset( $stacks[ $choice ] ) ) {
				$vars[ $var ] = $stacks[ $choice ][1];
			}
		}
	}

	$width = (int) bfd_option( 'container_width', 0 );
	if ( $width >= 800 && $width <= 1800 ) {
		$vars['--container'] = $width . 'px';
	}

	$css = '';
	if ( $vars ) {
		$css .= ':root{';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		$css .= '}';
	}
	$css .= (string) bfd_option( 'custom_css' );

	if ( '' !== trim( $css ) ) {
		// Values are validated above; custom CSS had its tags stripped on save.
		echo '<style id="bfd-design">' . str_ireplace( '</style', '', $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_head', 'bfd_design_css', 20 );

function bfd_body_classes( $classes ) {
	if ( function_exists( 'bfd_option' ) && bfd_option( 'sticky_header' ) ) {
		$classes[] = 'has-sticky-header';
	}
	return $classes;
}
add_filter( 'body_class', 'bfd_body_classes' );
