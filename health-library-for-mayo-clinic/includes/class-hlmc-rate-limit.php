<?php
/**
 * Limits how many *uncached* Mayo API calls visitors can trigger.
 *
 * Cached pages are free; every new search term, page number or article ID
 * costs a live API call against the site's quota. Without a limit, anyone
 * could script thousands of random searches. Two fixed one-minute windows:
 *
 * - per visitor (by IP): default 20 uncached calls / minute
 * - whole site:          default 120 uncached calls / minute
 *
 * Behind a reverse proxy every visitor may share one IP; the per-visitor
 * limit then acts as a second site-wide cap. Use the `hlmc_client_ip`
 * filter to read a trusted header (e.g. CF-Connecting-IP) instead.
 *
 * Administrators and WP-CLI are never limited.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Rate_Limit {

	const PREFIX = 'hlmc_rl_';

	/**
	 * Record one uncached API call; false if a limit is exceeded.
	 *
	 * @return bool
	 */
	public static function allow() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		/**
		 * Filter the rate limits. Set a value to 0 to disable that limit.
		 *
		 * @param array $limits { per_ip: int, site: int } calls per minute.
		 */
		$limits = apply_filters(
			'hlmc_rate_limits',
			array(
				'per_ip' => 20,
				'site'   => 120,
			)
		);

		$window = (int) floor( time() / MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only hashed.
		$ip = (string) apply_filters( 'hlmc_client_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );

		$checks = array(
			'site' => self::PREFIX . 's_' . $window,
			'per_ip' => self::PREFIX . 'i_' . $window . '_' . md5( $ip ),
		);

		foreach ( $checks as $name => $key ) {
			$max = (int) ( $limits[ $name ] ?? 0 );
			if ( $max <= 0 ) {
				continue;
			}
			$count = (int) get_transient( $key );
			if ( $count >= $max ) {
				/**
				 * Fires when a visitor hits the API rate limit.
				 *
				 * @param string $name Which limit ('site' or 'per_ip').
				 */
				do_action( 'hlmc_rate_limited', $name );
				return false;
			}
		}
		foreach ( $checks as $name => $key ) {
			if ( (int) ( $limits[ $name ] ?? 0 ) > 0 ) {
				set_transient( $key, (int) get_transient( $key ) + 1, 2 * MINUTE_IN_SECONDS );
			}
		}
		return true;
	}
}
