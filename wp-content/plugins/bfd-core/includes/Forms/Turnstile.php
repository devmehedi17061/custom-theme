<?php
namespace BFD\Core\Forms;

use BFD\Core\Security\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Optional Cloudflare Turnstile spam check. Active only when both keys are set in BFD Options.
 */
final class Turnstile {

	private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public static function site_key(): string {
		return (string) bfd_option( 'turnstile_site_key' );
	}

	public static function enabled(): bool {
		return '' !== self::site_key() && '' !== (string) bfd_option( 'turnstile_secret' );
	}

	/** Fails closed: a network error counts as a failed check. */
	public static function verify( $token ): bool {
		if ( ! is_string( $token ) || '' === $token || strlen( $token ) > 2048 ) {
			return false;
		}
		$pre = apply_filters( 'bfd_turnstile_verify_result', null, $token );
		if ( null !== $pre ) {
			return (bool) $pre;
		}
		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => (string) bfd_option( 'turnstile_secret' ),
					'response' => $token,
					'remoteip' => Client::ip(),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && ! empty( $data['success'] );
	}
}
