<?php
namespace BFD\Core\Security;

use BFD\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Hardening. Every feature can be switched off with the `bfd_security_features` filter
 * (return the array without the feature's key).
 */
final class Security_Module implements Module {

	public function slug(): string {
		return 'security';
	}

	private function enabled( string $feature ): bool {
		$all = (array) apply_filters(
			'bfd_security_features',
			array( 'xmlrpc', 'rest_users', 'author_enum', 'headers', 'login', 'version', 'file_edit', 'activity_log', 'two_factor', 'csp' )
		);
		return in_array( $feature, $all, true );
	}

	public function register(): void {
		if ( $this->enabled( 'file_edit' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		if ( $this->enabled( 'xmlrpc' ) ) {
			// xmlrpc_enabled=false leaves system.* methods (e.g. multicall brute-forcing) answering, so end the request.
			if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				status_header( 403 );
				header( 'Content-Type: text/plain; charset=utf-8' );
				exit( 'XML-RPC is disabled.' );
			}
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'wp_headers', array( $this, 'drop_pingback_header' ) );
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( $this->enabled( 'rest_users' ) ) {
			add_filter( 'rest_endpoints', array( $this, 'hide_user_endpoints' ) );
		}

		if ( $this->enabled( 'author_enum' ) ) {
			add_action( 'template_redirect', array( $this, 'block_author_enumeration' ), 0 );
		}

		if ( $this->enabled( 'headers' ) ) {
			add_action( 'send_headers', array( $this, 'send_headers' ) );
			add_action( 'login_init', array( $this, 'send_headers' ) );
		}

		if ( $this->enabled( 'login' ) ) {
			( new Login_Limiter() )->register();
		}
		if ( $this->enabled( 'activity_log' ) ) {
			( new Activity_Log() )->register();
		}
		if ( $this->enabled( 'two_factor' ) ) {
			( new Two_Factor() )->register();
		}

		if ( $this->enabled( 'version' ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'style_loader_src', array( $this, 'strip_wp_version' ) );
			add_filter( 'script_loader_src', array( $this, 'strip_wp_version' ) );
		}
	}

	public function drop_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/** Anonymous visitors must not be able to list users (usernames are half a login). */
	public function hide_user_endpoints( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/** ?author=N and /author/name/ reveal usernames: 404 for guests. */
	public function block_author_enumeration(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['author'] ) || is_author() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			exit;
		}
	}

	public function send_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		$headers = array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
		);
		if ( is_ssl() ) {
			$headers['Strict-Transport-Security'] = 'max-age=15552000';
		}
		$csp = $this->csp_header();
		if ( $csp ) {
			$headers[ $csp[0] ] = $csp[1];
		}
		// Content-Security-Policy is off by default because inline styles/scripts from custom
		// sections would break. Opt in, ideally as Report-Only first:
		// add_filter( 'bfd_security_headers', fn( $h ) => $h + [ 'Content-Security-Policy-Report-Only' => "default-src 'self'" ] );
		foreach ( (array) apply_filters( 'bfd_security_headers', $headers ) as $name => $value ) {
			if ( '' !== (string) $value && ! headers_sent() ) {
				header( $name . ': ' . str_replace( array( "\r", "\n" ), '', (string) $value ) );
			}
		}
	}

	public function strip_wp_version( string $src ): string {
		return ( false !== strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) ? remove_query_arg( 'ver', $src ) : $src;
	}

	/**
	 * Optional CSP for the public site (never wp-admin or the login page).
	 * The policy allows inline styles/scripts because sections, design options and analytics use them,
	 * but blocks every external origin that is not needed, plus plugins (object-src) and framing by other sites.
	 *
	 * @return array{0:string,1:string}|null header name and value
	 */
	private function csp_header(): ?array {
		global $pagenow;
		$mode = (string) bfd_option( 'csp_mode', 'off' );
		if ( ! $this->enabled( 'csp' ) || ! in_array( $mode, array( 'report_only', 'enforce' ), true ) || is_admin() || 'wp-login.php' === $pagenow ) {
			return null;
		}

		$script  = array( "'self'", "'unsafe-inline'" );
		$frame   = array( 'https://www.youtube-nocookie.com', 'https://player.vimeo.com' );
		$connect = array( "'self'" );
		if ( \BFD\Core\Forms\Turnstile::enabled() ) {
			$script[] = 'https://challenges.cloudflare.com';
			$frame[]  = 'https://challenges.cloudflare.com';
		}
		if ( '' !== (string) bfd_option( 'ga_id' ) ) {
			$script[]  = 'https://www.googletagmanager.com';
			$connect[] = 'https://*.google-analytics.com';
			$connect[] = 'https://*.analytics.google.com';
			$connect[] = 'https://www.googletagmanager.com';
		}

		$directives = array(
			'default-src'     => array( "'self'" ),
			'script-src'      => $script,
			'style-src'       => array( "'self'", "'unsafe-inline'" ),
			'img-src'         => array( "'self'", 'data:', 'https:' ),
			'font-src'        => array( "'self'", 'data:' ),
			'media-src'       => array( "'self'", 'https:' ),
			'frame-src'       => $frame,
			'connect-src'     => $connect,
			'object-src'      => array( "'none'" ),
			'base-uri'        => array( "'self'" ),
			'form-action'     => array( "'self'" ),
			'frame-ancestors' => array( "'self'" ),
		);
		$directives = (array) apply_filters( 'bfd_csp_directives', $directives );

		$parts = array();
		foreach ( $directives as $name => $sources ) {
			$parts[] = $name . ' ' . implode( ' ', array_map( 'strval', (array) $sources ) );
		}
		return array(
			'enforce' === $mode ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
			implode( '; ', $parts ),
		);
	}
}
