<?php
/**
 * Plugin wiring.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

final class PGCC_Plugin {

	/** @var PGCC_Plugin|null */
	private static $instance = null;

	/** @var PGCC_Source|null */
	private $source = null;

	/** @var PGCC_Request|null */
	private $request = null;

	/** @return PGCC_Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function boot() {
		PGCC_Image_Proxy::init();
		new PGCC_Frontend();

		if ( is_admin() ) {
			new PGCC_Admin();
		}
	}

	/**
	 * The active content source: the Mayo API when live mode is on and a key
	 * is configured, otherwise the bundled demo data.
	 *
	 * @return PGCC_Source
	 */
	public function source() {
		if ( null === $this->source ) {
			if ( PGCC_Settings::is_live() ) {
				$source = new PGCC_API_Source(
					PGCC_Settings::base_url(),
					PGCC_Settings::api_key(),
					PGCC_Settings::trckr(),
					(string) PGCC_Settings::get( 'language' )
				);
			} else {
				$source = new PGCC_Demo_Source();
			}
			/**
			 * Replace or decorate the content source.
			 *
			 * @param PGCC_Source $source Source instance.
			 */
			$filtered     = apply_filters( 'pgcc_source', $source );
			$this->source = $filtered instanceof PGCC_Source ? $filtered : $source;
		}
		return $this->source;
	}

	/** @return PGCC_Request */
	public function request() {
		if ( null === $this->request ) {
			$this->request = new PGCC_Request( $this->source() );
		}
		return $this->request;
	}

	/** Reset cached instances (after settings change, and in tests). */
	public function reset() {
		$this->source  = null;
		$this->request = null;
		PGCC_Settings::flush();
	}

	/** Activation: seed settings so the demo works out of the box. */
	public static function activate() {
		if ( false === get_option( PGCC_Settings::OPTION ) ) {
			add_option( PGCC_Settings::OPTION, PGCC_Settings::defaults(), '', false );
		}
	}
}
