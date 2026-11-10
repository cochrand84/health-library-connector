<?php
/**
 * Image proxy.
 *
 * Mayo image binaries need an authenticated request, so <img> tags can't
 * point at the API directly. Images are served from
 *   https://your-site/?hlmc-image=GUID-...
 * which fetches (or reads from cache) the binary server-side and streams it,
 * keeping the API key off the client.
 *
 * Carried over from the prototype's ?mayo-image= handler.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Image_Proxy {

	const QUERY_VAR = 'hlmc-image';

	/** Hook in early, before themes or other plugins send output. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 1 );
	}

	/** Serve the image and exit if this is an image request. */
	public static function maybe_serve() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only, validated GUID.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$guid = hlmc_sanitize_guid( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) );
		if ( '' === $guid ) {
			status_header( 400 );
			nocache_headers();
			exit;
		}

		$image = HLMC_Plugin::instance()->source()->get_image( $guid );

		if ( is_wp_error( $image ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$type = preg_match( '#^image/[a-z0-9.+-]+$#i', $image['type'] ) ? $image['type'] : 'application/octet-stream';

		status_header( 200 );
		header( 'Content-Type: ' . $type );
		header( 'Content-Length: ' . strlen( $image['body'] ) );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );
		if ( 'image/svg+xml' === $type ) {
			// SVG can carry script; never let it run in our origin.
			header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox" );
		}
		echo $image['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary image data.
		exit;
	}
}
