<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package PulsegridContentConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'pgcc_settings' );
delete_option( 'pgcc_care_links' );
delete_option( 'pgcc_topics' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_pgcc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_pgcc_' ) . '%'
	)
);

$pgcc_uploads = wp_upload_dir( null, false );
$pgcc_dir     = trailingslashit( $pgcc_uploads['basedir'] ) . 'pgcc-cache';
if ( is_dir( $pgcc_dir ) ) {
	$pgcc_files = array_merge( (array) glob( $pgcc_dir . '/*' ), (array) glob( $pgcc_dir . '/.htaccess' ) );
	foreach ( $pgcc_files as $pgcc_file ) {
		if ( is_file( $pgcc_file ) ) {
			wp_delete_file( $pgcc_file );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	@rmdir( $pgcc_dir );
}
