<?php
/**
 * Resolves what the current request is asking for (home / search / article)
 * and fetches the data once, so the shortcode, <title>, and canonical tag
 * all share the same result.
 *
 * URL parameters (kept compatible with the ?mayo-id= pattern):
 *   ?mayo-id=CON-20372695   article
 *   ?mayo-q=diabetes        search
 *   ?mayo-page=2            search results page
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

class PGCC_Request {

	const PARAM_ID    = 'mayo-id';
	const PARAM_QUERY = 'mayo-q';
	const PARAM_PAGE  = 'mayo-page';

	/** @var string 'home' | 'search' | 'article' */
	public $mode = 'home';

	/** @var string */
	public $content_id = '';

	/** @var string */
	public $query = '';

	/** @var int */
	public $page = 1;

	/** @var array|null */
	public $article = null;

	/** @var array|null */
	public $search = null;

	/** @var string User-facing error message. */
	public $error = '';

	/** @var bool */
	private $resolved = false;

	/** @var PGCC_Source */
	private $source;

	/**
	 * @param PGCC_Source $source Content source.
	 * @param array|null  $params Query parameters (defaults to $_GET).
	 */
	public function __construct( PGCC_Source $source, $params = null ) {
		$this->source = $source;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public view parameters.
		$params = is_array( $params ) ? $params : wp_unslash( $_GET );

		if ( ! empty( $params[ self::PARAM_ID ] ) ) {
			$this->content_id = pgcc_sanitize_content_id( $params[ self::PARAM_ID ] );
			$this->mode       = '' !== $this->content_id ? 'article' : 'home';
		} elseif ( isset( $params[ self::PARAM_QUERY ] ) && '' !== trim( (string) $params[ self::PARAM_QUERY ] ) ) {
			$this->query = mb_substr( sanitize_text_field( $params[ self::PARAM_QUERY ] ), 0, 200 );
			$this->mode  = 'search';
		}

		$this->page = isset( $params[ self::PARAM_PAGE ] ) ? max( 1, min( 100, absint( $params[ self::PARAM_PAGE ] ) ) ) : 1;
	}

	/**
	 * Fetch data for the current mode (idempotent).
	 *
	 * @return $this
	 */
	public function resolve() {
		if ( $this->resolved ) {
			return $this;
		}
		$this->resolved = true;

		if ( 'article' === $this->mode ) {
			$result = $this->source->get_content( $this->content_id );
			if ( ! is_wp_error( $result ) ) {
				$result = pgcc_normalize_article( $result );
			}
			if ( is_wp_error( $result ) || empty( $result['body'] ) ) {
				$this->error = __( 'The requested article could not be loaded. Please try again or search below.', 'pulsegrid-content-connector' );
				$this->mode  = 'home';
			} else {
				$this->article = $result;
			}
		}

		if ( 'search' === $this->mode ) {
			$per    = (int) PGCC_Settings::get( 'per_page' );
			$result = $this->source->search( $this->query, $per, ( $this->page - 1 ) * $per );
			if ( is_wp_error( $result ) ) {
				$this->error = __( 'Search is temporarily unavailable. Please try again shortly.', 'pulsegrid-content-connector' );
				$this->mode  = 'home';
			} else {
				$this->search = $result;
			}
		}

		return $this;
	}

	/** @return string */
	public function article_title() {
		if ( ! $this->article ) {
			return '';
		}
		return wp_strip_all_tags( (string) ( $this->article['title'] ?? '' ) );
	}

	/** @return string Mayo's canonical URL for the article, or ''. */
	public function canonical_url() {
		if ( ! $this->article || ! $this->source->is_live() ) {
			return '';
		}
		$url = (string) ( $this->article['meta']['originalUrl'] ?? '' );
		return wp_http_validate_url( $url ) ? $url : '';
	}
}
