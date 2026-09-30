<?php
namespace BFD\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Simple redirect manager. Rules come from BFD Options > SEO defaults, one per line:
 *   /old-path  /new-path        (301)
 *   /old-path  https://example.com/new  302
 * Targets must be a site path or an http(s) URL. GET/HEAD requests only.
 */
final class Redirects {

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/** @return array<string, array{to:string, code:int}> keyed by normalized source path. */
	public static function rules(): array {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) bfd_option( 'redirects' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$parts = preg_split( '/\s+/', $line );
			if ( count( $parts ) < 2 || '/' !== $parts[0][0] ) {
				continue;
			}
			$code = isset( $parts[2] ) && in_array( (int) $parts[2], array( 301, 302, 307, 308 ), true ) ? (int) $parts[2] : 301;
			$to   = self::target( $parts[1] );
			$from = self::normalize( $parts[0] );
			if ( '' === $to || '' === $from ) {
				continue;
			}
			$rules[ $from ] = array(
				'to'   => $to,
				'code' => $code,
			);
		}
		return $rules;
	}

	private static function target( string $to ): string {
		if ( '/' === $to[0] && 0 !== strpos( $to, '//' ) ) {
			return home_url( $to );
		}
		return preg_match( '#^https?://#i', $to ) ? esc_url_raw( $to ) : '';
	}

	private static function normalize( string $path ): string {
		$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		return strtolower( trim( rawurldecode( $path ), '/' ) );
	}

	public function maybe_redirect(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( is_admin() || ! in_array( $method, array( 'GET', 'HEAD' ), true ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$rules = self::rules();
		if ( ! $rules ) {
			return;
		}

		$path = self::normalize( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		$base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' ); // WordPress in a sub-directory.
		if ( '' !== $base && 0 === strpos( $path, strtolower( $base ) ) ) {
			$path = trim( substr( $path, strlen( $base ) ), '/' );
		}

		if ( isset( $rules[ $path ] ) ) {
			$to = $rules[ $path ]['to'];
			if ( trim( (string) wp_parse_url( $to, PHP_URL_PATH ), '/' ) === $path && wp_parse_url( $to, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
				return; // Would redirect to itself.
			}
			wp_redirect( $to, $rules[ $path ]['code'], 'BFD Core' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- target validated in self::target().
			exit;
		}
	}
}
