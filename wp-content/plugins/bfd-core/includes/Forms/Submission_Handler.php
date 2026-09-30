<?php
namespace BFD\Core\Forms;

use BFD\Core\Security\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and processes a form submission.
 * Order: form exists > nonce > honeypot > timing > rate limit > validate > upload > store > email.
 */
final class Submission_Handler {

	private const MIN_SECONDS = 3;
	private const MAX_SECONDS = DAY_IN_SECONDS;

	/** @return array{ok:bool, code:string, message:string, errors:array<string,string>} */
	public static function handle( int $form_id, array $post, array $files ): array {
		$form = Form_Definition::load( $form_id );
		if ( ! $form ) {
			return self::result( false, 'server' );
		}

		$nonce = isset( $post['_bfd_nonce'] ) && is_string( $post['_bfd_nonce'] ) ? $post['_bfd_nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'bfd_form_' . $form_id ) ) {
			return self::result( false, 'security' );
		}

		// Honeypot: bots fill it. Pretend success so they don't adapt.
		if ( ! empty( $post['bfd_website'] ) ) {
			return self::result( true, 'ok', $form );
		}

		if ( ! self::timing_ok( $form_id, $post['bfd_ts'] ?? '' ) ) {
			return self::result( false, 'timing' );
		}

		if ( ! self::rate_ok( $form_id ) ) {
			return self::result( false, 'rate' );
		}

		if ( Turnstile::enabled() && ! Turnstile::verify( $post['cf-turnstile-response'] ?? '' ) ) {
			return self::result( false, 'captcha' );
		}

		$values = array();
		$errors = array();
		foreach ( $form->fields as $f ) {
			$name = 'f_' . $f['key'];
			if ( 'file' === $f['type'] ) {
				$r = self::file( $f, $files[ $name ] ?? null );
			} else {
				$r = self::value( $f, $post[ $name ] ?? null );
			}
			if ( isset( $r['error'] ) ) {
				$errors[ $f['key'] ] = $r['error'];
			} else {
				$values[ $f['key'] ] = $r['value'];
			}
		}
		if ( $errors ) {
			// Remove files already uploaded for a submission that failed elsewhere.
			foreach ( $form->fields as $f ) {
				if ( 'file' === $f['type'] && ! empty( $values[ $f['key'] ]['path'] ) ) {
					wp_delete_file( $values[ $f['key'] ]['path'] );
				}
			}
			return self::result( false, 'invalid', $form, $errors );
		}

		$rows = array();
		foreach ( $form->fields as $f ) {
			$rows[] = array(
				'key'   => $f['key'],
				'label' => $f['label'],
				'type'  => $f['type'],
				'value' => self::display_value( $f, $values[ $f['key'] ] ?? '' ),
			);
		}

		$stored = ! empty( $form->settings['store'] ) && Submissions::insert( $form_id, $rows );
		$mailed = self::send_mail( $form, $rows );

		if ( ! $stored && ! $mailed ) {
			return self::result( false, 'server' );
		}
		if ( ! empty( $form->settings['autoreply'] ) ) {
			self::send_autoreply( $form, $rows );
		}
		return self::result( true, 'ok', $form );
	}

