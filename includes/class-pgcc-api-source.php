<?php
/**
 * Live client for the Mayo Clinic GBS Realtime (Content Delivery) API.
 *
 * Endpoints:
 *   GET {base}content?q=...&top=...&skip=...&lang=en   search
 *   GET {base}content/{id}/{lang}                      article / image
 *
 * Every request carries the `apikey` header and, if configured, the `trckr`
 * header Mayo uses for usage tracking.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

class PGCC_API_Source implements PGCC_Source {

	/** @var string */
	private $base_url;

	/** @var string */
	private $api_key;

	/** @var string */
	private $trckr;

	/** @var string */
	private $lang;

	/**
	 * @param string $base_url Base URL ending in a slash.
	 * @param string $api_key  API key.
	 * @param string $trckr    Tracking value.
	 * @param string $lang     Language code.
	 */
	public function __construct( $base_url, $api_key, $trckr = '', $lang = 'en' ) {
		$this->base_url = trailingslashit( $base_url );
		$this->api_key  = $api_key;
		$this->trckr    = $trckr;
		$this->lang     = $lang;
	}

	public function is_live() {
		return true;
	}

	public function search( $query, $top = 10, $skip = 0 ) {
		$url = add_query_arg(
			array(
				'q'    => rawurlencode( $query ),
				'top'  => (int) $top,
				'skip' => (int) $skip,
				'lang' => $this->lang,
			),
			$this->base_url . 'content'
		);
		return $this->get_json( $url );
	}

	public function get_content( $content_id ) {
		$content_id = pgcc_sanitize_content_id( $content_id );
		if ( '' === $content_id ) {
			return new WP_Error( 'pgcc_invalid_id', __( 'Invalid content ID.', 'pulsegrid-content-connector' ) );
		}
		return $this->get_json( $this->base_url . 'content/' . rawurlencode( $content_id ) . '/' . $this->lang );
	}

	public function get_image( $guid ) {
		$guid = pgcc_sanitize_guid( $guid );
		if ( '' === $guid ) {
			return new WP_Error( 'pgcc_invalid_id', __( 'Invalid image ID.', 'pulsegrid-content-connector' ) );
		}

		$cached = PGCC_Cache::get_image( 'live', $guid );
		if ( null !== $cached ) {
			return $cached;
		}

		if ( ! PGCC_Rate_Limit::allow( 'image' ) ) {
			return new WP_Error( 'pgcc_rate_limited', __( 'Too many requests. Please try again in a minute.', 'pulsegrid-content-connector' ), array( 'status' => 429 ) );
		}

		// No JSON Accept header here: Mayo returns the raw binary for images.
		$response = wp_remote_get(
			$this->base_url . 'content/' . rawurlencode( $guid ) . '/' . $this->lang,
			array(
				'timeout' => 15,
				'headers' => $this->headers( false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code >= 400 || '' === $body || 0 !== strpos( strtolower( $type ), 'image/' ) ) {
			return new WP_Error( 'pgcc_image_unavailable', sprintf( 'Mayo API returned HTTP %d for image.', $code ) );
		}

		$image = array(
			'type' => $type,
			'body' => $body,
		);
		PGCC_Cache::set_image( 'live', $guid, $image );
		return $image;
	}

	/**
	 * Quick connectivity check used by the admin "Test connection" button
	 * and `wp pulsegrid test`.
	 *
	 * @return true|WP_Error
	 */
	public function test() {
		$url      = add_query_arg(
			array(
				'q'    => 'health',
				'top'  => 1,
				'lang' => $this->lang,
			),
			$this->base_url . 'content'
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => $this->headers(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'pgcc_http_' . $code, sprintf( 'Mayo API returned HTTP %d.', $code ) );
		}
		return true;
	}

	/**
	 * GET + decode JSON, with caching.
	 *
	 * @param string $url Full URL.
	 * @return array|WP_Error
	 */
	private function get_json( $url ) {
		$key    = PGCC_Cache::key( 'live', $url );
		$cached = PGCC_Cache::get( $key );
		if ( null !== $cached ) {
			return $cached;
		}

		if ( ! PGCC_Rate_Limit::allow() ) {
			return new WP_Error( 'pgcc_rate_limited', __( 'Too many requests. Please try again in a minute.', 'pulsegrid-content-connector' ), array( 'status' => 429 ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => $this->headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) {
			return new WP_Error( 'pgcc_not_found', __( 'Content not found.', 'pulsegrid-content-connector' ), array( 'status' => 404 ) );
		}
		if ( $code >= 400 ) {
			return new WP_Error( 'pgcc_http_' . $code, sprintf( 'Mayo API returned HTTP %d.', $code ), array( 'status' => $code ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'pgcc_bad_json', __( 'Unexpected response from the content service.', 'pulsegrid-content-connector' ) );
		}

		PGCC_Cache::set( $key, $data );
		return $data;
	}

	/**
	 * @param bool $json Whether to ask for JSON.
	 * @return array
	 */
	private function headers( $json = true ) {
		$headers = array( 'apikey' => $this->api_key );
		if ( '' !== $this->trckr ) {
			$headers['trckr'] = $this->trckr;
		}
		if ( $json ) {
			$headers['Accept'] = 'application/json';
		}
		return $headers;
	}
}
