<?php
namespace BFD\Core\Forms;

use BFD\Core\Security\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Storage + admin screen (list, delete, CSV export) for form submissions.
 */
final class Submissions {

	private const DB_VERSION = '1';
	private const PER_PAGE   = 20;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bfd_submissions';
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_install' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_bfd_delete_submission', array( $this, 'delete' ) );
		add_action( 'admin_post_bfd_export_submissions', array( $this, 'export' ) );
	}

	public function maybe_install(): void {
		if ( self::DB_VERSION === get_option( 'bfd_submissions_db' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			data longtext NOT NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at)
		) " . $wpdb->get_charset_collate() . ';'
		);
		update_option( 'bfd_submissions_db', self::DB_VERSION, false );
	}

	public static function insert( int $form_id, array $rows ): bool {
		global $wpdb;
		return (bool) $wpdb->insert(
			self::table(),
			array(
				'form_id'    => $form_id,
				'data'       => wp_json_encode( $rows ),
				'ip_hash'    => Client::ip_hash(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Form_Definition::POST_TYPE,
			__( 'Submissions', 'bfd-core' ),
			__( 'Submissions', 'bfd-core' ),
			'manage_options',
			'bfd-submissions',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$t       = self::table();
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where   = $form_id ? $wpdb->prepare( 'WHERE form_id = %d', $form_id ) : '';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$forms = Form_Definition::published();
		$base  = admin_url( 'edit.php?post_type=' . Form_Definition::POST_TYPE . '&page=bfd-submissions' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Form Submissions', 'bfd-core' ); ?></h1>

			<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'bfd-core' ); ?></p></div>
			<?php endif; ?>

			<form method="get" style="margin:12px 0;display:flex;gap:8px;align-items:center">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Form_Definition::POST_TYPE ); ?>">
				<input type="hidden" name="page" value="bfd-submissions">
				<select name="form_id">
					<option value="0"><?php esc_html_e( 'All forms', 'bfd-core' ); ?></option>
					<?php foreach ( $forms as $id => $title ) : ?>
						<option value="<?php echo (int) $id; ?>" <?php selected( $form_id, $id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Filter', 'bfd-core' ); ?></button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bfd_export_submissions&form_id=' . $form_id ), 'bfd_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'bfd-core' ); ?></a>
			</form>

			<table class="widefat striped">
				<thead><tr><th style="width:150px"><?php esc_html_e( 'Date', 'bfd-core' ); ?></th><th style="width:160px"><?php esc_html_e( 'Form', 'bfd-core' ); ?></th><th><?php esc_html_e( 'Data', 'bfd-core' ); ?></th><th style="width:80px"></th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No submissions yet.', 'bfd-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $data = json_decode( $row->data, true ) ?: array(); ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $forms[ $row->form_id ] ?? ( '#' . $row->form_id ) ); ?></td>
						<td>
							<?php foreach ( $data as $d ) : ?>
								<div><strong><?php echo esc_html( $d['label'] ?? '' ); ?>:</strong> <?php echo nl2br( esc_html( (string) ( $d['value'] ?? '' ) ) ); ?></div>
							<?php endforeach; ?>
						</td>
						<td><a class="submitdelete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bfd_delete_submission&id=' . (int) $row->id ), 'bfd_del_' . (int) $row->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission?', 'bfd-core' ) ); ?>')"><?php esc_html_e( 'Delete', 'bfd-core' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', $form_id ? add_query_arg( 'form_id', $form_id, $base ) : $base ),
						'format'  => '',
						'current' => $paged,
						'total'   => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
					)
				)
			) . '</div></div>';
			?>
		</div>
		<?php
	}

	public function delete(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'bfd_del_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bfd-core' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT data FROM ' . self::table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $row ) {
			$this->delete_files( json_decode( $row->data, true ) ?: array() );
			$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
			do_action( 'bfd_submission_deleted', $id );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Form_Definition::POST_TYPE . '&page=bfd-submissions&deleted=1' ) );
		exit;
	}

	/** Remove uploaded files referenced by a submission (only inside our uploads folder). */
	private function delete_files( array $rows ): void {
		$up = wp_upload_dir();
		foreach ( $rows as $r ) {
			if ( 'file' !== ( $r['type'] ?? '' ) || ! preg_match( '/\((https?:[^)]+)\)$/', (string) $r['value'], $m ) ) {
				continue;
			}
			$prefix = $up['baseurl'] . '/bfd-forms/';
			if ( 0 === strpos( $m[1], $prefix ) ) {
				$file = $up['basedir'] . '/bfd-forms/' . basename( $m[1] );
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	public function export(): void {
		check_admin_referer( 'bfd_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bfd-core' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$where   = $form_id ? $wpdb->prepare( 'WHERE form_id = %d', $form_id ) : '';
		$rows    = $wpdb->get_results( 'SELECT * FROM ' . self::table() . " {$where} ORDER BY id DESC LIMIT 10000" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$columns = array();
		$parsed  = array();
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( json_decode( $row->data, true ) ?: array() as $d ) {
				$label             = (string) ( $d['label'] ?? '' );
				$columns[ $label ] = true;
				$line[ $label ]    = (string) ( $d['value'] ?? '' );
			}
			$parsed[] = array( $row, $line );
		}

		do_action( 'bfd_submissions_exported' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="submissions-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- streaming a download to php://output, not the filesystem.
		fputcsv( $out, array_merge( array( 'Date', 'Form' ), array_map( array( $this, 'csv_cell' ), array_keys( $columns ) ) ) );
		foreach ( $parsed as [ $row, $line ] ) {
			$cells = array( $row->created_at, get_the_title( (int) $row->form_id ) );
			foreach ( array_keys( $columns ) as $label ) {
				$cells[] = $line[ $label ] ?? '';
			}
			fputcsv( $out, array_map( array( $this, 'csv_cell' ), $cells ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Neutralize spreadsheet formula injection. */
	private function csv_cell( $value ): string {
		$value = (string) $value;
		return ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) ? "'" . $value : $value;
	}
}