	private static function result( bool $ok, string $code, ?Form_Definition $form = null, array $errors = array() ): array {
		$message = $ok
			? ( $form ? $form->settings['success_message'] : '' )
			: __( Form_Renderer::MESSAGES[ $code ] ?? Form_Renderer::MESSAGES['server'], 'bfd-core' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		return array( 'ok' => $ok, 'code' => $code, 'message' => $message, 'errors' => $errors );
	}

	private static function timing_ok( int $form_id, $token ): bool {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m ) ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $form_id . '|' . $m[1], wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected, $m[2] ) ) {
			return false;
		}
		$age = time() - (int) $m[1];
		return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
	}

	private static function rate_ok( int $form_id ): bool {
		$limit  = (int) apply_filters( 'bfd_form_rate_limit', 10 );
		$window = (int) apply_filters( 'bfd_form_rate_window', 10 * MINUTE_IN_SECONDS );
		$key    = 'bfd_rl_' . substr( Client::ip_hash(), 0, 24 ) . '_' . $form_id;
		$count  = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/** @return array{value:mixed}|array{error:string} */
	private static function value( array $f, $raw ): array {
		$required = ! empty( $f['required'] );
		$str      = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		$msg_req  = __( 'This field is required.', 'bfd-core' );

		if ( 'checkbox' === $f['type'] ) {
			$on = in_array( $raw, array( '1', 1, true, 'on' ), true );
			return ( $required && ! $on ) ? array( 'error' => $msg_req ) : array( 'value' => $on );
		}

		if ( '' === $str ) {
			return $required ? array( 'error' => $msg_req ) : array( 'value' => '' );
		}

		switch ( $f['type'] ) {
			case 'email':
				$v = sanitize_email( $str );
				return ( $v && is_email( $v ) && strlen( $v ) <= 254 ) ? array( 'value' => $v ) : array( 'error' => __( 'Enter a valid email address.', 'bfd-core' ) );
			case 'tel':
				$v      = preg_replace( '/[^0-9+()\-.\s]/', '', $str );
				$digits = strlen( preg_replace( '/\D/', '', $v ) );
				return ( $digits >= 5 && $digits <= 20 ) ? array( 'value' => $v ) : array( 'error' => __( 'Enter a valid phone number.', 'bfd-core' ) );
			case 'number':
				return is_numeric( $str ) ? array( 'value' => $str + 0 ) : array( 'error' => __( 'Enter a number.', 'bfd-core' ) );
			case 'select':
				$opts = array_map( 'strval', array_keys( Form_Definition::parse_options( (string) ( $f['options'] ?? '' ) ) ) );
				return in_array( $str, $opts, true ) ? array( 'value' => $str ) : array( 'error' => __( 'Choose a valid option.', 'bfd-core' ) );
			case 'textarea':
				return array( 'value' => mb_substr( sanitize_textarea_field( $str ), 0, 5000 ) );
			default:
				return array( 'value' => mb_substr( sanitize_text_field( $str ), 0, 500 ) );
		}
	}

	public static function allowed_mimes(): array {
		return (array) apply_filters(
			'bfd_form_allowed_mimes',
			array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
				'pdf'          => 'application/pdf',
				'doc'          => 'application/msword',
				'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			)
		);
	}

	public static function allowed_extensions(): array {
		$exts = array();
		foreach ( array_keys( self::allowed_mimes() ) as $group ) {
			$exts = array_merge( $exts, explode( '|', $group ) );
		}
		return $exts;
	}

	/** @return array{value:mixed}|array{error:string} */
	private static function file( array $f, $file ): array {
		$required = ! empty( $f['required'] );
		if ( ! is_array( $file ) || ! isset( $file['error'] ) || is_array( $file['error'] ) || UPLOAD_ERR_NO_FILE === $file['error'] ) {
			return $required ? array( 'error' => __( 'Please choose a file.', 'bfd-core' ) ) : array( 'value' => null );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return array( 'error' => __( 'The file could not be uploaded.', 'bfd-core' ) );
		}
		$max = (int) apply_filters( 'bfd_form_max_upload', 5 * MB_IN_BYTES );
		if ( (int) $file['size'] > $max ) {
			/* translators: %s: max size */
			return array( 'error' => sprintf( __( 'The file is too large (max %s).', 'bfd-core' ), size_format( $max ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		self::prepare_upload_dir();
		$to_subdir = static function ( array $dirs ): array {
			$dirs['subdir'] = '/bfd-forms';
			$dirs['path']   = $dirs['basedir'] . '/bfd-forms';
			$dirs['url']    = $dirs['baseurl'] . '/bfd-forms';
			return $dirs;
		};
		add_filter( 'upload_dir', $to_subdir );
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'mimes'                    => self::allowed_mimes(),
				// Random name: uploads are never guessable from the original name.
				'unique_filename_callback' => static fn( $dir, $name, $ext ) => strtolower( wp_generate_password( 20, false ) ) . $ext,
			)
		);
		remove_filter( 'upload_dir', $to_subdir );

		if ( isset( $moved['error'] ) ) {
			return array( 'error' => __( 'This file type is not allowed.', 'bfd-core' ) );
		}
		return array(
			'value' => array(
				'name' => sanitize_file_name( (string) $file['name'] ),
				'url'  => $moved['url'],
				'path' => $moved['file'],
			),
		);
	}

	/** Uploads folder that can never execute or list files. */
	private static function prepare_upload_dir(): void {
		$dir = wp_upload_dir()['basedir'] . '/bfd-forms';
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi)$\">\n\tRequire all denied\n</FilesMatch>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/** Text form of a value for storage/email. Files become "name (url)". */
	private static function display_value( array $f, $value ): string {
		if ( 'checkbox' === $f['type'] ) {
			return $value ? __( 'Yes', 'bfd-core' ) : __( 'No', 'bfd-core' );
		}
		if ( 'file' === $f['type'] ) {
			return is_array( $value ) ? $value['name'] . ' (' . $value['url'] . ')' : '';
		}
		return (string) $value;
	}

	private static function send_mail( Form_Definition $form, array $rows ): bool {
		$to = array_filter( array_map( 'trim', explode( ',', $form->settings['recipients'] ) ), 'is_email' );
		if ( ! $to ) {
			return false;
		}

		$lines = array();
		$reply = '';
		foreach ( $rows as $r ) {
			$lines[] = $r['label'] . ': ' . $r['value'];
			if ( 'email' === $r['type'] && '' === $reply && is_email( $r['value'] ) ) {
				$reply = $r['value'];
			}
		}
		$body = sprintf( "Form: %s\nSent: %s\n\n%s\n", $form->title, current_time( 'mysql' ), implode( "\n", $lines ) );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $reply ) {
			$headers[] = 'Reply-To: ' . $reply; // is_email() rejects line breaks, so no header injection.
		}
		$subject = trim( preg_replace( '/[\r\n]+/', ' ', $form->settings['subject'] ) );

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	/** Confirmation to the sender. Only their own (validated) address is used; the submission is not echoed back. */
	private static function send_autoreply( Form_Definition $form, array $rows ): void {
		foreach ( $rows as $r ) {
			if ( 'email' === $r['type'] && is_email( $r['value'] ) ) {
				wp_mail(
					$r['value'],
					/* translators: %s: site name */
					sprintf( __( '%s: we received your message', 'bfd-core' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
					$form->settings['autoreply_message'],
					array( 'Content-Type: text/plain; charset=UTF-8' )
				);
				return;
			}
		}
	}
}
