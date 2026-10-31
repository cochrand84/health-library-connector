<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'hlmc_settings' );
delete_option( 'hlmc_care_links' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_hlmc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_hlmc_' ) . '%'
	)
);

$hlmc_uploads = wp_upload_dir( null, false );
$hlmc_dir     = trailingslashit( $hlmc_uploads['basedir'] ) . 'hlmc-cache';
if ( is_dir( $hlmc_dir ) ) {
	$hlmc_files = array_merge( (array) glob( $hlmc_dir . '/*' ), (array) glob( $hlmc_dir . '/.htaccess' ) );
	foreach ( $hlmc_files as $hlmc_file ) {
		if ( is_file( $hlmc_file ) ) {
			wp_delete_file( $hlmc_file );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	@rmdir( $hlmc_dir );
}
