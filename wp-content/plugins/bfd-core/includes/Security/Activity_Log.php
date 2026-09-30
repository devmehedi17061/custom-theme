<?php
namespace BFD\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Security-relevant activity log (logins, lockouts, users, plugins, settings, publishing).
 * IPs are anonymized (last octet dropped). Viewable under Tools > Activity Log; old rows are purged daily.
 */
final class Activity_Log {

	private const DB_VERSION = '1';
	private const PER_PAGE   = 30;
	private const CRON_HOOK  = 'bfd_activity_purge';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bfd_activity';
	}

	/** @return array<string,string> event => label */
	public static function events(): array {
		return array(
			'login'              => __( 'Login', 'bfd-core' ),
			'login_failed'       => __( 'Failed login', 'bfd-core' ),
			'login_locked'       => __( 'Login lockout', 'bfd-core' ),
			'password_reset'     => __( 'Password reset', 'bfd-core' ),
			'user_created'       => __( 'User created', 'bfd-core' ),
			'user_deleted'       => __( 'User deleted', 'bfd-core' ),
			'role_changed'       => __( 'Role changed', 'bfd-core' ),
			'plugin_activated'   => __( 'Plugin activated', 'bfd-core' ),
			'plugin_deactivated' => __( 'Plugin deactivated', 'bfd-core' ),
			'theme_switched'     => __( 'Theme switched', 'bfd-core' ),
			'content_published'  => __( 'Content published', 'bfd-core' ),
			'content_trashed'    => __( 'Content trashed', 'bfd-core' ),
			'settings_changed'   => __( 'Settings changed', 'bfd-core' ),
			'2fa_enabled'        => __( '2FA enabled', 'bfd-core' ),
			'2fa_disabled'       => __( '2FA disabled', 'bfd-core' ),
			'submission_deleted' => __( 'Submission deleted', 'bfd-core' ),
			'submissions_export' => __( 'Submissions exported', 'bfd-core' ),
		);
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_install' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( self::CRON_HOOK, array( $this, 'purge' ) );
		add_action( 'init', array( $this, 'schedule' ) );

		add_action( 'wp_login', array( $this, 'on_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 1 );
		add_action( 'bfd_login_locked', array( $this, 'on_locked' ) );
		add_action( 'after_password_reset', array( $this, 'on_password_reset' ) );
		add_action( 'user_register', array( $this, 'on_user_register' ) );
		add_action( 'delete_user', array( $this, 'on_user_delete' ) );
		add_action( 'set_user_role', array( $this, 'on_role_change' ), 10, 3 );
		add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_deactivated' ) );
		add_action( 'switch_theme', array( $this, 'on_theme_switch' ) );
		add_action( 'transition_post_status', array( $this, 'on_post_status' ), 10, 3 );
		add_action( 'update_option_bfd_options', array( $this, 'on_settings' ) );
		add_action( 'bfd_2fa_enabled', array( $this, 'on_2fa_enabled' ) );
		add_action( 'bfd_2fa_disabled', array( $this, 'on_2fa_disabled' ), 10, 2 );
		add_action( 'bfd_submission_deleted', array( $this, 'on_submission_deleted' ) );
		add_action( 'bfd_submissions_exported', array( $this, 'on_export' ) );
	}

	public function maybe_install(): void {
		if ( self::DB_VERSION === get_option( 'bfd_activity_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_login varchar(60) NOT NULL DEFAULT '',
			event varchar(40) NOT NULL,
			message text NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event (event),
			KEY created_at (created_at)
		) " . $wpdb->get_charset_collate() . ';'
		);
		update_option( 'bfd_activity_db', self::DB_VERSION, false );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function purge(): void {
		global $wpdb;
		$days = max( 7, (int) apply_filters( 'bfd_activity_retention_days', (int) bfd_option( 'log_retention_days', 90 ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/* ---------- Writing ---------- */

	public static function log( string $event, string $message, ?int $user_id = null, string $user_login = '' ): void {
		global $wpdb;
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( '' === $user_login && $user_id ) {
			$user       = get_userdata( $user_id );
			$user_login = $user ? $user->user_login : '';
		}
		$wpdb->insert(
			self::table(),
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'user_id'    => $user_id,
				'user_login' => mb_substr( $user_login, 0, 60 ),
				'event'      => substr( $event, 0, 40 ),
				'message'    => mb_substr( $message, 0, 500 ),
				'ip'         => (string) wp_privacy_anonymize_ip( Client::ip() ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	public function on_login( $user_login, $user ): void {
		self::log( 'login', __( 'Logged in', 'bfd-core' ), (int) $user->ID, (string) $user_login );
	}

	public function on_login_failed( $username ): void {
		/* translators: %s: username that was tried */
		self::log( 'login_failed', sprintf( __( 'Failed login for "%s"', 'bfd-core' ), sanitize_user( (string) $username ) ), 0, sanitize_user( (string) $username ) );
	}

	public function on_locked(): void {
		self::log( 'login_locked', __( 'Too many failed logins: this IP is locked out', 'bfd-core' ), 0 );
	}

	public function on_password_reset( $user ): void {
		self::log( 'password_reset', __( 'Password was reset', 'bfd-core' ), (int) $user->ID, (string) $user->user_login );
	}

	public function on_user_register( $user_id ): void {
		$user = get_userdata( (int) $user_id );
		/* translators: %s: username */
		self::log( 'user_created', sprintf( __( 'User "%s" created', 'bfd-core' ), $user ? $user->user_login : (string) $user_id ) );
	}

	public function on_user_delete( $user_id ): void {
		$user = get_userdata( (int) $user_id );
		/* translators: %s: username */
		self::log( 'user_deleted', sprintf( __( 'User "%s" deleted', 'bfd-core' ), $user ? $user->user_login : (string) $user_id ) );
	}

	public function on_role_change( $user_id, $role, $old_roles ): void {
		if ( ! $old_roles ) {
			return; // New user: covered by user_created.
		}
		$user = get_userdata( (int) $user_id );
		/* translators: 1: username, 2: old role(s), 3: new role */
		self::log( 'role_changed', sprintf( __( '"%1$s": %2$s to %3$s', 'bfd-core' ), $user ? $user->user_login : (string) $user_id, implode( ',', (array) $old_roles ), (string) $role ) );
	}

	public function on_plugin_activated( $plugin ): void {
		/* translators: %s: plugin file */
		self::log( 'plugin_activated', sprintf( __( 'Activated %s', 'bfd-core' ), (string) $plugin ) );
	}

	public function on_plugin_deactivated( $plugin ): void {
		/* translators: %s: plugin file */
		self::log( 'plugin_deactivated', sprintf( __( 'Deactivated %s', 'bfd-core' ), (string) $plugin ) );
	}

	public function on_theme_switch( $new_name ): void {
		/* translators: %s: theme name */
		self::log( 'theme_switched', sprintf( __( 'Switched to theme %s', 'bfd-core' ), (string) $new_name ) );
	}

	public function on_post_status( $new_status, $old_status, $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}
		$type_object = get_post_type_object( $post->post_type );
		$is_public   = (bool) ( $type_object->public ?? false );
		if ( ! $is_public && 0 !== strpos( $post->post_type, 'bfd_' ) ) {
			return;
		}
		$title = wp_strip_all_tags( get_the_title( $post ) );
		if ( 'publish' === $new_status ) {
			/* translators: 1: post type, 2: title */
			self::log( 'content_published', sprintf( __( '%1$s published: %2$s', 'bfd-core' ), $post->post_type, $title ) );
		} elseif ( 'trash' === $new_status ) {
			/* translators: 1: post type, 2: title */
			self::log( 'content_trashed', sprintf( __( '%1$s trashed: %2$s', 'bfd-core' ), $post->post_type, $title ) );
		}
	}

	/** Never logs values, only that BFD Options changed (they may contain keys). */
	public function on_settings(): void {
		self::log( 'settings_changed', __( 'BFD Options were saved', 'bfd-core' ) );
	}

	public function on_2fa_enabled( $user_id ): void {
		self::log( '2fa_enabled', __( 'Two-factor authentication enabled', 'bfd-core' ), (int) $user_id );
	}

	public function on_2fa_disabled( $user_id, $by ): void {
		/* translators: %s: how it was disabled */
		self::log( '2fa_disabled', sprintf( __( 'Two-factor authentication disabled (%s)', 'bfd-core' ), (string) $by ), (int) $user_id );
	}

	public function on_submission_deleted( $id ): void {
		/* translators: %d: submission id */
		self::log( 'submission_deleted', sprintf( __( 'Form submission #%d deleted', 'bfd-core' ), (int) $id ) );
	}

	public function on_export(): void {
		self::log( 'submissions_export', __( 'Form submissions exported to CSV', 'bfd-core' ) );
	}

	/* ---------- Admin screen ---------- */

	public function menu(): void {
		add_management_page( __( 'Activity Log', 'bfd-core' ), __( 'Activity Log', 'bfd-core' ), 'manage_options', 'bfd-activity', array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$t      = self::table();
		$events = self::events();
		$event  = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event  = isset( $events[ $event ] ) ? $event : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where  = $event ? $wpdb->prepare( 'WHERE event = %s', $event ) : '';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Activity Log', 'bfd-core' ); ?></h1>
			<form method="get" style="margin:12px 0;display:flex;gap:8px">
				<input type="hidden" name="page" value="bfd-activity">
				<select name="event">
					<option value=""><?php esc_html_e( 'All events', 'bfd-core' ); ?></option>
					<?php foreach ( $events as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $event, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Filter', 'bfd-core' ); ?></button>
			</form>
			<table class="widefat striped">
				<thead><tr>
					<th style="width:160px"><?php esc_html_e( 'Time (UTC)', 'bfd-core' ); ?></th>
					<th style="width:140px"><?php esc_html_e( 'User', 'bfd-core' ); ?></th>
					<th style="width:170px"><?php esc_html_e( 'Event', 'bfd-core' ); ?></th>
					<th><?php esc_html_e( 'Details', 'bfd-core' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'IP', 'bfd-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Nothing logged yet.', 'bfd-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $row->user_login ?: '-' ); ?></td>
						<td><?php echo esc_html( $events[ $row->event ] ?? $row->event ); ?></td>
						<td><?php echo esc_html( $row->message ); ?></td>
						<td><?php echo esc_html( $row->ip ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
					)
				)
			) . '</div></div>';
			?>
			<p class="description"><?php esc_html_e( 'IP addresses are anonymized. Entries older than the retention period (Settings > BFD Options > Advanced) are removed daily.', 'bfd-core' ); ?></p>
		</div>
		<?php
	}
}
