<?php
/**
 * Plugin Name: Simple Visitor Analytics
 * Plugin URI: https://wordpress.org/plugins/simple-visitor-analytics/
 * Description: Privacy-friendly, lightweight visitor analytics for WordPress.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: DEV OCEEAN
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-visitor-analytics
 * Domain Path: /languages
 *
 * @package SimpleVisitorAnalytics
 */

defined( 'ABSPATH' ) || exit;

define( 'SVA_VERSION', '1.0.0' );
define( 'SVA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SVA_PLUGIN_DIR . 'includes/class-sva-activator.php';
require_once SVA_PLUGIN_DIR . 'includes/class-sva-deactivator.php';
require_once SVA_PLUGIN_DIR . 'includes/class-sva-tracker.php';
require_once SVA_PLUGIN_DIR . 'includes/class-sva-aggregator.php';
require_once SVA_PLUGIN_DIR . 'includes/class-sva-admin.php';

register_activation_hook( __FILE__, array( 'SVA_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SVA_Deactivator', 'deactivate' ) );

new SVA_Tracker();
new SVA_Aggregator();
new SVA_Admin();
