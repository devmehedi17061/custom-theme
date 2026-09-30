<?php
namespace BFD\Core\Sections;

defined( 'ABSPATH' ) || exit;

/**
 * Tiny logic-less template engine for custom sections.
 *
 *   {{key}}            escaped value (image => URL, url => esc_url, richtext => kses, textarea => nl2br)
 *   {{key:img}}        image as <img> (optionally {{key:img:large}})
 *   {{key:url:large}}  image URL of a given size
 *   {{#key}}..{{/key}} repeater loop, or "if not empty"
 *   {{^key}}..{{/key}} "if empty"
 *   {{_index}}         1-based row number inside a repeater loop
 *
 * Values are never re-scanned for template syntax; only the author's template text is parsed.
 */
final class Template_Engine {

	private const BLOCK = '/\{\{([#^])\s*([a-z][a-z0-9_]*)\s*\}\}(.*?)\{\{\/\s*\2\s*\}\}/s';
	private const VAR   = '/\{\{\s*([a-z_][a-z0-9_]*)(?::([a-z]+))?(?::([a-z0-9_\-]+))?\s*\}\}/i';

	public static function render( string $tpl, array $ctx, array $defs ): string {
		$by_key = array();
		foreach ( $defs as $d ) {
			$by_key[ $d['key'] ] = $d;
		}

		$out = '';
		$pos = 0;
		while ( preg_match( self::BLOCK, $tpl, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$start = $m[0][1];
			$out  .= self::vars( substr( $tpl, $pos, $start - $pos ), $ctx, $by_key );
			$out  .= self::block( $m[1][0], $m[2][0], $m[3][0], $ctx, $defs, $by_key );
			$pos   = $start + strlen( $m[0][0] );
		}
		return $out . self::vars( substr( $tpl, $pos ), $ctx, $by_key );
	}

	private static function block( string $mode, string $name, string $inner, array $ctx, array $defs, array $by_key ): string {
		$def = $by_key[ $name ] ?? null;
		$val = $ctx[ $name ] ?? null;

		if ( '^' === $mode ) {
			return self::truthy( $val ) ? '' : self::render( $inner, $ctx, $defs );
		}

		if ( $def && 'repeater' === $def['type'] ) {
			$out = '';
			foreach ( is_array( $val ) ? array_values( $val ) : array() as $i => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$row['_index'] = $i + 1;
				$out          .= self::render( $inner, $row, $def['sub_fields'] ?? array() );
			}
			return $out;
		}

		return self::truthy( $val ) ? self::render( $inner, $ctx, $defs ) : '';
	}

	private static function truthy( $val ): bool {
		return ! ( null === $val || '' === $val || false === $val || 0 === $val || '0' === $val || array() === $val );
	}

	private static function vars( string $text, array $ctx, array $by_key ): string {
		return (string) preg_replace_callback(
			self::VAR,
			static function ( array $m ) use ( $ctx, $by_key ): string {
				$key = $m[1];
				$mod = strtolower( $m[2] ?? '' );
				$arg = $m[3] ?? '';

				if ( '_index' === $key ) {
					return (string) (int) ( $ctx['_index'] ?? 0 );
				}
				$def = $by_key[ $key ] ?? null;
				if ( ! $def ) {
					return '';
				}
				$val = $ctx[ $key ] ?? '';

				switch ( $def['type'] ) {
					case 'image':
						$id = (int) $val;
						if ( ! $id ) {
							return '';
						}
						$size = self::size( $arg );
						if ( 'img' === $mod ) {
							return wp_get_attachment_image( $id, $size, false, array( 'loading' => 'lazy' ) );
						}
						return esc_url( (string) wp_get_attachment_image_url( $id, $size ) );
					case 'url':
						return esc_url( (string) $val );
					case 'richtext':
						return wp_kses_post( (string) $val );
					case 'textarea':
						return nl2br( esc_html( (string) $val ) );
					case 'checkbox':
						return $val ? '1' : '';
					case 'repeater':
						return '';
					default:
						return esc_html( is_scalar( $val ) ? (string) $val : '' );
				}
			},
			$text
		);
	}

	private static function size( string $size ): string {
		return in_array( $size, array_merge( array( 'full' ), get_intermediate_image_sizes() ), true ) ? $size : 'full';
	}
}
