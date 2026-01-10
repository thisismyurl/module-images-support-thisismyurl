<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and composition guides
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
require_once TIMU_PLUGIN_DIR . 'includes/composition-guides.php';
require_once TIMU_PLUGIN_DIR . 'includes/admin-ajax.php';

// Initialize plugin
add_action('plugins_loaded', 'timu_init');

function timu_init() {
    // Load text domain for translations
    load_plugin_textdomain('module-images-support', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'timu_enqueue_admin_assets');

function timu_enqueue_admin_assets($hook) {
    // Only load on media/attachment pages
    if ($hook !== 'post.php' && $hook !== 'upload.php' && $hook !== 'media-upload.php') {
        return;
    }
    
    wp_enqueue_style(
        'timu-composition-guides',
        TIMU_PLUGIN_URL . 'assets/css/composition-guides.css',
        array(),
        TIMU_VERSION
    );
    
    wp_enqueue_script(
        'timu-composition-guides',
        TIMU_PLUGIN_URL . 'assets/js/composition-guides.js',
        array('jquery'),
        TIMU_VERSION,
        true
    );
    
    // Pass AJAX URL and nonce to JavaScript
    wp_localize_script('timu-composition-guides', 'timuData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('timu_composition_guides_nonce')
    ));
}
