<?php
namespace BFD\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Optional two-factor authentication (TOTP, RFC 6238) for wp-login.php, per user.
 *
 * - Secrets are stored encrypted (libsodium, key derived from the site's salts).
 * - Codes are single-use (replay-protected); 8 one-time backup codes are issued at setup.
 * - Wrong codes count toward the login lockout like wrong passwords.
 * - Set up on the user's profile page. Application passwords / REST are not affected.
 */
final class Two_Factor {

	private const META_SECRET = '_bfd_2fa_secret';
	private const META_LAST   = '_bfd_2fa_last';
	private const META_BACKUP = '_bfd_2fa_backup';
	private const STEP        = 30;

	public static function available(): bool {
		return function_exists( 'sodium_crypto_secretbox' );
	}

	public function register(): void {
		if ( ! self::available() ) {
			return;
		}
		add_action( 'login_form', array( $this, 'login_field' ) );
		add_filter( 'authenticate', array( $this, 'check_code' ), 50, 1 );

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'show_user_profile', array( $this, 'profile_section' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_section' ) );
		add_action( 'admin_post_bfd_2fa_start', array( $this, 'handle_start' ) );
		add_action( 'admin_post_bfd_2fa_enable', array( $this, 'handle_enable' ) );
		add_action( 'admin_post_bfd_2fa_disable', array( $this, 'handle_disable' ) );
		add_action( 'admin_notices', array( $this, 'admin_nag' ) );
	}

	/* ---------- TOTP primitives (public for testing) ---------- */

	private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	public static function base32_encode( string $bin ): string {
		$bits = '';
		foreach ( str_split( $bin ) as $c ) {
			$bits .= str_pad( decbin( ord( $c ) ), 8, '0', STR_PAD_LEFT );
		}
		$out = '';
		foreach ( str_split( $bits, 5 ) as $chunk ) {
			$out .= self::B32[ bindec( str_pad( $chunk, 5, '0' ) ) ];
		}
		return $out;
	}

	public static function base32_decode( string $b32 ): string {
		$bits = '';
		foreach ( str_split( strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $b32 ) ) ) as $c ) {
			$bits .= str_pad( decbin( strpos( self::B32, $c ) ), 5, '0', STR_PAD_LEFT );
		}
		$out = '';
		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$out .= chr( bindec( $byte ) );
			}
		}
		return $out;
	}

	public static function code( string $secret_b32, int $step, int $digits = 6 ): string {
		$hash   = hash_hmac( 'sha1', pack( 'N2', 0, $step ), self::base32_decode( $secret_b32 ), true );
		$offset = ord( $hash[19] ) & 0xf;
		$num    = ( unpack( 'N', substr( $hash, $offset, 4 ) )[1] & 0x7fffffff ) % ( 10 ** $digits );
		return str_pad( (string) $num, $digits, '0', STR_PAD_LEFT );
	}

	/** Accepts the current, previous and next 30-second window; returns the matched step or 0. */
	public static function match_step( string $secret_b32, string $code, ?int $now = null ): int {
		$code    = preg_replace( '/\D/', '', $code );
		$counter = intdiv( $now ?? time(), self::STEP );
		if ( 6 !== strlen( $code ) ) {
			return 0;
		}
		for ( $i = -1; $i <= 1; $i++ ) {
			if ( hash_equals( self::code( $secret_b32, $counter + $i ), $code ) ) {
				return $counter + $i;
			}
		}
		return 0;
	}

	/* ---------- Secret storage ---------- */

	private static function key(): string {
		return sodium_crypto_generichash( wp_salt( 'secure_auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public static function encrypt( string $plain ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding an encrypted blob for storage.
	}

	public static function decrypt( string $blob ): string {
		$raw = base64_decode( $blob, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding our own encrypted blob.
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}

	public static function is_enabled( int $user_id ): bool {
		return '' !== (string) get_user_meta( $user_id, self::META_SECRET, true );
	}

	private static function secret_for( int $user_id ): string {
		return self::decrypt( (string) get_user_meta( $user_id, self::META_SECRET, true ) );
	}

	private static function hash_backup( string $code ): string {
		return hash_hmac( 'sha256', strtolower( preg_replace( '/[^a-z0-9]/i', '', $code ) ), wp_salt( 'auth' ) );
	}

	/** @return string[] plain backup codes (shown once) */
	private static function issue_backup_codes( int $user_id ): array {
		$plain  = array();
		$hashes = array();
		for ( $i = 0; $i < 8; $i++ ) {
			$c        = strtolower( wp_generate_password( 10, false ) );
			$plain[]  = substr( $c, 0, 5 ) . '-' . substr( $c, 5, 5 );
			$hashes[] = self::hash_backup( $c );
		}
		update_user_meta( $user_id, self::META_BACKUP, $hashes );
		return $plain;
	}

	/* ---------- Login ---------- */

	public function login_field(): void {
		?>
		<p>
			<label for="bfd_otp"><?php esc_html_e( 'Authentication code (only if you use two-factor)', 'bfd-core' ); ?></label>
			<input type="text" name="bfd_otp" id="bfd_otp" class="input" value="" size="20" autocomplete="one-time-code" inputmode="numeric" spellcheck="false">
		</p>
		<?php
	}

	public function check_code( $user ) {
		global $pagenow;
		// Only the interactive login form: app passwords and REST keep working.
		if ( ! ( $user instanceof \WP_User ) || 'wp-login.php' !== $pagenow || ! self::is_enabled( $user->ID ) ) {
			return $user;
		}
		$submitted = isset( $_POST['bfd_otp'] ) ? sanitize_text_field( wp_unslash( $_POST['bfd_otp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the login form has no nonce; credentials were just verified.
		if ( '' === trim( $submitted ) ) {
			return new \WP_Error( 'bfd_2fa_required', __( '<strong>Error:</strong> Enter the authentication code from your app (or a backup code).', 'bfd-core' ) );
		}
		if ( $this->verify_for_user( $user->ID, $submitted ) ) {
			return $user;
		}
		return new \WP_Error( 'bfd_2fa_invalid', __( '<strong>Error:</strong> The authentication code is invalid or was already used.', 'bfd-core' ) );
	}

	/** TOTP (single use) or a one-time backup code. */
	private function verify_for_user( int $user_id, string $input ): bool {
		$step = self::match_step( self::secret_for( $user_id ), $input );
		if ( $step && $step > (int) get_user_meta( $user_id, self::META_LAST, true ) ) {
			update_user_meta( $user_id, self::META_LAST, $step );
			return true;
		}

		$hash    = self::hash_backup( $input );
		$backups = (array) get_user_meta( $user_id, self::META_BACKUP, true );
		foreach ( $backups as $i => $stored ) {
			if ( is_string( $stored ) && hash_equals( $stored, $hash ) ) {
				unset( $backups[ $i ] );
				update_user_meta( $user_id, self::META_BACKUP, array_values( $backups ) );
				return true;
			}
		}
		return false;
	}

	/* ---------- Profile UI ---------- */

	private function pending_key( int $user_id ): string {
		return 'bfd_2fa_pending_' . $user_id;
	}

	/** Users > Two-factor authentication (own account) for everyone; admins can open it for another user. */
	public function menu(): void {
		add_users_page( __( 'Two-factor authentication', 'bfd-core' ), __( 'Two-factor', 'bfd-core' ), 'read', 'bfd-2fa', array( $this, 'render_page' ) );
	}

	public static function page_url( int $user_id = 0 ): string {
		$base = current_user_can( 'list_users' ) ? 'users.php' : 'profile.php';
		$url  = admin_url( $base . '?page=bfd-2fa' );
		return $user_id && get_current_user_id() !== $user_id ? add_query_arg( 'user_id', $user_id, $url ) : $url;
	}

	/** Profile page: status + link only. The forms live on their own page (forms cannot be nested inside the profile form). */
	public function profile_section( \WP_User $user ): void {
		$is_self = get_current_user_id() === $user->ID;
		if ( ! $is_self && ! current_user_can( 'edit_users' ) ) {
			return;
		}
		?>
		<h2 id="bfd-2fa-status"><?php esc_html_e( 'Two-factor authentication', 'bfd-core' ); ?></h2>
		<table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Status', 'bfd-core' ); ?></th><td>
			<?php echo self::is_enabled( $user->ID ) ? '<span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> ' . esc_html__( 'Enabled', 'bfd-core' ) : esc_html__( 'Off', 'bfd-core' ); ?>
			&nbsp; <a class="button" href="<?php echo esc_url( self::page_url( $user->ID ) ); ?>"><?php echo $is_self ? esc_html__( 'Manage two-factor', 'bfd-core' ) : esc_html__( 'Manage / reset for this user', 'bfd-core' ); ?></a>
		</td></tr></table>
		<?php
	}

	public function render_page(): void {
		$uid     = get_current_user_id();
		$target  = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : $uid; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_self = $target === $uid;
		$user    = get_userdata( $target );
		if ( ! $user || ( ! $is_self && ! current_user_can( 'edit_users' ) ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bfd-core' ), '', array( 'response' => 403 ) );
		}
		$enabled = self::is_enabled( $user->ID );
		$pending = $is_self ? (string) get_transient( $this->pending_key( $user->ID ) ) : '';
		$codes   = $is_self ? get_transient( 'bfd_2fa_codes_' . $user->ID ) : false;
		$msg     = isset( $_GET['bfd_2fa'] ) ? sanitize_key( wp_unslash( $_GET['bfd_2fa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$left    = count( (array) get_user_meta( $user->ID, self::META_BACKUP, true ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Two-factor authentication', 'bfd-core' ); ?></h1>
			<div id="bfd-2fa">
			<?php if ( 'bad' === $msg ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'That code was not correct. Try again.', 'bfd-core' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $codes ) : ?>
				<div class="notice notice-success inline"><p><strong><?php esc_html_e( 'Two-factor is on. Save these backup codes now; each works once and they will not be shown again:', 'bfd-core' ); ?></strong></p>
				<p><code style="font-size:14px;line-height:2"><?php echo esc_html( implode( '   ', (array) $codes ) ); ?></code></p></div>
				<?php delete_transient( 'bfd_2fa_codes_' . $user->ID ); ?>
			<?php endif; ?>

			<?php if ( $enabled ) : ?>
				<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> <?php esc_html_e( 'Enabled.', 'bfd-core' ); ?>
					<?php echo esc_html( sprintf( /* translators: %d: number of codes */ _n( '%d backup code left.', '%d backup codes left.', $left, 'bfd-core' ), $left ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bfd_2fa_disable">
					<input type="hidden" name="user_id" value="<?php echo (int) $user->ID; ?>">
					<?php wp_nonce_field( 'bfd_2fa_disable_' . $user->ID ); ?>
					<?php if ( $is_self ) : ?>
						<input type="text" name="code" autocomplete="one-time-code" inputmode="numeric" placeholder="<?php esc_attr_e( 'Current code or backup code', 'bfd-core' ); ?>" required>
					<?php endif; ?>
					<button class="button"><?php echo $is_self ? esc_html__( 'Turn off two-factor', 'bfd-core' ) : esc_html__( 'Reset two-factor for this user (they lost their device)', 'bfd-core' ); ?></button>
				</form>
			<?php elseif ( $is_self && '' !== $pending ) : ?>
				<?php
				$secret = self::decrypt( $pending );
				$label  = rawurlencode( get_bloginfo( 'name' ) . ':' . $user->user_login );
				$uri    = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode( get_bloginfo( 'name' ) ) . '&digits=6&period=30';
				?>
				<p><?php esc_html_e( 'In your authenticator app choose "Enter a setup key" and use:', 'bfd-core' ); ?></p>
				<p><code class="bfd-2fa-key" style="font-size:16px;letter-spacing:2px"><?php echo esc_html( trim( chunk_split( $secret, 4, ' ' ) ) ); ?></code></p>
				<p class="description"><?php esc_html_e( 'Account: your username. Type: time-based. On a phone you can also tap:', 'bfd-core' ); ?> <a href="<?php echo esc_attr( $uri ); ?>"><?php esc_html_e( 'open in authenticator app', 'bfd-core' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bfd_2fa_enable">
					<?php wp_nonce_field( 'bfd_2fa_enable_' . $user->ID ); ?>
					<input type="text" name="code" autocomplete="one-time-code" inputmode="numeric" placeholder="<?php esc_attr_e( '6-digit code', 'bfd-core' ); ?>" required>
					<button class="button button-primary"><?php esc_html_e( 'Confirm and turn on', 'bfd-core' ); ?></button>
				</form>
			<?php elseif ( $is_self ) : ?>
				<p><?php esc_html_e( 'Off. Adds a 6-digit code from an app (Google Authenticator, Authy, 1Password…) to your login.', 'bfd-core' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bfd_2fa_start">
					<?php wp_nonce_field( 'bfd_2fa_start_' . $user->ID ); ?>
					<button class="button button-primary"><?php esc_html_e( 'Set up two-factor', 'bfd-core' ); ?></button>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Not enabled for this user.', 'bfd-core' ); ?></p>
			<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function back( string $msg = '', int $user_id = 0 ): void {
		$url = self::page_url( $user_id );
		if ( $msg ) {
			$url = add_query_arg( 'bfd_2fa', rawurlencode( $msg ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_start(): void {
		$uid = get_current_user_id();
		check_admin_referer( 'bfd_2fa_start_' . $uid );
		if ( ! $uid || self::is_enabled( $uid ) ) {
			$this->back();
		}
		set_transient( $this->pending_key( $uid ), self::encrypt( self::base32_encode( random_bytes( 20 ) ) ), 15 * MINUTE_IN_SECONDS );
		$this->back();
	}

	public function handle_enable(): void {
		$uid = get_current_user_id();
		check_admin_referer( 'bfd_2fa_enable_' . $uid );
		$pending = (string) get_transient( $this->pending_key( $uid ) );
		$code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$secret  = '' !== $pending ? self::decrypt( $pending ) : '';
		$step    = '' !== $secret ? self::match_step( $secret, $code ) : 0;
		if ( ! $step ) {
			$this->back( 'bad' );
		}
		update_user_meta( $uid, self::META_SECRET, $pending );
		update_user_meta( $uid, self::META_LAST, $step );
		delete_transient( $this->pending_key( $uid ) );
		set_transient( 'bfd_2fa_codes_' . $uid, self::issue_backup_codes( $uid ), 5 * MINUTE_IN_SECONDS );
		do_action( 'bfd_2fa_enabled', $uid );
		$this->back();
	}

	public function handle_disable(): void {
		$target = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
		check_admin_referer( 'bfd_2fa_disable_' . $target );
		$self = get_current_user_id() === $target;
		if ( ! $self && ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bfd-core' ), '', array( 'response' => 403 ) );
		}
		if ( $self ) {
			$code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
			if ( ! $this->verify_for_user( $target, $code ) ) {
				$this->back( 'bad', $target );
			}
		}
		delete_user_meta( $target, self::META_SECRET );
		delete_user_meta( $target, self::META_LAST );
		delete_user_meta( $target, self::META_BACKUP );
		do_action( 'bfd_2fa_disabled', $target, $self ? 'by user' : 'reset by admin' );
		$this->back( '', $target );
	}

	/** Gentle reminder for administrators who have not enabled it (dashboard + profile only). */
	public function admin_nag(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'profile' ), true ) || ! current_user_can( 'manage_options' ) || self::is_enabled( get_current_user_id() ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Protect your admin account with two-factor authentication.', 'bfd-core' ),
			esc_url( self::page_url() ),
			esc_html__( 'Set it up', 'bfd-core' )
		);
	}
}
