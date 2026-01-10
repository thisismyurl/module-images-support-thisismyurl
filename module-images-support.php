<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and OCR text detection
 * Version: 1.0.0
 * Author: Christopher Ross
 * Author URI: https://github.com/thisismyurl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: module-images-support
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TIMU_VERSION', '1.0.0');
define('TIMU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIMU_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core files
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-ocr.php';
require_once TIMU_PLUGIN_DIR . 'includes/timu-functions.php';
require_once TIMU_PLUGIN_DIR . 'includes/timu-admin.php';
require_once TIMU_PLUGIN_DIR . 'includes/timu-media-library.php';

/**
 * Initialize the plugin
 */
function timu_init() {
    // Initialize OCR handler
    TIMU_OCR::get_instance();
    
    // Load admin interface
    if (is_admin()) {
        TIMU_Admin::get_instance();
        TIMU_Media_Library::get_instance();
    }
}
add_action('plugins_loaded', 'timu_init');

/**
 * Activation hook
 */
function timu_activate() {
    // Check for required extensions
    $required_extensions = array('imagick');
    $missing_extensions = array();
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (!empty($missing_extensions)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                __('Module Images Support requires the following PHP extensions: %s. Please install them and try again.', 'module-images-support'),
                implode(', ', $missing_extensions)
            ),
            __('Plugin Activation Error', 'module-images-support'),
            array('back_link' => true)
        );
    }
    
    // Create options
    add_option('timu_ocr_enabled', true);
    add_option('timu_thumbnail_size', 150);
    add_option('timu_min_text_size', 10);
}
register_activation_hook(__FILE__, 'timu_activate');

/**
 * Deactivation hook
 */
function timu_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'timu_deactivate');
