<?php
namespace BFD\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Removes plugin data on deletion, but ONLY when "Delete ALL plugin data" is ticked in BFD Options.
 * Content of Services/Projects/Testimonials and normal page content are never touched.
 */
final class Uninstaller {

	/** @return array<string,int> what was (or, with $dry_run, would be) removed. */
	public static function run( bool $dry_run = false ): array {
		global $wpdb;
		$opts   = get_option( 'bfd_options', array() );
		$report = array( 'posts' => 0, 'meta_rows' => 0, 'table_rows' => 0, 'files' => 0 );

		if ( empty( $opts['delete_on_uninstall'] ) ) {
			return $report; // Default: keep everything.
		}

		$post_ids = get_posts( array( 'post_type' => array( 'bfd_section', 'bfd_form' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
		$report['posts'] = count( $post_ids );

		$report['meta_rows'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_bfd\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$table = $wpdb->prefix . 'bfd_submissions';
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$report['table_rows'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$dir = wp_upload_dir()['basedir'] . '/bfd-forms';
		if ( is_dir( $dir ) ) {
			$report['files'] = count( glob( $dir . '/*' ) ?: array() );
		}

		if ( $dry_run ) {
			return $report;
		}

		foreach ( $post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_bfd\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_bfd\\_%' OR option_name LIKE '\\_transient\\_timeout\\_bfd\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( array( 'bfd_options', 'bfd_submissions_db', 'bfd_core_rewrite_version' ) as $option ) {
			delete_option( $option );
		}
		if ( is_dir( $dir ) ) {
			foreach ( glob( $dir . '/{*,.htaccess}', GLOB_BRACE ) ?: array() as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		return $report;
	}
}
