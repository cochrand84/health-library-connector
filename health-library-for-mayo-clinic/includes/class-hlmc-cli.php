<?php
/**
 * WP-CLI commands.
 *
 *   wp health-library cache clear
 *   wp health-library test
 *   wp health-library status
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_CLI {

	/**
	 * Clear cached content and images.
	 *
	 * ## EXAMPLES
	 *
	 *     wp health-library cache clear
	 *
	 * @subcommand cache
	 * @param array $args Positional args.
	 */
	public function cache( $args ) {
		if ( ( $args[0] ?? '' ) !== 'clear' ) {
			WP_CLI::error( 'Usage: wp health-library cache clear' );
		}
		$count = HLMC_Cache::clear();
		WP_CLI::success( sprintf( 'Cleared %d cached item(s).', $count ) );
	}

	/**
	 * Test the Mayo API credentials.
	 */
	public function test() {
		if ( '' === HLMC_Settings::api_key() ) {
			WP_CLI::error( 'No API key configured.' );
		}
		$client = new HLMC_API_Source( HLMC_Settings::base_url(), HLMC_Settings::api_key(), HLMC_Settings::trckr(), (string) HLMC_Settings::get( 'language' ) );
		$result = $client->test();
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( 'Connected to the Mayo Clinic API.' );
	}

	/**
	 * Show the active configuration (without secrets).
	 */
	public function status() {
		$s = HLMC_Settings::all();
		WP_CLI::log( 'Mode:          ' . ( HLMC_Settings::is_live() ? 'live' : 'demo' ) . ( 'live' === $s['mode'] && ! HLMC_Settings::is_live() ? ' (live selected, no API key)' : '' ) );
		WP_CLI::log( 'API key:       ' . ( '' !== HLMC_Settings::api_key() ? 'set' . ( HLMC_Settings::api_key_from_constant() ? ' (wp-config.php)' : '' ) : 'not set' ) );
		WP_CLI::log( 'Base URL:      ' . HLMC_Settings::base_url() );
		WP_CLI::log( 'Cache TTL:     ' . ( HLMC_Settings::cache_ttl() / HOUR_IN_SECONDS ) . 'h' );
		WP_CLI::log( 'Library page:  ' . ( $s['library_page_id'] ? get_permalink( $s['library_page_id'] ) : 'not set' ) );
		WP_CLI::log( 'Sidebar:       ' . HLMC_Settings::normalize_sidebar( $s['sidebar'] ) );
		WP_CLI::log( 'Sidebar links: ' . count( HLMC_Care_Links::rows() ) . ' rows' . ( HLMC_Care_Links::has_saved_rows() ? '' : ' (examples)' ) );
		WP_CLI::log( 'Topic buttons: ' . count( HLMC_Topics::all() ) . ( HLMC_Topics::has_saved() ? '' : ' (starter list)' ) );
	}
}

WP_CLI::add_command( 'health-library', 'HLMC_CLI' );
