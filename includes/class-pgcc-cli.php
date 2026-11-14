<?php
/**
 * WP-CLI commands.
 *
 *   wp pulsegrid cache clear
 *   wp pulsegrid test
 *   wp pulsegrid status
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

class PGCC_CLI {

	/**
	 * Clear cached content and images.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pulsegrid cache clear
	 *
	 * @subcommand cache
	 * @param array $args Positional args.
	 */
	public function cache( $args ) {
		if ( ( $args[0] ?? '' ) !== 'clear' ) {
			WP_CLI::error( 'Usage: wp pulsegrid cache clear' );
		}
		$count = PGCC_Cache::clear();
		WP_CLI::success( sprintf( 'Cleared %d cached item(s).', $count ) );
	}

	/**
	 * Test the Mayo API credentials.
	 */
	public function test() {
		if ( '' === PGCC_Settings::api_key() ) {
			WP_CLI::error( 'No API key configured.' );
		}
		$client = new PGCC_API_Source( PGCC_Settings::base_url(), PGCC_Settings::api_key(), PGCC_Settings::trckr(), (string) PGCC_Settings::get( 'language' ) );
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
		$s = PGCC_Settings::all();
		WP_CLI::log( 'Mode:          ' . ( PGCC_Settings::is_live() ? 'live' : 'demo' ) . ( 'live' === $s['mode'] && ! PGCC_Settings::is_live() ? ' (live selected, no API key)' : '' ) );
		WP_CLI::log( 'API key:       ' . ( '' !== PGCC_Settings::api_key() ? 'set' . ( PGCC_Settings::api_key_from_constant() ? ' (wp-config.php)' : '' ) : 'not set' ) );
		WP_CLI::log( 'Base URL:      ' . PGCC_Settings::base_url() );
		WP_CLI::log( 'Cache TTL:     ' . ( PGCC_Settings::cache_ttl() / HOUR_IN_SECONDS ) . 'h' );
		WP_CLI::log( 'Library page:  ' . ( $s['library_page_id'] ? get_permalink( $s['library_page_id'] ) : 'not set' ) );
		WP_CLI::log( 'Sidebar:       ' . PGCC_Settings::normalize_sidebar( $s['sidebar'] ) );
		WP_CLI::log( 'Sidebar links: ' . count( PGCC_Care_Links::rows() ) . ' rows' . ( PGCC_Care_Links::has_saved_rows() ? '' : ' (examples)' ) );
		WP_CLI::log( 'Topic buttons: ' . count( PGCC_Topics::all() ) . ( PGCC_Topics::has_saved() ? '' : ' (starter list)' ) );
	}
}

WP_CLI::add_command( 'pulsegrid', 'PGCC_CLI' );
