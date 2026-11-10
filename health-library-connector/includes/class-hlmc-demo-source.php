<?php
/**
 * Demo content source.
 *
 * Serves the sample articles in /demo-data in the same JSON shape the Mayo
 * API returns, so the plugin can be installed and evaluated (and a public
 * demo site can run) without API credentials.
 *
 * The demo articles are original placeholder text written for this plugin.
 * They are NOT Mayo Clinic content and are not medical advice.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Demo_Source implements HLMC_Source {

	/** @var string */
	private $dir;

	/** @var array|null */
	private $index = null;

	/**
	 * @param string|null $dir Directory containing index.json, articles/ and images/.
	 */
	public function __construct( $dir = null ) {
		$this->dir = trailingslashit( $dir ? $dir : HLMC_DIR . 'demo-data' );
	}

	public function is_live() {
		return false;
	}

	public function search( $query, $top = 10, $skip = 0 ) {
		$all   = $this->index();
		$query = trim( (string) $query );

		if ( '' === $query || '*' === $query ) {
			$hits = $all;
		} else {
			$terms = preg_split( '/\s+/', strtolower( $query ) );
			$hits  = array();
			foreach ( $all as $item ) {
				$haystack = strtolower( $item['title'] . ' ' . implode( ' ', (array) $item['abstract'] ) . ' ' . implode( ' ', (array) ( $item['keywords'] ?? array() ) ) );
				$score    = 0;
				foreach ( $terms as $term ) {
					if ( '' !== $term && false !== strpos( $haystack, $term ) ) {
						$score += ( false !== stripos( $item['title'], $term ) ) ? 3 : 1;
					}
				}
				if ( $score > 0 ) {
					$item['_score'] = $score;
					$hits[]         = $item;
				}
			}
			usort(
				$hits,
				function ( $a, $b ) {
					return $b['_score'] - $a['_score'];
				}
			);
		}

		$results = array();
		foreach ( array_slice( $hits, max( 0, (int) $skip ), max( 1, (int) $top ) ) as $item ) {
			unset( $item['_score'], $item['keywords'] );
			$results[] = $item;
		}

		return array(
			'count'         => count( $hits ),
			'searchResults' => $results,
			'facets'        => null,
		);
	}

	public function get_content( $content_id ) {
		$content_id = hlmc_sanitize_content_id( $content_id );
		$file       = $this->dir . 'articles/' . $content_id . '.json';
		if ( '' === $content_id || ! is_readable( $file ) ) {
			return new WP_Error( 'hlmc_not_found', __( 'Content not found.', 'health-library-connector' ), array( 'status' => 404 ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $data ) ? $data : new WP_Error( 'hlmc_bad_json', 'Demo article is not valid JSON.' );
	}

	public function get_image( $guid ) {
		$guid = hlmc_sanitize_guid( $guid );
		$file = $this->dir . 'images/' . $guid . '.svg';
		if ( '' === $guid || ! is_readable( $file ) ) {
			return new WP_Error( 'hlmc_image_unavailable', 'Demo image not found.' );
		}
		return array(
			'type' => 'image/svg+xml',
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			'body' => (string) file_get_contents( $file ),
		);
	}

	/**
	 * @return array[]
	 */
	private function index() {
		if ( null === $this->index ) {
			$file = $this->dir . 'index.json';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data        = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
			$this->index = is_array( $data ) ? $data : array();
		}
		return $this->index;
	}
}
