<?php
namespace BFD\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Locks an IP out of logging in after too many failures, and hides which half of
 * the credentials was wrong.
 */
final class Login_Limiter {

	public function register(): void {
		// Priority 100: runs after core's credential checks, so a lock also blocks a correct password.
		add_filter( 'authenticate', array( $this, 'check_lock' ), 100, 3 );
		add_action( 'wp_login_failed', array( $this, 'record_failure' ), 10, 2 );
		add_action( 'wp_login', array( $this, 'clear' ) );
		add_filter( 'login_errors', array( $this, 'generic_error' ) );
	}

	private function max_attempts(): int {
		return (int) apply_filters( 'bfd_login_max_attempts', 5 );
	}

	private function lock_seconds(): int {
		return (int) apply_filters( 'bfd_login_lock_seconds', 15 * MINUTE_IN_SECONDS );
	}

	private function key(): string {
		return 'bfd_login_' . substr( Client::ip_hash(), 0, 24 );
	}

	public function check_lock( $user, $username = '', $password = '' ) {
		if ( '' === (string) $username && '' === (string) $password ) {
			return $user;
		}
		if ( (int) get_transient( $this->key() ) >= $this->max_attempts() ) {
			return new \WP_Error(
				'bfd_locked',
				sprintf(
					/* translators: %d: minutes */
					__( 'Too many failed login attempts. Try again in %d minutes.', 'bfd-core' ),
					(int) ceil( $this->lock_seconds() / MINUTE_IN_SECONDS )
				)
			);
		}
		return $user;
	}

	public function record_failure( $username, $error = null ): void {
		if ( $error instanceof \WP_Error && $error->get_error_code() === 'bfd_locked' ) {
			return; // Don't extend the lock with our own error.
		}
		$key   = $this->key();
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, $this->lock_seconds() );
		if ( $count === $this->max_attempts() ) {
			do_action( 'bfd_login_locked' );
		}
	}

	public function clear(): void {
		delete_transient( $this->key() );
	}

	/** Same message for "unknown user" and "wrong password" so usernames can't be probed. */
	public function generic_error( $error ) {
		global $errors;
		if ( is_wp_error( $errors ) ) {
			$codes = $errors->get_error_codes();
			if ( array_intersect( $codes, array( 'invalid_username', 'incorrect_password', 'invalid_email' ) ) ) {
				return __( '<strong>Error:</strong> The username or password is incorrect.', 'bfd-core' );
			}
		}
		return $error;
	}
}
