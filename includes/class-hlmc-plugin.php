<?php
/**
 * Plugin wiring.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

final class HLMC_Plugin {

	/** @var HLMC_Plugin|null */
	private static $instance = null;

	/** @var HLMC_Source|null */
	private $source = null;

	/** @var HLMC_Request|null */
	private $request = null;

	/** @return HLMC_Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() {
		HLMC_Image_Proxy::init();
		new HLMC_Frontend();

		if ( is_admin() ) {
			new HLMC_Admin();
		}
	}

	/**
	 * The active content source: the Mayo API when live mode is on and a key
	 * is configured, otherwise the bundled demo data.
	 *
	 * @return HLMC_Source
	 */
	public function source() {
		if ( null === $this->source ) {
			if ( HLMC_Settings::is_live() ) {
				$source = new HLMC_API_Source(
					HLMC_Settings::base_url(),
					HLMC_Settings::api_key(),
					HLMC_Settings::trckr(),
					(string) HLMC_Settings::get( 'language' )
				);
			} else {
				$source = new HLMC_Demo_Source();
			}
			/**
			 * Replace or decorate the content source.
			 *
			 * @param HLMC_Source $source Source instance.
			 */
			$filtered     = apply_filters( 'hlmc_source', $source );
			$this->source = $filtered instanceof HLMC_Source ? $filtered : $source;
		}
		return $this->source;
	}

	/** @return HLMC_Request */
	public function request() {
		if ( null === $this->request ) {
			$this->request = new HLMC_Request( $this->source() );
		}
		return $this->request;
	}

	/** Reset cached instances (after settings change, and in tests). */
	public function reset() {
		$this->source  = null;
		$this->request = null;
		HLMC_Settings::flush();
	}

	/** Activation: seed settings so the demo works out of the box. */
	public static function activate() {
		if ( false === get_option( HLMC_Settings::OPTION ) ) {
			add_option( HLMC_Settings::OPTION, HLMC_Settings::defaults(), '', false );
		}
	}
}
