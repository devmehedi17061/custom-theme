<?php
namespace BFD\Core\Security;

defined( 'ABSPATH' ) || exit;

final class Client {

	/**
	 * Client IP. Only REMOTE_ADDR is trusted by default (forwarded headers are spoofable);
	 * behind a reverse proxy/CDN, filter `bfd_client_ip` to read the proxy's header.
	 */
	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'bfd_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/** Non-reversible identifier for storing/limiting without keeping raw IPs. */
	public static function ip_hash( ?string $ip = null ): string {
		return hash_hmac( 'sha256', $ip ?? self::ip(), wp_salt( 'auth' ) );
	}
}
