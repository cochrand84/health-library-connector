<?php
/**
 * Caching: transients for JSON responses, a protected uploads folder for
 * image binaries (too large to sensibly keep in wp_options).
 *
 * Everything is capped at 48 hours to satisfy Mayo's syndication terms.
 *
 * Image filenames are keyed with a site secret, so even on servers that
 * ignore .htaccess (nginx) the cached files can't be fetched by guessing.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Cache {

	const PREFIX = 'hlmc_';

	/**
	 * Transient key for a request. Includes the content source so demo and
	 * live data never mix.
	 *
	 * @param string $source 'live' or 'demo'.
	 * @param string $url    Request URL or identifier.
	 * @return string
	 */
	public static function key( $source, $url ) {
		return self::PREFIX . $source . '_' . md5( $url );
	}

	/**
	 * @param string $key Transient key.
	 * @return mixed|null Null on miss.
	 */
	public static function get( $key ) {
		$value = get_transient( $key );
		return false === $value ? null : $value;
	}

	/**
	 * @param string $key   Transient key.
	 * @param mixed  $value Value.
	 */
	public static function set( $key, $value ) {
		set_transient( $key, $value, HLMC_Settings::cache_ttl() );
	}

	/**
	 * Remove all cached content and images.
	 *
	 * @return int Number of transients removed.
	 */
	public static function clear() {
		global $wpdb;

		$like_value   = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_value,
				$like_timeout
			)
		);

		// Persistent object caches don't use the options table.
		if ( wp_using_ext_object_cache() && function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'transient' );
		}

		self::clear_images();

		/**
		 * Fires after the Health Library cache has been cleared.
		 */
		do_action( 'hlmc_cache_cleared' );

		return (int) floor( $count / 2 );
	}

	/**
	 * Folder for cached image binaries, created on demand and protected
	 * from direct listing / access.
	 *
	 * @return string|false Absolute path with trailing slash, or false.
	 */
	public static function image_dir() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'hlmc-cache/';
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
			file_put_contents( $dir . '.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" );
			// phpcs:enable
		}
		return $dir;
	}

	/**
	 * @param string $source Source name.
	 * @param string $guid   Image GUID.
	 * @return array|null { type, body } or null on miss/expired.
	 */
	public static function get_image( $source, $guid ) {
		$dir = self::image_dir();
		if ( ! $dir ) {
			return null;
		}
		$file = $dir . self::image_name( $source, $guid );
		if ( ! is_readable( $file ) || ! is_readable( $file . '.type' ) ) {
			return null;
		}
		if ( ( time() - filemtime( $file ) ) >= HLMC_Settings::cache_ttl() ) {
			wp_delete_file( $file );
			wp_delete_file( $file . '.type' );
			return null;
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return array(
			'type' => trim( (string) file_get_contents( $file . '.type' ) ),
			'body' => (string) file_get_contents( $file ),
		);
		// phpcs:enable
	}

	/**
	 * @param string $source Source name.
	 * @param string $guid   Image GUID.
	 * @param array  $image  { type, body }.
	 */
	public static function set_image( $source, $guid, array $image ) {
		$dir = self::image_dir();
		if ( ! $dir ) {
			return;
		}
		$file = $dir . self::image_name( $source, $guid );
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $image['body'] );
		file_put_contents( $file . '.type', $image['type'] );
		// phpcs:enable
	}

	/**
	 * Unguessable cache filename for an image.
	 *
	 * @param string $source Source name.
	 * @param string $guid   Image GUID.
	 * @return string
	 */
	private static function image_name( $source, $guid ) {
		return hash_hmac( 'sha256', $source . '|' . $guid, wp_salt( 'auth' ) ) . '.bin';
	}

	/** Delete every cached image file. */
	public static function clear_images() {
		$dir = self::image_dir();
		if ( ! $dir ) {
			return;
		}
		foreach ( (array) glob( $dir . '*' ) as $file ) {
			$name = basename( $file );
			if ( 'index.php' !== $name && is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
}
