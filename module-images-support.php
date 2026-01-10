<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and multi-focal point support
 * Version: 1.0.0
 * Author: thisismyurl
 * Author URI: https://github.com/thisismyurl
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: timu
 * Domain Path: /languages
 *
 * @package TIMU
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'TIMU_VERSION', '1.0.0' );
define( 'TIMU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIMU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TIMU_PLUGIN_FILE', __FILE__ );

// Include core files.
require_once TIMU_PLUGIN_DIR . 'includes/focal-points.php';
require_once TIMU_PLUGIN_DIR . 'includes/cropping.php';

// Initialize admin UI if in admin context.
if ( is_admin() ) {
	require_once TIMU_PLUGIN_DIR . 'admin/focal-point-editor.php';
}

/**
 * Initialize plugin
 */
function timu_init() {
	// Load text domain for translations.
	load_plugin_textdomain( 'timu', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	
	// Hook into WordPress.
	do_action( 'timu_init' );
}
add_action( 'plugins_loaded', 'timu_init' );

/**
 * Activation hook
 */
function timu_activate() {
	// Set default options or perform setup tasks.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'timu_activate' );

/**
 * Deactivation hook
 */
function timu_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'timu_deactivate' );
