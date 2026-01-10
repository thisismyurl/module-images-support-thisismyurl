<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and dual-layer copyright mapping.
 * Version: 1.0.0
 * Author: Christopher Ross
 * Author URI: https://github.com/thisismyurl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: module-images-support
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'TIMU_VERSION', '1.0.0' );
define( 'TIMU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIMU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TIMU_PLUGIN_FILE', __FILE__ );

// Include required files
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-metadata.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-dct-fingerprint.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-lsb-fingerprint.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-ownership.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-dmca.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-admin.php';

// Initialize the plugin
function timu_init() {
    // Load text domain for translations
    load_plugin_textdomain( 'module-images-support', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    // Initialize admin interface
    if ( is_admin() ) {
        new TIMU_Admin();
    }
}
add_action( 'plugins_loaded', 'timu_init' );

// Activation hook
function timu_activate() {
    // Set default options
    $default_options = array(
        'enable_metadata' => true,
        'enable_dct_fingerprint' => true,
        'enable_lsb_fingerprint' => false,
        'enable_visible_overlay' => false,
        'copyright_text' => '',
        'creator_name' => '',
        'rights_usage' => 'All Rights Reserved',
        'usage_terms' => '',
        'license_url' => '',
    );
    
    add_option( 'timu_settings', $default_options );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'timu_activate' );

// Deactivation hook
function timu_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'timu_deactivate' );
