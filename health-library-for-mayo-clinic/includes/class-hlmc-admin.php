<?php
/**
 * Settings → Health Library screen.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Admin {

	const PAGE = 'health-library';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_hlmc_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_post_hlmc_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_hlmc_create_page', array( $this, 'handle_create_page' ) );
		add_action( 'admin_post_hlmc_save_links', array( $this, 'handle_save_links' ) );
		add_action( 'admin_post_hlmc_import_links', array( $this, 'handle_import_links' ) );
		add_action( 'admin_post_hlmc_export_links', array( $this, 'handle_export_links' ) );
		add_action( 'admin_post_hlmc_links_template', array( $this, 'handle_links_template' ) );
		add_action( 'admin_post_hlmc_reset_links', array( $this, 'handle_reset_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HLMC_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_options_page(
			__( 'Health Library', 'health-library-for-mayo-clinic' ),
			__( 'Health Library', 'health-library-for-mayo-clinic' ),
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'health-library-for-mayo-clinic' ) . '</a>' );
		return $links;
	}

	public function register() {
		register_setting(
			self::PAGE,
			HLMC_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'HLMC_Settings', 'sanitize' ),
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
		$url = add_query_arg(
			array_filter(
				array(
					'page'        => self::PAGE,
					'tab'         => $tab,
					'hlmc_notice' => $code,
					'hlmc_msg'    => rawurlencode( $message ),
				)
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'health-library-for-mayo-clinic' ) );
		}
		check_admin_referer( 'hlmc_clear_cache' );
		$count = HLMC_Cache::clear();
		/* translators: %d: number of cached items */
		$this->back( 'cleared', sprintf( _n( 'Cleared %d cached item.', 'Cleared %d cached items.', $count, 'health-library-for-mayo-clinic' ), $count ) );
	}

	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'health-library-for-mayo-clinic' ) );
		}
		check_admin_referer( 'hlmc_test_connection' );

		if ( '' === HLMC_Settings::api_key() ) {
			$this->back( 'error', __( 'Add an API key first.', 'health-library-for-mayo-clinic' ) );
		}
		$client = new HLMC_API_Source( HLMC_Settings::base_url(), HLMC_Settings::api_key(), HLMC_Settings::trckr(), (string) HLMC_Settings::get( 'language' ) );
		$result = $client->test();
		if ( is_wp_error( $result ) ) {
			$this->back( 'error', $result->get_error_message() );
		}
		$this->back( 'ok', __( 'Connected to the Mayo Clinic API successfully.', 'health-library-for-mayo-clinic' ) );
	}

	public function handle_create_page() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'health-library-for-mayo-clinic' ) );
		}
		check_admin_referer( 'hlmc_create_page' );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Health Library', 'health-library-for-mayo-clinic' ),
				'post_content' => '<!-- wp:shortcode -->[' . HLMC_Frontend::SHORTCODE . ']<!-- /wp:shortcode -->',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			$this->back( 'error', $page_id->get_error_message() );
		}

		$settings                    = HLMC_Settings::all();
		$settings['library_page_id'] = (int) $page_id;
		update_option( HLMC_Settings::OPTION, $settings );
		HLMC_Settings::flush();

		$this->back( 'ok', __( 'Created the Health Library page.', 'health-library-for-mayo-clinic' ) );
	}

	/**
	 * @param string $name Field name inside the option array.
	 * @return string
	 */
	private function name( $name ) {
		return HLMC_Settings::OPTION . '[' . $name . ']';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s          = HLMC_Settings::all();
		$key_const  = HLMC_Settings::api_key_from_constant();
		$trk_const  = HLMC_Settings::trckr_from_constant();
		$has_key    = '' !== HLMC_Settings::api_key();
		$page_id    = (int) $s['library_page_id'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$notice     = isset( $_GET['hlmc_notice'] ) ? sanitize_key( $_GET['hlmc_notice'] ) : '';
		$notice_msg = isset( $_GET['hlmc_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['hlmc_msg'] ) ) ) : '';
		$tab        = isset( $_GET['tab'] ) && 'links' === $_GET['tab'] ? 'links' : 'general';
		// phpcs:enable
		$tab_url    = admin_url( 'options-general.php?page=' . self::PAGE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Health Library for Mayo Clinic', 'health-library-for-mayo-clinic' ); ?></h1>

			<?php if ( $notice && $notice_msg ) : ?>
				<div class="notice notice-<?php echo 'error' === $notice ? 'error' : ( 'warning' === $notice ? 'warning' : 'success' ); ?> is-dismissible"><p><?php echo nl2br( esc_html( $notice_msg ) ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'health-library-for-mayo-clinic' ); ?></a>
				<a href="<?php echo esc_url( $tab_url . '&tab=links' ); ?>" class="nav-tab <?php echo 'links' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Sidebar links', 'health-library-for-mayo-clinic' ); ?></a>
			</nav>

			<?php
			if ( 'links' === $tab ) {
				$this->render_links_tab();
				echo '</div>';
				return;
			}
			?>

			<?php if ( 'live' === $s['mode'] && ! $has_key ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Live mode is selected but no API key is set, so demo content is being shown.', 'health-library-for-mayo-clinic' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Add [health_library] to any page to show the library. Use [health_library_search] for a search box elsewhere (header, sidebar, home page).', 'health-library-for-mayo-clinic' ); ?>
				<?php if ( $page_id && get_post_status( $page_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank"><?php esc_html_e( 'View library page', 'health-library-for-mayo-clinic' ); ?> &rarr;</a>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>

				<h2><?php esc_html_e( 'Content source', 'health-library-for-mayo-clinic' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Mode', 'health-library-for-mayo-clinic' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'mode' ) ); ?>" value="demo" <?php checked( $s['mode'], 'demo' ); ?>> <?php esc_html_e( 'Demo — bundled sample articles, no API key needed', 'health-library-for-mayo-clinic' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'mode' ) ); ?>" value="live" <?php checked( $s['mode'], 'live' ); ?>> <?php esc_html_e( 'Live — Mayo Clinic Realtime API (requires your licensed credentials)', 'health-library-for-mayo-clinic' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-api-key"><?php esc_html_e( 'API key', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<?php if ( $key_const ) : ?>
								<p><code>HLMC_API_KEY</code> <?php esc_html_e( 'is defined in wp-config.php.', 'health-library-for-mayo-clinic' ); ?></p>
							<?php else : ?>
								<input type="password" id="hlmc-api-key" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $this->name( 'api_key' ) ); ?>" value="" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'health-library-for-mayo-clinic' ) : ''; ?>">
								<?php if ( $has_key ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'api_key_clear' ) ); ?>" value="1"> <?php esc_html_e( 'Remove', 'health-library-for-mayo-clinic' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php echo wp_kses( __( 'Tip: define <code>HLMC_API_KEY</code> in wp-config.php instead, so the key is never stored in the database.', 'health-library-for-mayo-clinic' ), array( 'code' => array() ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-trckr"><?php esc_html_e( 'Tracking value (trckr)', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<?php if ( $trk_const ) : ?>
								<p><code>HLMC_TRCKR</code> <?php esc_html_e( 'is defined in wp-config.php.', 'health-library-for-mayo-clinic' ); ?></p>
							<?php else : ?>
								<input type="password" id="hlmc-trckr" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $this->name( 'trckr' ) ); ?>" value="" placeholder="<?php echo '' !== $s['trckr'] ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'health-library-for-mayo-clinic' ) : ''; ?>">
								<?php if ( '' !== $s['trckr'] ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'trckr_clear' ) ); ?>" value="1"> <?php esc_html_e( 'Remove', 'health-library-for-mayo-clinic' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Provided by your Mayo Clinic operations contact; sent as the trckr header.', 'health-library-for-mayo-clinic' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-base"><?php esc_html_e( 'API base URL', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td><input type="url" id="hlmc-base" class="regular-text code" name="<?php echo esc_attr( $this->name( 'base_url' ) ); ?>" value="<?php echo esc_attr( $s['base_url'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-lang"><?php esc_html_e( 'Language', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td><input type="text" id="hlmc-lang" class="small-text" name="<?php echo esc_attr( $this->name( 'language' ) ); ?>" value="<?php echo esc_attr( $s['language'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-cache"><?php esc_html_e( 'Cache lifetime (hours)', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<input type="number" id="hlmc-cache" class="small-text" min="1" max="48" name="<?php echo esc_attr( $this->name( 'cache_hours' ) ); ?>" value="<?php echo esc_attr( $s['cache_hours'] ); ?>">
							<p class="description"><?php esc_html_e( 'Maximum 48. Mayo Clinic’s syndication terms require content to be refreshed at least every 48 hours.', 'health-library-for-mayo-clinic' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Display', 'health-library-for-mayo-clinic' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hlmc-page"><?php esc_html_e( 'Library page', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $this->name( 'library_page_id' ) ),
									'id'                => 'hlmc-page',
									'selected'          => $page_id,
									'show_option_none'  => esc_html__( '— Select —', 'health-library-for-mayo-clinic' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Where [health_library_search] boxes send visitors.', 'health-library-for-mayo-clinic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-per"><?php esc_html_e( 'Results per page', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td><input type="number" id="hlmc-per" class="small-text" min="1" max="50" name="<?php echo esc_attr( $this->name( 'per_page' ) ); ?>" value="<?php echo esc_attr( $s['per_page'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-featured"><?php esc_html_e( 'Featured articles', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<input type="number" id="hlmc-featured" class="small-text" min="0" max="50" name="<?php echo esc_attr( $this->name( 'featured_count' ) ); ?>" value="<?php echo esc_attr( $s['featured_count'] ); ?>">
							<p class="description"><?php esc_html_e( 'Shown on the landing view before a search. 0 hides them.', 'health-library-for-mayo-clinic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-accent"><?php esc_html_e( 'Accent color', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td><input type="text" id="hlmc-accent" class="small-text" pattern="#[0-9A-Fa-f]{6}" name="<?php echo esc_attr( $this->name( 'accent_color' ) ); ?>" value="<?php echo esc_attr( $s['accent_color'] ); ?>"> <span style="display:inline-block;width:1.5em;height:1.5em;vertical-align:middle;border-radius:3px;background:<?php echo esc_attr( $s['accent_color'] ); ?>"></span></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sidebar', 'health-library-for-mayo-clinic' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on search and article views', 'health-library-for-mayo-clinic' ); ?></th>
						<td>
							<?php $sidebar = HLMC_Settings::normalize_sidebar( $s['sidebar'] ); ?>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="none" <?php checked( $sidebar, 'none' ); ?>> <?php esc_html_e( 'No sidebar', 'health-library-for-mayo-clinic' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="cta" <?php checked( $sidebar, 'cta' ); ?>> <?php esc_html_e( 'Call to action (below) only', 'health-library-for-mayo-clinic' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="links" <?php checked( $sidebar, 'links' ); ?>> <?php esc_html_e( 'Links matched to the article’s topic, falling back to the call to action', 'health-library-for-mayo-clinic' ); ?></label>
							</fieldset>
							<p class="description"><a href="<?php echo esc_url( $tab_url . '&tab=links' ); ?>"><?php esc_html_e( 'Edit the topic → link mapping', 'health-library-for-mayo-clinic' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-lph"><?php esc_html_e( 'Link headings', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<p><input type="text" id="hlmc-lph" class="regular-text" name="<?php echo esc_attr( $this->name( 'links_provider_heading' ) ); ?>" value="<?php echo esc_attr( $s['links_provider_heading'] ); ?>"> <span class="description"><?php esc_html_e( 'above the provider link(s)', 'health-library-for-mayo-clinic' ); ?></span></p>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $this->name( 'links_services_heading' ) ); ?>" value="<?php echo esc_attr( $s['links_services_heading'] ); ?>"> <span class="description"><?php esc_html_e( 'above the service links', 'health-library-for-mayo-clinic' ); ?></span></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-cta-title"><?php esc_html_e( 'Call to action', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<p><input type="text" id="hlmc-cta-title" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_title' ) ); ?>" value="<?php echo esc_attr( $s['cta_title'] ); ?>" placeholder="<?php esc_attr_e( 'Heading', 'health-library-for-mayo-clinic' ); ?>"></p>
							<p><textarea class="large-text" rows="2" name="<?php echo esc_attr( $this->name( 'cta_text' ) ); ?>" placeholder="<?php esc_attr_e( 'Text', 'health-library-for-mayo-clinic' ); ?>"><?php echo esc_textarea( $s['cta_text'] ); ?></textarea></p>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_button' ) ); ?>" value="<?php echo esc_attr( $s['cta_button'] ); ?>" placeholder="<?php esc_attr_e( 'Button label', 'health-library-for-mayo-clinic' ); ?>"></p>
							<p><input type="url" class="regular-text code" name="<?php echo esc_attr( $this->name( 'cta_url' ) ); ?>" value="<?php echo esc_attr( $s['cta_url'] ); ?>" placeholder="https://"></p>
							<p class="description"><?php esc_html_e( 'Shown on search results, and on articles with no mapped topic.', 'health-library-for-mayo-clinic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Category inspector', 'health-library-for-mayo-clinic' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $this->name( 'show_inspector' ) ); ?>" value="1" <?php checked( ! empty( $s['show_inspector'] ) ); ?>> <?php esc_html_e( 'Show administrators a panel under each article listing its Mayo categories and which sidebar row matched', 'health-library-for-mayo-clinic' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Tools', 'health-library-for-mayo-clinic' ); ?></h2>
			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="hlmc_test_connection">
					<?php wp_nonce_field( 'hlmc_test_connection' ); ?>
					<?php submit_button( __( 'Test API connection', 'health-library-for-mayo-clinic' ), 'secondary', 'submit', false, $has_key ? array() : array( 'disabled' => 'disabled' ) ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="hlmc_clear_cache">
					<?php wp_nonce_field( 'hlmc_clear_cache' ); ?>
					<?php submit_button( __( 'Clear cache', 'health-library-for-mayo-clinic' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( ! $page_id || ! get_post_status( $page_id ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<input type="hidden" name="action" value="hlmc_create_page">
						<?php wp_nonce_field( 'hlmc_create_page' ); ?>
						<?php submit_button( __( 'Create library page', 'health-library-for-mayo-clinic' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</p>

			<p class="description" style="margin-top:2em">
				<?php esc_html_e( 'This plugin is an independent project and is not affiliated with or endorsed by Mayo Clinic. You are responsible for complying with the terms of your Mayo Clinic content license, including attribution, canonical tags and caching limits.', 'health-library-for-mayo-clinic' ); ?>
			</p>
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
		wp_enqueue_script( 'hlmc-admin-links', HLMC_URL . 'assets/js/admin-links.js', array(), HLMC_VERSION, true );
		wp_enqueue_style( 'hlmc-admin', HLMC_URL . 'assets/css/admin.css', array(), HLMC_VERSION );
	}

	private function render_links_tab() {
		$rows     = HLMC_Care_Links::rows();
		$is_saved = HLMC_Care_Links::has_saved_rows();
		$sidebar  = HLMC_Settings::normalize_sidebar( HLMC_Settings::get( 'sidebar' ) );
		$post_url = admin_url( 'admin-post.php' );
		?>
		<p class="hlmc-intro">
			<?php esc_html_e( 'When someone reads an article, the sidebar shows links to your own pages for that topic. Each row lists one or more Mayo category IDs and the links to show for them.', 'health-library-for-mayo-clinic' ); ?>
		</p>
		<ul class="hlmc-help">
			<li><?php echo wp_kses( __( '<strong>Finding IDs:</strong> open any article on your library page while logged in as an administrator. A yellow panel under the article lists its categories and IDs, and which row matched.', 'health-library-for-mayo-clinic' ), array( 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Which row wins:</strong> categories run from broad (“Diagnosable condition”) to specific (“Gas and gas pains”). The most specific mapped ID wins; ties go to the row higher in this list.', 'health-library-for-mayo-clinic' ), array( 'strong' => array() ) ); ?></li>
			<li><?php echo wp_kses( __( '<strong>Default:</strong> put <code>*</code> in the IDs of one row to use it when nothing else matches.', 'health-library-for-mayo-clinic' ), array( 'strong' => array(), 'code' => array() ) ); ?></li>
		</ul>

		<?php if ( 'links' !== $sidebar ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'The sidebar is not set to show these links. Choose “Links matched to the article’s topic” on the General tab (or use [health_library sidebar="links"]).', 'health-library-for-mayo-clinic' ); ?>
			</p></div>
		<?php endif; ?>
		<?php if ( ! $is_saved && $rows ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'These are example rows for demo mode. Edit and save them, or import your own.', 'health-library-for-mayo-clinic' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>" id="hlmc-links-form">
			<input type="hidden" name="action" value="hlmc_save_links">
			<input type="hidden" name="rows_json" id="hlmc-rows-json" value="">
			<?php wp_nonce_field( 'hlmc_save_links' ); ?>

			<div class="hlmc-links-toolbar">
				<input type="search" id="hlmc-links-filter" placeholder="<?php esc_attr_e( 'Filter by topic, ID or link…', 'health-library-for-mayo-clinic' ); ?>">
				<span class="hlmc-count" id="hlmc-links-count"></span>
			</div>

			<table class="widefat hlmc-links-table">
				<thead>
					<tr>
						<th class="col-order">#</th>
						<th class="col-topic"><?php esc_html_e( 'Topic', 'health-library-for-mayo-clinic' ); ?></th>
						<th class="col-ids"><?php esc_html_e( 'Mayo category IDs', 'health-library-for-mayo-clinic' ); ?></th>
						<th class="col-links"><?php echo esc_html( HLMC_Settings::get( 'links_provider_heading' ) ); ?></th>
						<th class="col-links"><?php echo esc_html( HLMC_Settings::get( 'links_services_heading' ) ); ?></th>
						<th class="col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'health-library-for-mayo-clinic' ); ?></span></th>
					</tr>
				</thead>
				<tbody id="hlmc-links-body"></tbody>
			</table>
			<script type="application/json" id="hlmc-links-data"><?php echo wp_json_encode( $rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ); ?></script>

			<p>
				<button type="button" class="button" id="hlmc-add-row">+ <?php esc_html_e( 'Add row', 'health-library-for-mayo-clinic' ); ?></button>
			</p>
			<?php submit_button( __( 'Save links', 'health-library-for-mayo-clinic' ) ); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Import and export', 'health-library-for-mayo-clinic' ); ?></h2>
		<p><?php echo wp_kses( __( 'Use a spreadsheet: download the template, fill it in, and save it from Excel as <strong>CSV UTF-8</strong>. Put several IDs in one cell separated by semicolons. Add more link columns by continuing the pattern (<code>service4_label</code>, <code>service4_url</code>, <code>provider2_label</code>…).', 'health-library-for-mayo-clinic' ), array( 'strong' => array(), 'code' => array() ) ); ?></p>

		<div class="hlmc-io">
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<input type="hidden" name="action" value="hlmc_links_template">
				<?php wp_nonce_field( 'hlmc_links_template' ); ?>
				<?php submit_button( __( 'Download template', 'health-library-for-mayo-clinic' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<input type="hidden" name="action" value="hlmc_export_links">
				<?php wp_nonce_field( 'hlmc_export_links' ); ?>
				<?php submit_button( __( 'Export current links (CSV)', 'health-library-for-mayo-clinic' ), 'secondary', 'submit', false, $rows ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		</div>

		<form method="post" action="<?php echo esc_url( $post_url ); ?>" enctype="multipart/form-data" class="hlmc-import">
			<input type="hidden" name="action" value="hlmc_import_links">
			<?php wp_nonce_field( 'hlmc_import_links' ); ?>
			<p><input type="file" name="hlmc_csv" accept=".csv,text/csv" required></p>
			<fieldset>
				<label><input type="radio" name="mode" value="merge" checked> <?php esc_html_e( 'Add and update: rows with the same IDs are replaced, new rows are added', 'health-library-for-mayo-clinic' ); ?></label><br>
				<label><input type="radio" name="mode" value="replace"> <?php esc_html_e( 'Replace everything with the file', 'health-library-for-mayo-clinic' ); ?></label>
			</fieldset>
			<?php submit_button( __( 'Import CSV', 'health-library-for-mayo-clinic' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( $is_saved ) : ?>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="hlmc-reset">
				<input type="hidden" name="action" value="hlmc_reset_links">
				<?php wp_nonce_field( 'hlmc_reset_links' ); ?>
				<label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'Delete all sidebar links', 'health-library-for-mayo-clinic' ); ?></label>
				<?php submit_button( __( 'Delete', 'health-library-for-mayo-clinic' ), 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/** Permission + nonce check for link handlers. */
	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'health-library-for-mayo-clinic' ) );
		}
		check_admin_referer( $action );
	}

	public function handle_save_links() {
		$this->guard( 'hlmc_save_links' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded then sanitized row by row in HLMC_Care_Links::clean_row().
		$json = isset( $_POST['rows_json'] ) ? wp_unslash( $_POST['rows_json'] ) : '';
		$rows = json_decode( (string) $json, true );
		if ( ! is_array( $rows ) ) {
			$this->back( 'error', __( 'Nothing was saved: the form data was not received. Is JavaScript enabled?', 'health-library-for-mayo-clinic' ), 'links' );
		}
		$before = count( $rows );
		HLMC_Care_Links::save( $rows );
		$after = count( HLMC_Care_Links::rows() );
		$msg   = sprintf(
			/* translators: %d: number of rows */
			_n( 'Saved %d row.', 'Saved %d rows.', $after, 'health-library-for-mayo-clinic' ),
			$after
		);
		if ( $after < $before ) {
			$msg .= ' ' . sprintf(
				/* translators: %d: number of rows */
				_n( '%d incomplete row (no IDs or no link URL) was dropped.', '%d incomplete rows (no IDs or no link URL) were dropped.', $before - $after, 'health-library-for-mayo-clinic' ),
				$before - $after
			);
		}
		$this->back( $after < $before ? 'warning' : 'ok', $msg, 'links' );
	}

	public function handle_import_links() {
		$this->guard( 'hlmc_import_links' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$file = isset( $_FILES['hlmc_csv'] ) ? $_FILES['hlmc_csv'] : null;
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->back( 'error', __( 'Choose a CSV file to import.', 'health-library-for-mayo-clinic' ), 'links' );
		}
		if ( $file['size'] > 2 * MB_IN_BYTES ) {
			$this->back( 'error', __( 'That file is larger than 2 MB.', 'health-library-for-mayo-clinic' ), 'links' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$parsed = HLMC_Care_Links::from_csv( (string) file_get_contents( $file['tmp_name'] ) );
		if ( empty( $parsed['rows'] ) ) {
			$this->back( 'error', implode( "\n", array_merge( array( __( 'No rows were imported.', 'health-library-for-mayo-clinic' ) ), array_slice( $parsed['errors'], 0, 20 ) ) ), 'links' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$mode = isset( $_POST['mode'] ) && 'replace' === $_POST['mode'] ? 'replace' : 'merge';
		$rows = 'replace' === $mode || ! HLMC_Care_Links::has_saved_rows()
			? $parsed['rows']
			: HLMC_Care_Links::merge( HLMC_Care_Links::rows(), $parsed['rows'] );
		HLMC_Care_Links::save( $rows );

		$msg = sprintf(
			/* translators: 1: rows imported, 2: total rows */
			__( 'Imported %1$d rows. The table now has %2$d rows.', 'health-library-for-mayo-clinic' ),
			count( $parsed['rows'] ),
			count( HLMC_Care_Links::rows() )
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
		$this->guard( 'hlmc_export_links' );
		$this->send_csv( 'health-library-sidebar-links-' . gmdate( 'Y-m-d' ) . '.csv', HLMC_Care_Links::to_csv( HLMC_Care_Links::rows() ) );
	}

	public function handle_links_template() {
		$this->guard( 'hlmc_links_template' );
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
		$this->send_csv( 'health-library-sidebar-links-template.csv', HLMC_Care_Links::to_csv( $example ) );
	}

	public function handle_reset_links() {
		$this->guard( 'hlmc_reset_links' );
		HLMC_Care_Links::reset();
		$this->back( 'ok', __( 'Sidebar links deleted.', 'health-library-for-mayo-clinic' ), 'links' );
	}
}
