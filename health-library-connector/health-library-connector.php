<?php
/**
 * Plugin Name:       Health Library Connector
 * Plugin URI:        https://github.com/cochrand84/health-library-connector
 * Description:       Display Mayo Clinic Health Information Library content on any WordPress site through the Mayo Clinic GBS Realtime (Content Delivery) API. Requires your own licensed API credentials from Mayo Clinic. Includes a demo mode with sample data.
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            David Cochran
 * Author URI:        https://davidcochran.us
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       health-library-connector
 *
 * This plugin is an independent project. It is not affiliated with,
 * endorsed by, or sponsored by Mayo Clinic. "Mayo Clinic" is a trademark
 * of Mayo Foundation for Medical Education and Research.
 *
 * @package HealthLibraryConnector
 */

defined( 'ABSPATH' ) || exit;

define( 'HLMC_VERSION', '0.3.0' );
define( 'HLMC_FILE', __FILE__ );
define( 'HLMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'HLMC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Mayo's syndication terms require cached content to be refreshed at least
 * every 48 hours. The cache TTL setting can never exceed this value.
 */
define( 'HLMC_MAX_CACHE_TTL', 48 * HOUR_IN_SECONDS );

require_once HLMC_DIR . 'includes/helpers.php';
require_once HLMC_DIR . 'includes/interface-hlmc-source.php';
require_once HLMC_DIR . 'includes/class-hlmc-settings.php';
require_once HLMC_DIR . 'includes/class-hlmc-cache.php';
require_once HLMC_DIR . 'includes/class-hlmc-rate-limit.php';
require_once HLMC_DIR . 'includes/class-hlmc-api-source.php';
require_once HLMC_DIR . 'includes/class-hlmc-demo-source.php';
require_once HLMC_DIR . 'includes/class-hlmc-care-links.php';
require_once HLMC_DIR . 'includes/class-hlmc-topics.php';
require_once HLMC_DIR . 'includes/class-hlmc-image-proxy.php';
require_once HLMC_DIR . 'includes/class-hlmc-request.php';
require_once HLMC_DIR . 'includes/class-hlmc-frontend.php';
require_once HLMC_DIR . 'includes/class-hlmc-admin.php';
require_once HLMC_DIR . 'includes/class-hlmc-plugin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once HLMC_DIR . 'includes/class-hlmc-cli.php';
}

register_activation_hook( __FILE__, array( 'HLMC_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'HLMC_Plugin', 'instance' ) );
