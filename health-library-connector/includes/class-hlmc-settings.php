<?php
/**
 * Settings storage and defaults.
 *
 * Credentials can be set in the admin screen or, preferably, as constants in
 * wp-config.php so they never touch the database:
 *
 *   define( 'HLMC_API_KEY', '...' );
 *   define( 'HLMC_TRCKR',   '...' );
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Settings {

	const OPTION = 'hlmc_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'            => 'demo',  // 'demo' | 'live'.
			'api_key'         => '',
			'trckr'           => '',
			'base_url'        => 'https://cdapi.mayoclinic.org/api/v2/',
			'language'        => 'en',
			'cache_hours'     => 48,
			'per_page'        => 10,
			'featured_count'  => 12,
			'library_page_id' => 0,
			'accent_color'    => '#003865',
			'sidebar'         => 'none',  // 'none' | 'cta' | 'links'.
			'cta_title'       => __( 'Find a Doctor', 'health-library-connector' ),
			'cta_text'        => __( 'Connect with a specialist who can help with your health concerns.', 'health-library-connector' ),
			'cta_button'      => __( 'Schedule an Appointment', 'health-library-connector' ),
			'cta_url'         => '',
			'links_provider_heading' => __( 'Find a Provider', 'health-library-connector' ),
			'links_services_heading' => __( 'Services We Offer', 'health-library-connector' ),
			'show_inspector'  => 1,
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/** Drop the in-memory copy (after saving). */
	public static function flush() {
		self::$cache = null;
	}

	/** @return string */
	public static function api_key() {
		return defined( 'HLMC_API_KEY' ) ? (string) HLMC_API_KEY : (string) self::get( 'api_key' );
	}

	/** @return string */
	public static function trckr() {
		return defined( 'HLMC_TRCKR' ) ? (string) HLMC_TRCKR : (string) self::get( 'trckr' );
	}

	/** @return bool */
	public static function api_key_from_constant() {
		return defined( 'HLMC_API_KEY' );
	}

	/** @return bool */
	public static function trckr_from_constant() {
		return defined( 'HLMC_TRCKR' );
	}

	/**
	 * Live mode needs a key; without one we quietly fall back to demo data.
	 *
	 * @return bool
	 */
	public static function is_live() {
		return 'live' === self::get( 'mode' ) && '' !== self::api_key();
	}

	/**
	 * Cache lifetime in seconds, never above the 48 hour license maximum.
	 *
	 * @return int
	 */
	public static function cache_ttl() {
		$hours = (int) self::get( 'cache_hours' );
		$ttl   = max( 1, $hours ) * HOUR_IN_SECONDS;
		return (int) min( $ttl, HLMC_MAX_CACHE_TTL );
	}

	/**
	 * Base URL with exactly one trailing slash.
	 *
	 * @return string
	 */
	public static function base_url() {
		return trailingslashit( (string) self::get( 'base_url' ) );
	}

	/**
	 * Sidebar mode. 'providers' is accepted as an older name for 'links'.
	 *
	 * @param string $value Raw value.
	 * @return string 'none' | 'cta' | 'links'
	 */
	public static function normalize_sidebar( $value ) {
		$value = 'providers' === $value ? 'links' : (string) $value;
		return in_array( $value, array( 'none', 'cta', 'links' ), true ) ? $value : 'none';
	}

	/**
	 * Sanitize the settings array on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::all();
		$defaults = self::defaults();
		$out      = $current;

		$out['mode'] = ( isset( $input['mode'] ) && 'live' === $input['mode'] ) ? 'live' : 'demo';

		// Secrets: a blank submission keeps the stored value (the field is
		// never echoed back), the "clear" checkbox removes it.
		foreach ( array( 'api_key', 'trckr' ) as $secret ) {
			if ( ! empty( $input[ $secret . '_clear' ] ) ) {
				$out[ $secret ] = '';
			} elseif ( isset( $input[ $secret ] ) && '' !== trim( $input[ $secret ] ) ) {
				$out[ $secret ] = sanitize_text_field( wp_unslash( $input[ $secret ] ) );
			}
		}

		$base = isset( $input['base_url'] ) ? esc_url_raw( trim( $input['base_url'] ), array( 'https' ) ) : '';
		$out['base_url'] = $base ? $base : $defaults['base_url'];

		$lang            = isset( $input['language'] ) ? strtolower( sanitize_key( $input['language'] ) ) : 'en';
		$out['language'] = preg_match( '/^[a-z]{2}(-[a-z]{2})?$/', $lang ) ? $lang : 'en';

		$out['cache_hours']     = isset( $input['cache_hours'] ) ? min( 48, max( 1, absint( $input['cache_hours'] ) ) ) : 48;
		$out['per_page']        = isset( $input['per_page'] ) ? min( 50, max( 1, absint( $input['per_page'] ) ) ) : 10;
		$out['featured_count']  = isset( $input['featured_count'] ) ? min( 50, absint( $input['featured_count'] ) ) : 12;
		$out['library_page_id'] = isset( $input['library_page_id'] ) ? absint( $input['library_page_id'] ) : 0;

		$color               = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$out['accent_color'] = $color ? $color : $defaults['accent_color'];

		$out['sidebar'] = self::normalize_sidebar( isset( $input['sidebar'] ) ? $input['sidebar'] : 'none' );

		foreach ( array( 'cta_title', 'cta_button', 'links_provider_heading', 'links_services_heading' ) as $k ) {
			$out[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( wp_unslash( $input[ $k ] ) ) : $defaults[ $k ];
		}
		$out['cta_text'] = isset( $input['cta_text'] ) ? sanitize_textarea_field( wp_unslash( $input['cta_text'] ) ) : $defaults['cta_text'];
		$out['cta_url']  = isset( $input['cta_url'] ) ? esc_url_raw( trim( $input['cta_url'] ) ) : '';

		$out['show_inspector'] = empty( $input['show_inspector'] ) ? 0 : 1;

		// Changing the content source or credentials invalidates cached data.
		if ( $out['mode'] !== $current['mode'] || $out['api_key'] !== $current['api_key'] || $out['base_url'] !== $current['base_url'] || $out['language'] !== $current['language'] ) {
			HLMC_Cache::clear();
		}

		self::flush();
		return $out;
	}
}
