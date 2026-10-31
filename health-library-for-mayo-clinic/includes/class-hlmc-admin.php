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
	private function back( $code, $message = '' ) {
		$url = add_query_arg(
			array(
				'page'        => self::PAGE,
				'hlmc_notice' => $code,
				'hlmc_msg'    => rawurlencode( $message ),
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
		$providers  = '' !== $s['providers_json'] ? $s['providers_json'] : wp_json_encode( HLMC_Providers::example_map(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$notice     = isset( $_GET['hlmc_notice'] ) ? sanitize_key( $_GET['hlmc_notice'] ) : '';
		$notice_msg = isset( $_GET['hlmc_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['hlmc_msg'] ) ) ) : '';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Health Library for Mayo Clinic', 'health-library-for-mayo-clinic' ); ?></h1>

			<?php if ( $notice && $notice_msg ) : ?>
				<div class="notice notice-<?php echo 'error' === $notice ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $notice_msg ); ?></p></div>
			<?php endif; ?>

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
							<p class="description"><?php esc_html_e( 'Maximum 48. The Mayo Clinic Syndication Guide requires content to be refreshed at least every 48 hours.', 'health-library-for-mayo-clinic' ); ?></p>
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
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="none" <?php checked( $s['sidebar'], 'none' ); ?>> <?php esc_html_e( 'No sidebar', 'health-library-for-mayo-clinic' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="cta" <?php checked( $s['sidebar'], 'cta' ); ?>> <?php esc_html_e( 'Call to action (below)', 'health-library-for-mayo-clinic' ); ?></label><br>
								<label><input type="radio" name="<?php echo esc_attr( $this->name( 'sidebar' ) ); ?>" value="providers" <?php checked( $s['sidebar'], 'providers' ); ?>> <?php esc_html_e( 'Specialists matched to the article’s topic', 'health-library-for-mayo-clinic' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-cta-title"><?php esc_html_e( 'Call to action', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<p><input type="text" id="hlmc-cta-title" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_title' ) ); ?>" value="<?php echo esc_attr( $s['cta_title'] ); ?>" placeholder="<?php esc_attr_e( 'Heading', 'health-library-for-mayo-clinic' ); ?>"></p>
							<p><textarea class="large-text" rows="2" name="<?php echo esc_attr( $this->name( 'cta_text' ) ); ?>" placeholder="<?php esc_attr_e( 'Text', 'health-library-for-mayo-clinic' ); ?>"><?php echo esc_textarea( $s['cta_text'] ); ?></textarea></p>
							<p><input type="text" class="regular-text" name="<?php echo esc_attr( $this->name( 'cta_button' ) ); ?>" value="<?php echo esc_attr( $s['cta_button'] ); ?>" placeholder="<?php esc_attr_e( 'Button label', 'health-library-for-mayo-clinic' ); ?>"></p>
							<p><input type="url" class="regular-text code" name="<?php echo esc_attr( $this->name( 'cta_url' ) ); ?>" value="<?php echo esc_attr( $s['cta_url'] ); ?>" placeholder="https://"></p>
							<p class="description"><?php esc_html_e( 'The URL is also used for provider “Schedule appointment” buttons that have no URL of their own.', 'health-library-for-mayo-clinic' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hlmc-providers"><?php esc_html_e( 'Specialty mapping (JSON)', 'health-library-for-mayo-clinic' ); ?></label></th>
						<td>
							<textarea id="hlmc-providers" class="large-text code" rows="14" name="<?php echo esc_attr( $this->name( 'providers_json' ) ); ?>"><?php echo esc_textarea( (string) $providers ); ?></textarea>
							<p class="description">
								<?php echo wp_kses( __( 'Maps Mayo <code>mcvId</code> category IDs (from <code>meta.controlWords</code> in an article response) to a specialty, and each specialty to providers. The pre-filled example uses fictional placeholder providers — replace them before going live.', 'health-library-for-mayo-clinic' ), array( 'code' => array() ) ); ?>
							</p>
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
}
