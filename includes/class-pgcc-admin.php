<?php
/**
 * Settings → Pulsegrid screen.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

class PGCC_Admin {

	const PAGE = 'pulsegrid';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_pgcc_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_post_pgcc_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_pgcc_create_page', array( $this, 'handle_create_page' ) );
		add_action( 'admin_post_pgcc_save_links', array( $this, 'handle_save_links' ) );
		add_action( 'admin_post_pgcc_import_links', array( $this, 'handle_import_links' ) );
		add_action( 'admin_post_pgcc_export_links', array( $this, 'handle_export_links' ) );
		add_action( 'admin_post_pgcc_links_template', array( $this, 'handle_links_template' ) );
		add_action( 'admin_post_pgcc_reset_links', array( $this, 'handle_reset_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PGCC_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_options_page(
			__( 'Pulsegrid', 'pulsegrid-content-connector' ),
			__( 'Pulsegrid', 'pulsegrid-content-connector' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array $links Plugin row links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'pulsegrid-content-connector' ) . '</a>' );
		return $links;
	}

	const TOPICS_GROUP = 'pulsegrid-topics';

	public function register() {
		register_setting(
			self::PAGE,
			PGCC_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'PGCC_Settings', 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
		register_setting(
			self::TOPICS_GROUP,
			PGCC_Topics::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'PGCC_Topics', 'sanitize_option' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Redirect back to the settings screen with a notice.
	 *
	 * @param string $code    Notice code.
	 * @param string $message Message (optional).
	 */
	private function back( $code, $message = '', $tab = '' ) {
		// The message travels in a short-lived per-user transient, not the URL,
		// so a crafted link can't put arbitrary text on this screen.
		set_transient(
			'pgcc_notice_' . get_current_user_id(),
			array(
				'code'    => $code,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		$url = add_query_arg(
			array_filter(
				array(
					'page' => self::PAGE,
					'tab'  => $tab,
				)
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( 'pgcc_clear_cache' );
		$count = PGCC_Cache::clear();
		/* translators: %d: number of cached items */
		$this->back( 'cleared', sprintf( _n( 'Cleared %d cached item.', 'Cleared %d cached items.', $count, 'pulsegrid-content-connector' ), $count ) );
	}

	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( 'pgcc_test_connection' );

		if ( '' === PGCC_Settings::api_key() ) {
			$this->back( 'error', __( 'Add an API key first.', 'pulsegrid-content-connector' ) );
		}
		$client = new PGCC_API_Source( PGCC_Settings::base_url(), PGCC_Settings::api_key(), PGCC_Settings::trckr(), (string) PGCC_Settings::get( 'language' ) );
		$result = $client->test();
		if ( is_wp_error( $result ) ) {
			$this->back( 'error', $result->get_error_message() );
		}
		$this->back( 'ok', __( 'Connected to the Mayo Clinic API successfully.', 'pulsegrid-content-connector' ) );
	}

	public function handle_create_page() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( 'pgcc_create_page' );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Health Library', 'pulsegrid-content-connector' ),
				'post_content' => '<!-- wp:shortcode -->[' . PGCC_Frontend::SHORTCODE . ']<!-- /wp:shortcode -->',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			$this->back( 'error', $page_id->get_error_message() );
		}

		$settings                    = PGCC_Settings::all();
		$settings['library_page_id'] = (int) $page_id;
		update_option( PGCC_Settings::OPTION, $settings );
		PGCC_Settings::flush();

		$this->back( 'ok', __( 'Created the Health Library page.', 'pulsegrid-content-connector' ) );
	}

	/**
	 * @param string $name Field name inside the option array.
	 * @return string
	 */
	private function name( $name ) {
		return PGCC_Settings::OPTION . '[' . $name . ']';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s          = PGCC_Settings::all();
		$key_const  = PGCC_Settings::api_key_from_constant();
		$trk_const  = PGCC_Settings::trckr_from_constant();
		$has_key    = '' !== PGCC_Settings::api_key();
		$page_id    = (int) $s['library_page_id'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$tab        = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tab        = in_array( $tab, array( 'general', 'links', 'topics', 'help' ), true ) ? $tab : 'general';
		// phpcs:enable
		$flash      = get_transient( 'pgcc_notice_' . get_current_user_id() );
		$notice     = is_array( $flash ) ? sanitize_key( (string) $flash['code'] ) : '';
		$notice_msg = is_array( $flash ) ? sanitize_textarea_field( (string) $flash['message'] ) : '';
		if ( $flash ) {
			delete_transient( 'pgcc_notice_' . get_current_user_id() );
		}
		$tab_url    = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pulsegrid Content Connector', 'pulsegrid-content-connector' ); ?></h1>

			<?php if ( $notice && $notice_msg ) : ?>
				<div class="notice notice-<?php echo 'error' === $notice ? 'error' : ( 'warning' === $notice ? 'warning' : 'success' ); ?> is-dismissible"><p><?php echo nl2br( esc_html( $notice_msg ) ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'pulsegrid-content-connector' ); ?></a>
				<a href="<?php echo esc_url( $tab_url . '&tab=links' ); ?>" class="nav-tab <?php echo 'links' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sidebar links', 'pulsegrid-content-connector' ); ?></a>
				<a href="<?php echo esc_url( $tab_url . '&tab=topics' ); ?>" class="nav-tab <?php echo 'topics' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Topic buttons', 'pulsegrid-content-connector' ); ?></a>
				<a href="<?php echo esc_url( $tab_url . '&tab=help' ); ?>" class="nav-tab <?php echo 'help' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Shortcodes &amp; help', 'pulsegrid-content-connector' ); ?></a>
			</nav>

			<?php
			if ( 'general' !== $tab ) {
				if ( 'links' === $tab ) {
					$this->render_links_tab();
				} elseif ( 'topics' === $tab ) {
					$this->render_topics_tab();
				} else {
					$this->render_help_tab();
				}
				echo '</div>';
				return;
			}
			?>

			<?php if ( 'live' === $s['mode'] && ! $has_key ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Live mode is selected but no API key is set, so demo content is being shown.', 'pulsegrid-content-connector' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Add [pulsegrid] to any page to show the library. Use [pulsegrid_search] for a search box and [pulsegrid_topics] for topic buttons anywhere else (home page, header, sidebar).', 'pulsegrid-content-connector' ); ?>
				<a href="<?php echo esc_url( $tab_url . '&tab=help' ); ?>"><?php esc_html_e( 'All shortcodes and options', 'pulsegrid-content-connector' ); ?></a> &middot;
				<?php if ( $page_id && get_post_status( $page_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank"><?php esc_html_e( 'View library page', 'pulsegrid-content-connector' ); ?> &rarr;</a>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>

				<h2><?php esc_html_e( 'Content source', 'pulsegrid-content-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Mode', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'mode' ) ); ?>" value="demo" <?php checked( $s['mode'], 'demo' ); ?>> <?php esc_html_e( 'Demo — bundled sample articles, no API key needed', 'pulsegrid-content-connector' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'mode' ) ); ?>" value="live" <?php checked( $s['mode'], 'live' ); ?>> <?php esc_html_e( 'Live — Mayo Clinic Realtime API (requires your licensed credentials)', 'pulsegrid-content-connector' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-api-key"><?php esc_html_e( 'API key', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<?php if ( $key_const ) : ?>
								<p><code>PGCC_API_KEY</code> <?php esc_html_e( 'is defined in wp-config.php.', 'pulsegrid-content-connector' ); ?></p>
							<?php else : ?>
								<input type="password" id="pgcc-api-key" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $this->name( 'api_key' ) ); ?>" value="" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'pulsegrid-content-connector' ) : ''; ?>">
								<?php if ( $has_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'api_key_clear' ) ); ?>" value="1"> <?php esc_html_e( 'Remove', 'pulsegrid-content-connector' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php echo wp_kses( __( 'Tip: define <code>PGCC_API_KEY</code> in wp-config.php instead, so the key is never stored in the database.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-trckr"><?php esc_html_e( 'Tracking value (trckr)', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<?php if ( $trk_const ) : ?>
								<p><code>PGCC_TRCKR</code> <?php esc_html_e( 'is defined in wp-config.php.', 'pulsegrid-content-connector' ); ?></p>
							<?php else : ?>
								<input type="password" id="pgcc-trckr" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $this->name( 'trckr' ) ); ?>" value="" placeholder="<?php echo '' !== $s['trckr'] ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'pulsegrid-content-connector' ) : ''; ?>">
								<?php if ( '' !== $s['trckr'] ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'trckr_clear' ) ); ?>" value="1"> <?php esc_html_e( 'Remove', 'pulsegrid-content-connector' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Provided by your Mayo Clinic operations contact; sent as the trckr header.', 'pulsegrid-content-connector' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-base"><?php esc_html_e( 'API base URL', 'pulsegrid-content-connector' ); ?></label></th>
						<td><input type="url" id="pgcc-base" class="regular-text code" name="<?php echo esc_attr( $this->name( 'base_url' ) ); ?>" value="<?php echo esc_attr( $s['base_url'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-lang"><?php esc_html_e( 'Language', 'pulsegrid-content-connector' ); ?></label></th>
						<td><input type="text" id="pgcc-lang" class="small-text" name="<?php echo esc_attr( $this->name( 'language' ) ); ?>" value="<?php echo esc_attr( $s['language'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-cache"><?php esc_html_e( 'Cache lifetime (hours)', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<input type="number" id="pgcc-cache" class="small-text" min="1" max="48" name="<?php echo esc_attr( $this->name( 'cache_hours' ) ); ?>" value="<?php echo esc_attr( $s['cache_hours'] ); ?>">
							<p class="description"><?php esc_html_e( 'Maximum 48. Mayo Clinic’s syndication terms require content to be refreshed at least every 48 hours.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Display', 'pulsegrid-content-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pgcc-page"><?php esc_html_e( 'Library page', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $this->name( 'library_page_id' ) ),
									'id'                => 'pgcc-page',
									'selected'          => absint( $page_id ),
									'show_option_none'  => esc_html__( '— Select —', 'pulsegrid-content-connector' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Where [pulsegrid_search] boxes send visitors.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-per"><?php esc_html_e( 'Results per page', 'pulsegrid-content-connector' ); ?></label></th>
						<td><input type="number" id="pgcc-per" class="small-text" min="1" max="50" name="<?php echo esc_attr( $this->name( 'per_page' ) ); ?>" value="<?php echo esc_attr( $s['per_page'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-featured"><?php esc_html_e( 'Featured articles', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<input type="number" id="pgcc-featured" class="small-text" min="0" max="50" name="<?php echo esc_attr( $this->name( 'featured_count' ) ); ?>" value="<?php echo esc_attr( $s['featured_count'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown on the landing view before a search. 0 hides them.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
					<?php
					$pgcc_palette = PGCC_Style::theme_palette();
					$pgcc_accent  = PGCC_Style::accent();
					$pgcc_source  = 'custom' === $s['color_source'] ? 'custom' : 'theme';
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Accent color', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<p class="description pgcc-look-intro"><?php esc_html_e( 'Fonts, text and link colors always come from your theme. The accent color is used for buttons, badges and the sidebar box.', 'pulsegrid-content-connector' ); ?></p>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'color_source' ) ); ?>" value="theme" <?php checked( $pgcc_source, 'theme' ); ?> <?php disabled( empty( $pgcc_palette ) ); ?>> <?php esc_html_e( 'Use a color from my theme', 'pulsegrid-content-connector' ); ?></label>
								<?php if ( $pgcc_palette ) : ?>
									<div class="pgcc-swatches">
										<?php foreach ( $pgcc_palette as $pgcc_c ) : ?>
											<label class="pgcc-swatch" title="<?php echo esc_attr( $pgcc_c['color'] ); ?>">
												<input type="radio" name="<?php echo esc_attr( $this->name( 'theme_color' ) ); ?>" value="<?php echo esc_attr( $pgcc_c['slug'] ); ?>" <?php checked( $pgcc_accent['from_theme'] && $pgcc_accent['slug'] === $pgcc_c['slug'] ); ?>>
												<span class="pgcc-swatch-chip" style="background:<?php echo esc_attr( $pgcc_c['color'] ); ?>"></span>
												<span><?php echo esc_html( $pgcc_c['name'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="description"><?php esc_html_e( 'Linked to the theme: if the theme’s color changes, the library follows.', 'pulsegrid-content-connector' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Your theme doesn’t publish a color palette, so the custom color below is used.', 'pulsegrid-content-connector' ); ?></p>
								<?php endif; ?>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'color_source' ) ); ?>" value="custom" <?php checked( $pgcc_source, 'custom' ); ?>> <?php esc_html_e( 'Use a custom color', 'pulsegrid-content-connector' ); ?></label>
								<input type="text" id="pgcc-accent" class="small-text" pattern="#[0-9A-Fa-f]{6}" aria-label="<?php esc_attr_e( 'Custom accent color', 'pulsegrid-content-connector' ); ?>" name="<?php echo esc_attr( $this->name( 'accent_color' ) ); ?>" value="<?php echo esc_attr( $s['accent_color'] ); ?>">
								<span class="pgcc-swatch-chip" style="background:<?php echo esc_attr( $s['accent_color'] ); ?>"></span>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin styling', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'style_level' ) ); ?>" value="full" <?php checked( 'layout' !== $s['style_level'] ); ?>> <?php esc_html_e( 'Cards, buttons and topic chips in the accent color (recommended)', 'pulsegrid-content-connector' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'style_level' ) ); ?>" value="layout" <?php checked( 'layout', $s['style_level'] ); ?>> <?php esc_html_e( 'Layout only: leave buttons, borders and colors entirely to my theme or custom CSS', 'pulsegrid-content-connector' ); ?></label>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sidebar', 'pulsegrid-content-connector' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on search and article views', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<?php $sidebar = PGCC_Settings::normalize_sidebar( $s['sidebar'] ); ?>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="none" <?php checked( $sidebar, 'none' ); ?>> <?php esc_html_e( 'No sidebar', 'pulsegrid-content-connector' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="cta" <?php checked( $sidebar, 'cta' ); ?>> <?php esc_html_e( 'Call to action (below) only', 'pulsegrid-content-connector' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="links" <?php checked( $sidebar, 'links' ); ?>> <?php esc_html_e( 'Links matched to the article’s topic, falling back to the call to action', 'pulsegrid-content-connector' ); ?></label>
							</fieldset>
							<p class="description"><a href="<?php echo esc_url( $tab_url . '&tab=links' ); ?>"><?php esc_html_e( 'Edit the topic → link mapping', 'pulsegrid-content-connector' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-lph"><?php esc_html_e( 'Link headings', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<p><input type="text" id="pgcc-lph" class="regular-text" name="<?php echo esc_attr( $this->name( 'links_provider_heading' ) ); ?>" value="<?php echo esc_attr( $s['links_provider_heading'] ); ?>"> <span class="description"><?php esc_html_e( 'above the provider link(s)', 'pulsegrid-content-connector' ); ?></span></p>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $this->name( 'links_services_heading' ) ); ?>" value="<?php echo esc_attr( $s['links_services_heading'] ); ?>"> <span class="description"><?php esc_html_e( 'above the service links', 'pulsegrid-content-connector' ); ?></span></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pgcc-cta-title"><?php esc_html_e( 'Call to action', 'pulsegrid-content-connector' ); ?></label></th>
						<td>
							<p><input type="text" id="pgcc-cta-title" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_title' ) ); ?>" value="<?php echo esc_attr( $s['cta_title'] ); ?>" placeholder="<?php esc_attr_e( 'Heading', 'pulsegrid-content-connector' ); ?>"></p>
							<p><textarea class="large-text" rows="2" name="<?php echo esc_attr( $this->name( 'cta_text' ) ); ?>" placeholder="<?php esc_attr_e( 'Text', 'pulsegrid-content-connector' ); ?>"><?php echo esc_textarea( $s['cta_text'] ); ?></textarea></p>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_button' ) ); ?>" value="<?php echo esc_attr( $s['cta_button'] ); ?>" placeholder="<?php esc_attr_e( 'Button label', 'pulsegrid-content-connector' ); ?>"></p>
							<p><input type="url" class="regular-text code" name="<?php echo esc_attr( $this->name( 'cta_url' ) ); ?>" value="<?php echo esc_attr( $s['cta_url'] ); ?>" placeholder="https://"></p>
							<p class="description"><?php esc_html_e( 'Shown on search results, and on articles with no mapped topic.', 'pulsegrid-content-connector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Category inspector', 'pulsegrid-content-connector' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'show_inspector' ) ); ?>" value="1" <?php checked( ! empty( $s['show_inspector'] ) ); ?>> <?php esc_html_e( 'Show administrators a panel under each article listing its Mayo categories and which sidebar row matched', 'pulsegrid-content-connector' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Tools', 'pulsegrid-content-connector' ); ?></h2>
			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="pgcc_test_connection">
					<?php wp_nonce_field( 'pgcc_test_connection' ); ?>
					<?php submit_button( __( 'Test API connection', 'pulsegrid-content-connector' ), 'secondary', 'submit', false, $has_key ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="pgcc_clear_cache">
					<?php wp_nonce_field( 'pgcc_clear_cache' ); ?>
					<?php submit_button( __( 'Clear cache', 'pulsegrid-content-connector' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( ! $page_id || ! get_post_status( $page_id ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<input type="hidden" name="action" value="pgcc_create_page">
						<?php wp_nonce_field( 'pgcc_create_page' ); ?>
						<?php submit_button( __( 'Create library page', 'pulsegrid-content-connector' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</p>

			<p class="description" style="margin-top:2em">
				<?php esc_html_e( 'This plugin is an independent project and is not affiliated with or endorsed by Mayo Clinic. You are responsible for complying with the terms of your Mayo Clinic content license, including attribution, canonical tags and caching limits.', 'pulsegrid-content-connector' ); ?>
			</p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Topic buttons tab
	 * ------------------------------------------------------------------ */

	private function render_topics_tab() {
		$topics   = PGCC_Topics::all();
		$is_saved = PGCC_Topics::has_saved();
		$base     = PGCC_Frontend::library_url();
		?>
		<p class="pgcc-intro">
			<?php echo wp_kses( __( 'Buttons for common topics, shown wherever you place <code>[pulsegrid_topics]</code> (for example on the home page next to <code>[pulsegrid_search]</code>). Each button opens a search or a specific article in the library.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?>
		</p>
		<ul class="pgcc-help">
			<li><?php echo wp_kses( __( 'One topic per line: <code>Button label | what it opens</code>.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Search:</strong> <code>Heart attack | heart attack</code> opens search results for “heart attack”. If the label and the search are the same, the label alone is enough: <code>Asthma</code>.', 'pulsegrid-content-connector' ), array( 'code' => array(), 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Article:</strong> <code>Colonoscopy | CON-20373087</code> opens that article directly. The ID is the <code>mayo-id</code> value in an article’s address.', 'pulsegrid-content-connector' ), array( 'code' => array(), 'strong' => array() ) ); ?></li>
			<li><?php esc_html_e( 'Buttons appear in this order. Lines starting with # are ignored.', 'pulsegrid-content-connector' ); ?></li>
		</ul>

		<?php if ( ! $is_saved ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'This is the starter list. Edit and save it to use your own.', 'pulsegrid-content-connector' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( self::TOPICS_GROUP ); ?>
			<p>
				<label for="pgcc-topics-text" class="screen-reader-text"><?php esc_html_e( 'Topics', 'pulsegrid-content-connector' ); ?></label>
				<textarea id="pgcc-topics-text" class="large-text code" rows="12" name="<?php echo esc_attr( PGCC_Topics::OPTION ); ?>"><?php echo esc_textarea( PGCC_Topics::to_text( $topics ) ); ?></textarea>
			</p>
			<?php submit_button( __( 'Save topics', 'pulsegrid-content-connector' ) ); ?>
		</form>

		<?php if ( $topics ) : ?>
			<h2><?php esc_html_e( 'Preview', 'pulsegrid-content-connector' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Your theme styles the real buttons; this shows the order and where each one goes.', 'pulsegrid-content-connector' ); ?></p>
			<ul class="pgcc-topics-preview">
				<?php foreach ( $topics as $topic ) : ?>
					<li>
						<a href="<?php echo esc_url( PGCC_Topics::url( $topic, $base ) ); ?>" target="_blank"><?php echo esc_html( $topic['label'] ); ?></a>
						<span class="description">
							<?php
							echo esc_html(
								PGCC_Topics::is_content_id( $topic['target'] )
									/* translators: %s: content ID */
									? sprintf( __( 'article %s', 'pulsegrid-content-connector' ), $topic['target'] )
									/* translators: %s: search terms */
									: sprintf( __( 'search “%s”', 'pulsegrid-content-connector' ), $topic['target'] )
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Shortcodes & help tab
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $code Shortcode example.
	 */
	private function code_box( $code ) {
		printf(
			'<input type="text" class="large-text code pgcc-copy" readonly value="%s" aria-label="%s">',
			esc_attr( $code ),
			esc_attr__( 'Shortcode (select to copy)', 'pulsegrid-content-connector' )
		);
	}

	/**
	 * @param array $rows List of [ attribute, values, description ].
	 */
	private function attr_table( array $rows ) {
		?>
		<table class="widefat striped pgcc-attr-table">
			<thead><tr>
				<th><?php esc_html_e( 'Option', 'pulsegrid-content-connector' ); ?></th>
				<th><?php esc_html_e( 'Values', 'pulsegrid-content-connector' ); ?></th>
				<th><?php esc_html_e( 'What it does', 'pulsegrid-content-connector' ); ?></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr><td><code><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo esc_html( $row[1] ); ?></td><td><?php echo esc_html( $row[2] ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_help_tab() {
		$tab_url = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<div class="pgcc-doc">
			<p class="pgcc-intro"><?php esc_html_e( 'Shortcodes work in the block editor (Shortcode block), classic editor, most page builders (look for a “Shortcode” or “Text” element), and widget areas. Select a box below to copy it.', 'pulsegrid-content-connector' ); ?></p>

			<h2><code>[pulsegrid]</code> &mdash; <?php esc_html_e( 'the library', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php esc_html_e( 'The full library: a landing view with search and featured articles, search results, and articles. Put it on one page and choose that page as the Library page on the General tab.', 'pulsegrid-content-connector' ); ?></p>
			<?php $this->code_box( '[pulsegrid]' ); ?>
			<?php $this->code_box( '[pulsegrid sidebar="links" featured="6"]' ); ?>
			<?php
			$this->attr_table(
				array(
					array( 'sidebar', 'none, cta, links', __( 'Sidebar on search and article views. Defaults to the General tab setting. “links” uses the Sidebar links tab.', 'pulsegrid-content-connector' ) ),
					array( 'featured', '0–50', __( 'How many featured articles the landing view shows. 0 hides them.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><code>[pulsegrid_search]</code> &mdash; <?php esc_html_e( 'search box', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php esc_html_e( 'Just the search box, for the home page, a header or a sidebar. Searches open on the Library page.', 'pulsegrid-content-connector' ); ?></p>
			<?php $this->code_box( '[pulsegrid_search placeholder="Search conditions, symptoms, tests…"]' ); ?>
			<?php
			$this->attr_table(
				array(
					array( 'placeholder', __( 'any text', 'pulsegrid-content-connector' ), __( 'Hint text shown in the empty box.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><code>[pulsegrid_topics]</code> &mdash; <?php esc_html_e( 'topic buttons', 'pulsegrid-content-connector' ); ?></h2>
			<p>
				<?php esc_html_e( 'A row of buttons for common topics. Each opens a search or a specific article on the Library page.', 'pulsegrid-content-connector' ); ?>
				<a href="<?php echo esc_url( $tab_url . '&tab=topics' ); ?>"><?php esc_html_e( 'Edit the list on the Topic buttons tab.', 'pulsegrid-content-connector' ); ?></a>
			</p>
			<?php $this->code_box( '[pulsegrid_topics]' ); ?>
			<?php $this->code_box( '[pulsegrid_topics heading="Popular topics" limit="6"]' ); ?>
			<?php $this->code_box( '[pulsegrid_topics topics="Heart attack|heart attack; Colonoscopy|CON-20373087"]' ); ?>
			<?php
			$this->attr_table(
				array(
					array( 'heading', __( 'any text', 'pulsegrid-content-connector' ), __( 'Optional heading above the buttons.', 'pulsegrid-content-connector' ) ),
					array( 'limit', '0–50', __( 'Show only the first N topics. 0 shows all.', 'pulsegrid-content-connector' ) ),
					array( 'topics', 'Label|search; Label|ID', __( 'Use this list instead of the saved one, e.g. a different set on a service-line page. Separate topics with semicolons.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><?php esc_html_e( 'Linking to the library yourself', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php esc_html_e( 'Buttons and menu items can link straight into the library page with these address parameters:', 'pulsegrid-content-connector' ); ?></p>
			<?php
			$lib = PGCC_Frontend::library_url();
			$this->attr_table(
				array(
					array( 'mayo-q', __( 'search terms', 'pulsegrid-content-connector' ), add_query_arg( 'mayo-q', 'colonoscopy', $lib ) ),
					array( 'mayo-id', __( 'content ID', 'pulsegrid-content-connector' ), add_query_arg( 'mayo-id', 'CON-20373087', $lib ) ),
					array( 'mayo-page', __( 'page number', 'pulsegrid-content-connector' ), __( 'Page of search results, with mayo-q.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>

			<h2><?php esc_html_e( 'Changing the layout', 'pulsegrid-content-connector' ); ?></h2>
			<p><?php echo wp_kses( __( 'Colors: fonts, text and links always follow your theme. On the General tab, pick one of your theme’s palette colors (or a custom color) for buttons and badges, or switch “Plugin styling” to layout only and style everything in your theme. You can also override the <code>--pgcc-*</code> CSS custom properties. Markup: copy any file from the plugin’s <code>templates/</code> folder into <code>your-theme/pulsegrid/</code> and edit it there; your copy is used instead and survives plugin updates.', 'pulsegrid-content-connector' ), array( 'code' => array() ) ); ?></p>

			<h2><?php esc_html_e( 'Command line (WP-CLI)', 'pulsegrid-content-connector' ); ?></h2>
			<?php
			$this->attr_table(
				array(
					array( 'wp pulsegrid status', '', __( 'Mode, whether an API key is set, library page, sidebar and topic settings.', 'pulsegrid-content-connector' ) ),
					array( 'wp pulsegrid test', '', __( 'Check the connection to the Mayo Clinic API.', 'pulsegrid-content-connector' ) ),
					array( 'wp pulsegrid cache clear', '', __( 'Empty the content and image cache.', 'pulsegrid-content-connector' ) ),
				)
			);
			?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Sidebar links tab
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_script( 'pgcc-admin-links', PGCC_URL . 'assets/js/admin-links.js', array(), PGCC_VERSION, true );
		wp_enqueue_style( 'pgcc-admin', PGCC_URL . 'assets/css/admin.css', array(), PGCC_VERSION );
	}

	private function render_links_tab() {
		$rows     = PGCC_Care_Links::rows();
		$is_saved = PGCC_Care_Links::has_saved_rows();
		$sidebar  = PGCC_Settings::normalize_sidebar( PGCC_Settings::get( 'sidebar' ) );
		$post_url = admin_url( 'admin-post.php' );
		?>
		<p class="pgcc-intro">
			<?php esc_html_e( 'When someone reads an article, the sidebar shows links to your own pages for that topic. Each row lists one or more Mayo category IDs and the links to show for them.', 'pulsegrid-content-connector' ); ?>
		</p>
		<ul class="pgcc-help">
			<li><?php echo wp_kses( __( '<strong>Finding IDs:</strong> open any article on your library page while logged in as an administrator. A yellow panel under the article lists its categories and IDs, and which row matched.', 'pulsegrid-content-connector' ), array( 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Which row wins:</strong> categories run from broad (“Diagnosable condition”) to specific (“Gas and gas pains”). The most specific mapped ID wins; ties go to the row higher in this list.', 'pulsegrid-content-connector' ), array( 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Default:</strong> put <code>*</code> in the IDs of one row to use it when nothing else matches.', 'pulsegrid-content-connector' ), array( 'strong' => array(), 'code' => array() ) ); ?></li>
		</ul>

		<?php if ( 'links' !== $sidebar ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'The sidebar is not set to show these links. Choose “Links matched to the article’s topic” on the General tab (or use [pulsegrid sidebar="links"]).', 'pulsegrid-content-connector' ); ?>
			</p></div>
		<?php endif; ?>
		<?php if ( ! $is_saved && $rows ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'These are example rows for demo mode. Edit and save them, or import your own.', 'pulsegrid-content-connector' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>" id="pgcc-links-form">
			<input type="hidden" name="action" value="pgcc_save_links">
			<input type="hidden" name="rows_json" id="pgcc-rows-json" value="">
			<?php wp_nonce_field( 'pgcc_save_links' ); ?>

			<div class="pgcc-links-toolbar">
				<input type="search" id="pgcc-links-filter" placeholder="<?php esc_attr_e( 'Filter by topic, ID or link…', 'pulsegrid-content-connector' ); ?>">
				<span class="pgcc-count" id="pgcc-links-count"></span>
			</div>

			<table class="widefat pgcc-links-table">
				<thead>
					<tr>
						<th class="col-order">#</th>
						<th class="col-topic"><?php esc_html_e( 'Topic', 'pulsegrid-content-connector' ); ?></th>
						<th class="col-ids"><?php esc_html_e( 'Mayo category IDs', 'pulsegrid-content-connector' ); ?></th>
						<th class="col-links"><?php echo esc_html( PGCC_Settings::get( 'links_provider_heading' ) ); ?></th>
						<th class="col-links"><?php echo esc_html( PGCC_Settings::get( 'links_services_heading' ) ); ?></th>
						<th class="col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pulsegrid-content-connector' ); ?></span></th>
					</tr>
				</thead>
				<tbody id="pgcc-links-body"></tbody>
			</table>
			<script type="application/json" id="pgcc-links-data"><?php echo wp_json_encode( $rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ); ?></script>

			<p>
				<button type="button" class="button" id="pgcc-add-row">+ <?php esc_html_e( 'Add row', 'pulsegrid-content-connector' ); ?></button>
			</p>
			<?php submit_button( __( 'Save links', 'pulsegrid-content-connector' ) ); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Import and export', 'pulsegrid-content-connector' ); ?></h2>
		<p><?php echo wp_kses( __( 'Use a spreadsheet: download the template, fill it in, and save it from Excel as <strong>CSV UTF-8</strong>. Put several IDs in one cell separated by semicolons. Add more link columns by continuing the pattern (<code>service4_label</code>, <code>service4_url</code>, <code>provider2_label</code>…).', 'pulsegrid-content-connector' ), array( 'strong' => array(), 'code' => array() ) ); ?></p>

		<div class="pgcc-io">
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<input type="hidden" name="action" value="pgcc_links_template">
				<?php wp_nonce_field( 'pgcc_links_template' ); ?>
				<?php submit_button( __( 'Download template', 'pulsegrid-content-connector' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<input type="hidden" name="action" value="pgcc_export_links">
				<?php wp_nonce_field( 'pgcc_export_links' ); ?>
				<?php submit_button( __( 'Export current links (CSV)', 'pulsegrid-content-connector' ), 'secondary', 'submit', false, $rows ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		</div>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>" enctype="multipart/form-data" class="pgcc-import">
			<input type="hidden" name="action" value="pgcc_import_links">
			<?php wp_nonce_field( 'pgcc_import_links' ); ?>
			<p><input type="file" name="pgcc_csv" accept=".csv,text/csv" required></p>
			<fieldset>
				<label><input type="radio" name="mode" value="merge" checked> <?php esc_html_e( 'Add and update: rows with the same IDs are replaced, new rows are added', 'pulsegrid-content-connector' ); ?></label><br>
				<label><input type="radio" name="mode" value="replace"> <?php esc_html_e( 'Replace everything with the file', 'pulsegrid-content-connector' ); ?></label>
			</fieldset>
			<?php submit_button( __( 'Import CSV', 'pulsegrid-content-connector' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( $is_saved ) : ?>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="pgcc-reset">
				<input type="hidden" name="action" value="pgcc_reset_links">
				<?php wp_nonce_field( 'pgcc_reset_links' ); ?>
				<label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'Delete all sidebar links', 'pulsegrid-content-connector' ); ?></label>
				<?php submit_button( __( 'Delete', 'pulsegrid-content-connector' ), 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/** Permission + nonce check for link handlers. */
	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'pulsegrid-content-connector' ) );
		}
		check_admin_referer( $action );
	}

	public function handle_save_links() {
		$this->guard( 'pgcc_save_links' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in guard(); JSON is decoded then sanitized row by row in PGCC_Care_Links::clean_row().
		$json = isset( $_POST['rows_json'] ) ? wp_unslash( $_POST['rows_json'] ) : '';
		$rows = json_decode( (string) $json, true );
		if ( ! is_array( $rows ) ) {
			$this->back( 'error', __( 'Nothing was saved: the form data was not received. Is JavaScript enabled?', 'pulsegrid-content-connector' ), 'links' );
		}
		$before = count( $rows );
		PGCC_Care_Links::save( $rows );
		$after = count( PGCC_Care_Links::rows() );
		$msg   = sprintf(
			/* translators: %d: number of rows */
			_n( 'Saved %d row.', 'Saved %d rows.', $after, 'pulsegrid-content-connector' ),
			$after
		);
		if ( $after < $before ) {
			$msg .= ' ' . sprintf(
				/* translators: %d: number of rows */
				_n( '%d incomplete row (no IDs or no link URL) was dropped.', '%d incomplete rows (no IDs or no link URL) were dropped.', $before - $after, 'pulsegrid-content-connector' ),
				$before - $after
			);
		}
		$this->back( $after < $before ? 'warning' : 'ok', $msg, 'links' );
	}

	public function handle_import_links() {
		$this->guard( 'pgcc_import_links' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce checked in guard(); the upload is size-checked and parsed/sanitized by PGCC_Care_Links::from_csv().
		$file = isset( $_FILES['pgcc_csv'] ) ? $_FILES['pgcc_csv'] : null;
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->back( 'error', __( 'Choose a CSV file to import.', 'pulsegrid-content-connector' ), 'links' );
		}
		if ( $file['size'] > 2 * MB_IN_BYTES ) {
			$this->back( 'error', __( 'That file is larger than 2 MB.', 'pulsegrid-content-connector' ), 'links' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$parsed = PGCC_Care_Links::from_csv( (string) file_get_contents( $file['tmp_name'] ) );
		if ( empty( $parsed['rows'] ) ) {
			$this->back( 'error', implode( "\n", array_merge( array( __( 'No rows were imported.', 'pulsegrid-content-connector' ) ), array_slice( $parsed['errors'], 0, 20 ) ) ), 'links' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$mode = isset( $_POST['mode'] ) && 'replace' === $_POST['mode'] ? 'replace' : 'merge';
		$rows = 'replace' === $mode || ! PGCC_Care_Links::has_saved_rows()
			? $parsed['rows']
			: PGCC_Care_Links::merge( PGCC_Care_Links::rows(), $parsed['rows'] );
		PGCC_Care_Links::save( $rows );

		$msg = sprintf(
			/* translators: 1: rows imported, 2: total rows */
			__( 'Imported %1$d rows. The table now has %2$d rows.', 'pulsegrid-content-connector' ),
			count( $parsed['rows'] ),
			count( PGCC_Care_Links::rows() )
		);
		if ( $parsed['errors'] ) {
			$msg .= "\n" . implode( "\n", array_slice( $parsed['errors'], 0, 20 ) );
		}
		$this->back( $parsed['errors'] ? 'warning' : 'ok', $msg, 'links' );
	}

	/**
	 * @param string $filename Download name.
	 * @param string $csv      CSV text.
	 */
	private function send_csv( $filename, $csv ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		exit;
	}

	public function handle_export_links() {
		$this->guard( 'pgcc_export_links' );
		$this->send_csv( 'pulsegrid-sidebar-links-' . gmdate( 'Y-m-d' ) . '.csv', PGCC_Care_Links::to_csv( PGCC_Care_Links::rows() ) );
	}

	public function handle_links_template() {
		$this->guard( 'pgcc_links_template' );
		$example = array(
			array(
				'ids'      => array( '1452', '2926', '138500' ),
				'topic'    => 'Gastroenterology',
				'provider' => array( array( 'label' => 'Gastroenterology', 'url' => 'https://example.org/providers/gastroenterology/' ) ),
				'services' => array(
					array( 'label' => 'Lab Services', 'url' => 'https://example.org/lab-services/' ),
					array( 'label' => 'Diagnostic Imaging', 'url' => 'https://example.org/diagnostic-imaging/' ),
				),
			),
			array(
				'ids'      => array( '*' ),
				'topic'    => 'Default (no match)',
				'provider' => array( array( 'label' => 'Find a Doctor', 'url' => 'https://example.org/find-a-doctor/' ) ),
				'services' => array(),
			),
		);
		$this->send_csv( 'pulsegrid-sidebar-links-template.csv', PGCC_Care_Links::to_csv( $example ) );
	}

	public function handle_reset_links() {
		$this->guard( 'pgcc_reset_links' );
		PGCC_Care_Links::reset();
		$this->back( 'ok', __( 'Sidebar links deleted.', 'pulsegrid-content-connector' ), 'links' );
	}
}
