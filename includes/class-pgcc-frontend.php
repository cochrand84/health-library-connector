<?php
/**
 * Front end: shortcodes, document title, canonical tag, and assets.
 *
 *   [pulsegrid]          Full library (landing, search, article) on any page.
 *     sidebar="none|cta|links"      Override the sidebar setting for this page.
 *     featured="12"                 Number of featured articles on the landing view (0 hides).
 *
 *   [pulsegrid_search]   Just the search box; submits to the library page.
 *     placeholder="Search health topics…"
 *
 *   [pulsegrid_topics]   A row of topic buttons linking into the library.
 *     heading="Popular topics"      Optional heading above the buttons.
 *     limit="6"                     Show at most this many (0 = all).
 *     topics="Label|terms; Label|CON-12345678"   Use this list instead of the saved one.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

class PGCC_Frontend {

	const SHORTCODE        = 'pulsegrid';
	const SEARCH_SHORTCODE = 'pulsegrid_search';
	const TOPICS_SHORTCODE = 'pulsegrid_topics';

	/** @var bool Whether the current main query is a library page. */
	private $is_library_page = false;

	/** @var bool */
	private $assets_enqueued = false;

	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_library' ) );
		add_shortcode( self::SEARCH_SHORTCODE, array( $this, 'render_search_form' ) );
		add_shortcode( self::TOPICS_SHORTCODE, array( $this, 'render_topics' ) );

		add_action( 'wp', array( $this, 'detect_library_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ) );
		add_action( 'wp_head', array( $this, 'output_canonical' ), 1 );

		// SEO plugins render their own canonical/title; keep them consistent.
		add_filter( 'wpseo_canonical', array( $this, 'filter_seo_canonical' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_seo_canonical' ) );
		add_filter( 'wpseo_title', array( $this, 'filter_seo_title' ) );
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_seo_title' ) );
	}

	/**
	 * If the queried page contains the library shortcode, resolve the request
	 * now so title and canonical can use the article data.
	 */
	public function detect_library_page() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$is_library = has_shortcode( $post->post_content, self::SHORTCODE )
			|| ( (int) PGCC_Settings::get( 'library_page_id' ) === (int) $post->ID );

		/**
		 * Filter whether the current page is treated as a Pulsegrid library page
		 * (useful if the shortcode lives in a template part or page builder).
		 *
		 * @param bool    $is_library Detected value.
		 * @param WP_Post $post       Queried post.
		 */
		$this->is_library_page = (bool) apply_filters( 'pgcc_is_library_page', $is_library, $post );

		if ( $this->is_library_page ) {
			$request = PGCC_Plugin::instance()->request()->resolve();
			if ( 'article' === $request->mode && $request->canonical_url() ) {
				remove_action( 'wp_head', 'rel_canonical' );
			}
			if ( '' !== $request->error && 'home' === $request->mode && '' !== PGCC_Plugin::instance()->request()->content_id ) {
				status_header( 404 );
			}
		}
	}

	public function register_assets() {
		wp_register_style( 'pgcc', PGCC_URL . 'assets/css/pulsegrid.css', array(), PGCC_VERSION );
		if ( $this->is_library_page ) {
			$this->enqueue_assets();
		}
	}

	private function enqueue_assets() {
		if ( $this->assets_enqueued ) {
			return;
		}
		wp_enqueue_style( 'pgcc-layout', PGCC_URL . 'assets/css/pulsegrid-layout.css', array(), PGCC_VERSION );
		$handle = 'pgcc-layout';
		if ( PGCC_Style::full() ) {
			wp_enqueue_style( 'pgcc', PGCC_URL . 'assets/css/pulsegrid.css', array( 'pgcc-layout' ), PGCC_VERSION );
			$handle = 'pgcc';
		}
		wp_add_inline_style( $handle, PGCC_Style::inline_css() );
		$this->assets_enqueued = true;
	}

	/**
	 * Use the article title as the page title.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_title_parts( $parts ) {
		if ( ! $this->is_library_page ) {
			return $parts;
		}
		$request = PGCC_Plugin::instance()->request();
		if ( 'article' === $request->mode && $request->article_title() ) {
			$parts['title'] = $request->article_title();
		} elseif ( 'search' === $request->mode ) {
			/* translators: %s: search terms */
			$parts['title'] = sprintf( __( 'Search results for “%s”', 'pulsegrid-content-connector' ), $request->query );
		}
		return $parts;
	}

	/**
	 * @param string $title SEO plugin title.
	 * @return string
	 */
	public function filter_seo_title( $title ) {
		if ( ! $this->is_library_page ) {
			return $title;
		}
		$request = PGCC_Plugin::instance()->request();
		if ( 'article' === $request->mode && $request->article_title() ) {
			return $request->article_title() . ' – ' . get_bloginfo( 'name' );
		}
		return $title;
	}

	/**
	 * Mayo's syndication terms require a canonical tag pointing at the
	 * article's meta.originalUrl on every public page that shows its content.
	 */
	public function output_canonical() {
		if ( ! $this->is_library_page || $this->seo_plugin_active() ) {
			return;
		}
		$url = PGCC_Plugin::instance()->request()->canonical_url();
		if ( $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		}
	}

	/**
	 * @param string $canonical SEO plugin canonical.
	 * @return string
	 */
	public function filter_seo_canonical( $canonical ) {
		if ( ! $this->is_library_page ) {
			return $canonical;
		}
		$url = PGCC_Plugin::instance()->request()->canonical_url();
		return $url ? $url : $canonical;
	}

	/** @return bool */
	private function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	/**
	 * Base URL that library links point to: the page the shortcode is on.
	 *
	 * @return string
	 */
	public static function base_url() {
		$id = get_the_ID();
		if ( $id && in_the_loop() ) {
			return get_permalink( $id );
		}
		$queried = get_queried_object_id();
		if ( $queried && is_singular() ) {
			return get_permalink( $queried );
		}
		$page = (int) PGCC_Settings::get( 'library_page_id' );
		return $page ? get_permalink( $page ) : home_url( '/' );
	}

	/**
	 * URL of the configured library page (for the standalone search box).
	 *
	 * @return string
	 */
	public static function library_url() {
		$page = (int) PGCC_Settings::get( 'library_page_id' );
		return $page ? get_permalink( $page ) : self::base_url();
	}

	/**
	 * @param string      $content_id Content ID.
	 * @param string|null $base       Base URL.
	 * @return string
	 */
	public static function article_url( $content_id, $base = null ) {
		return add_query_arg( PGCC_Request::PARAM_ID, rawurlencode( $content_id ), $base ? $base : self::base_url() );
	}

	/**
	 * @param string      $query Search terms.
	 * @param int         $page  Page number.
	 * @param string|null $base  Base URL.
	 * @return string
	 */
	public static function search_url( $query, $page = 1, $base = null ) {
		$args = array( PGCC_Request::PARAM_QUERY => rawurlencode( $query ) );
		if ( $page > 1 ) {
			$args[ PGCC_Request::PARAM_PAGE ] = (int) $page;
		}
		return add_query_arg( $args, $base ? $base : self::base_url() );
	}

	/**
	 * [pulsegrid]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_library( $atts ) {
		$atts = shortcode_atts(
			array(
				'sidebar'  => PGCC_Settings::get( 'sidebar' ),
				'featured' => PGCC_Settings::get( 'featured_count' ),
			),
			$atts,
			self::SHORTCODE
		);

		$this->enqueue_assets();

		$plugin  = PGCC_Plugin::instance();
		$request = $plugin->request()->resolve();
		$source  = $plugin->source();
		$base    = self::base_url();

		$sidebar = PGCC_Settings::normalize_sidebar( $atts['sidebar'] );
		if ( 'home' === $request->mode ) {
			$sidebar = 'none';
		}

		$featured = array();
		if ( 'home' === $request->mode && (int) $atts['featured'] > 0 ) {
			$sample = $source->search( '*', (int) $atts['featured'] );
			if ( ! is_wp_error( $sample ) ) {
				$featured = (array) ( $sample['searchResults'] ?? array() );
				shuffle( $featured );
			}
		}

		$match = null;
		if ( 'article' === $request->mode && $request->article ) {
			$match = PGCC_Care_Links::match( $request->article );
		}

		// Admins get a panel under each article listing its Mayo categories
		// and which sidebar row (if any) matched, to make mapping easy.
		$inspector = 'article' === $request->mode && $request->article
			&& PGCC_Settings::get( 'show_inspector' )
			&& current_user_can( 'manage_options' );

		$args = array(
			'request'  => $request,
			'live'     => $source->is_live(),
			'base_url' => $base,
			'sidebar'  => $sidebar,
			'featured' => $featured,
			'match'     => $match,
			'inspector' => $inspector,
			'settings' => PGCC_Settings::all(),
		);

		ob_start();
		pgcc_template( 'library', $args );
		return (string) ob_get_clean();
	}

	/**
	 * [pulsegrid_search]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_search_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'placeholder' => __( 'Search health topics…', 'pulsegrid-content-connector' ),
			),
			$atts,
			self::SEARCH_SHORTCODE
		);
		$this->enqueue_assets();
		ob_start();
		echo '<div class="pgcc pgcc-search-only">';
		pgcc_template(
			'search-form',
			array(
				'action'      => self::library_url(),
				'query'       => PGCC_Plugin::instance()->request()->query,
				'placeholder' => $atts['placeholder'],
			)
		);
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * [pulsegrid_topics]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_topics( $atts ) {
		$atts = shortcode_atts(
			array(
				'heading' => '',
				'limit'   => 0,
				'topics'  => '',
			),
			$atts,
			self::TOPICS_SHORTCODE
		);

		$topics = '' !== trim( (string) $atts['topics'] )
			? PGCC_Topics::parse( str_replace( ';', "\n", (string) $atts['topics'] ) )
			: PGCC_Topics::all();
		$limit  = absint( $atts['limit'] );
		if ( $limit > 0 ) {
			$topics = array_slice( $topics, 0, $limit );
		}
		if ( ! $topics ) {
			return '';
		}

		$this->enqueue_assets();
		$base  = self::library_url();
		$items = array();
		foreach ( $topics as $topic ) {
			$items[] = array(
				'label' => $topic['label'],
				'url'   => PGCC_Topics::url( $topic, $base ),
			);
		}

		ob_start();
		pgcc_template(
			'topics',
			array(
				'heading' => sanitize_text_field( (string) $atts['heading'] ),
				'items'   => $items,
			)
		);
		return (string) ob_get_clean();
	}
}
