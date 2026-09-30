<?php
namespace BFD\Core\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * A form built in the admin: fields + settings, stored as post meta on a bfd_form post.
 * Field: array( key, label, type, required, options ). Types: text, email, tel, textarea, select, checkbox, number, file.
 */
final class Form_Definition {

	public const POST_TYPE   = 'bfd_form';
	public const META_FIELDS = '_bfd_form_fields';
	public const META_CONF   = '_bfd_form_settings';
	public const TYPES       = array( 'text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'number', 'file', 'step' );
	public const OPERATORS   = array( 'equals', 'not_equals', 'not_empty', 'empty' );

	public int $id;
	public string $title;
	public array $fields;
	public array $settings;

	private function __construct( int $id, string $title, array $fields, array $settings ) {
		$this->id       = $id;
		$this->title    = $title;
		$this->fields   = $fields;
		$this->settings = $settings;
	}

	public static function load( int $id ): ?self {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$fields = json_decode( (string) get_post_meta( $id, self::META_FIELDS, true ), true );
		$conf   = json_decode( (string) get_post_meta( $id, self::META_CONF, true ), true );
		return new self(
			$id,
			$post->post_title,
			is_array( $fields ) ? $fields : array(),
			array_merge( self::default_settings(), is_array( $conf ) ? $conf : array() )
		);
	}

	public static function default_settings(): array {
		return array(
			'recipients'        => (string) get_option( 'admin_email' ),
			'subject'           => __( 'New form submission', 'bfd-core' ),
			'success_message'   => __( 'Thank you! Your message has been sent.', 'bfd-core' ),
			'submit_label'      => __( 'Send', 'bfd-core' ),
			'store'             => true,
			'autoreply'         => false,
			'autoreply_message' => __( 'Thanks for getting in touch. We have received your message and will reply soon.', 'bfd-core' ),
		);
	}

	/** @return array<int,string> id => title of published forms. */
	public static function published(): array {
		$out = array();
		foreach ( get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		) as $p ) {
			$out[ $p->ID ] = $p->post_title;
		}
		return $out;
	}

	public static function parse_options( string $text ): array {
		$opts = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' !== $parts[0] ) {
				$opts[ $parts[0] ] = $parts[1] ?? $parts[0];
			}
		}
		return $opts;
	}

	/** Clean field definitions coming from the admin JSON. */
	public static function sanitize_fields( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out        = array();
		$seen       = array();
		$fields_raw = array();
		foreach ( array_slice( array_values( $raw ), 0, 40 ) as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$fields_raw[] = $f;
			$type         = is_string( $f['type'] ?? '' ) && in_array( $f['type'], self::TYPES, true ) ? $f['type'] : 'text';
			$label        = sanitize_text_field( is_scalar( $f['label'] ?? '' ) ? (string) ( $f['label'] ?? '' ) : '' );
			$key          = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '_', is_scalar( $f['key'] ?? '' ) ? (string) ( $f['key'] ?? '' ) : '' ), '_' ) );
			if ( '' === $key ) {
				$key = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '_', $label ), '_' ) );
			}
			$key  = '' === $key ? 'field' : ( preg_match( '/^[a-z]/', $key ) ? $key : 'f_' . $key );
			$key  = substr( $key, 0, 40 );
			$base = $key;
			for ( $n = 2; isset( $seen[ $key ] ); $n++ ) {
				$key = $base . '_' . $n;
			}
			$seen[ $key ] = true;

			$def = array(
				'key'      => $key,
				'label'    => '' !== $label ? $label : $key,
				'type'     => $type,
				'required' => ! empty( $f['required'] ),
			);
			if ( 'select' === $type ) {
				$opts           = $f['options'] ?? '';
				$def['options'] = sanitize_textarea_field( is_scalar( $opts ) ? (string) $opts : '' );
			}
			$out[] = $def;
		}
		// Second pass: conditions may only look at an EARLIER, visible-type field.
		$before = array();
		foreach ( $out as $n => $def ) {
			$cond = $fields_raw[ $n ]['show_if'] ?? null;
			if ( 'step' !== $def['type'] && is_array( $cond ) && isset( $cond['field'], $cond['op'] ) && is_string( $cond['field'] ) && is_string( $cond['op'] ) ) {
				$ref = $before[ $cond['field'] ] ?? null;
				if ( $ref && in_array( $cond['op'], self::OPERATORS, true ) ) {
					$value                = is_scalar( $cond['value'] ?? '' ) ? mb_substr( sanitize_text_field( (string) ( $cond['value'] ?? '' ) ), 0, 200 ) : '';
					$out[ $n ]['show_if'] = array(
						'field' => $cond['field'],
						'op'    => $cond['op'],
						'value' => $value,
					);
				}
			}
			if ( ! in_array( $def['type'], array( 'step', 'file' ), true ) ) {
				$before[ $def['key'] ] = $def;
			}
		}

		return $out;
	}

	public static function sanitize_settings( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$def = self::default_settings();

		$emails = array();
		foreach ( explode( ',', is_scalar( $raw['recipients'] ?? '' ) ? (string) ( $raw['recipients'] ?? '' ) : '' ) as $e ) {
			$e = sanitize_email( trim( $e ) );
			if ( $e ) {
				$emails[] = $e;
			}
		}
		$line = static fn( $v, $fallback ) => ( is_scalar( $v ) && '' !== trim( sanitize_text_field( (string) $v ) ) ) ? sanitize_text_field( (string) $v ) : $fallback;

		return array(
			'recipients'        => $emails ? implode( ', ', $emails ) : $def['recipients'],
			'subject'           => $line( $raw['subject'] ?? '', $def['subject'] ),
			'success_message'   => $line( $raw['success_message'] ?? '', $def['success_message'] ),
			'submit_label'      => $line( $raw['submit_label'] ?? '', $def['submit_label'] ),
			'store'             => ! empty( $raw['store'] ),
			'autoreply'         => ! empty( $raw['autoreply'] ),
			'autoreply_message' => is_scalar( $raw['autoreply_message'] ?? '' ) && '' !== trim( (string) ( $raw['autoreply_message'] ?? '' ) )
				? sanitize_textarea_field( (string) $raw['autoreply_message'] )
				: $def['autoreply_message'],
		);
	}
}
