<?php
namespace BFD\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes submitted values against a field schema. Anything not in the schema is dropped.
 *
 * Field def: array( 'key', 'type', 'label', 'options'?, 'sub_fields'?, 'default'? )
 * Types: text, textarea, richtext, html, url, email, tel, image, select, checkbox, color, number, repeater.
 */
final class Sanitizer {

	private const MAX_ROWS = 50;

	public static function fields( array $defs, $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( $defs as $def ) {
			$out[ $def['key'] ] = self::value( $def, $raw[ $def['key'] ] ?? null );
		}
		return $out;
	}

	public static function value( array $def, $value ) {
		$scalar = is_scalar( $value ) ? (string) $value : '';

		switch ( $def['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( $scalar );
			case 'richtext':
				return wp_kses_post( $scalar );
			case 'html':
				return current_user_can( 'unfiltered_html' ) ? $scalar : wp_kses_post( $scalar );
			case 'url':
				return esc_url_raw( $scalar );
			case 'slug':
				return sanitize_title( $scalar );
			case 'classes':
				return implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', trim( $scalar ) ) ) ) );
			case 'email':
				return sanitize_email( $scalar );
			case 'tel':
				return preg_replace( '/[^0-9+()\-.\s]/', '', $scalar );
			case 'image':
				$id = absint( $scalar );
				return ( $id && 'attachment' === get_post_type( $id ) ) ? $id : 0;
			case 'select':
				$allowed = array_map( 'strval', array_keys( $def['options'] ?? array() ) );
				if ( in_array( $scalar, $allowed, true ) ) {
					return $scalar;
				}
				return (string) ( $def['default'] ?? ( $allowed[0] ?? '' ) );
			case 'checkbox':
				return in_array( $value, array( true, 1, '1', 'true' ), true );
			case 'color':
				return (string) sanitize_hex_color( $scalar );
			case 'number':
				return is_numeric( $scalar ) ? $scalar + 0 : 0;
			case 'repeater':
				$rows = array();
				if ( is_array( $value ) ) {
					foreach ( array_slice( array_values( $value ), 0, self::MAX_ROWS ) as $row ) {
						$rows[] = self::fields( $def['sub_fields'] ?? array(), $row );
					}
				}
				return $rows;
			case 'text':
			default:
				return sanitize_text_field( $scalar );
		}
	}
}
