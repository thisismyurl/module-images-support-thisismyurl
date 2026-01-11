<?php
/**
 * Bootstrap file for tests
 * 
 * @package Module_Images_Support
 */

// Load WordPress test environment
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if WordPress test library exists
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find WordPress tests library at $_tests_dir\n";
    echo "Please set WP_TESTS_DIR environment variable to point to your WordPress tests directory\n";
    
    // For now, just define minimal required functions for standalone testing
    define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
    define( 'WPINC', 'wp-includes' );
    
    // Define minimal WordPress functions for testing
    if ( ! function_exists( 'add_action' ) ) {
        function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
    }
    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
    }
    if ( ! function_exists( 'do_action' ) ) {
        function do_action( $hook ) {}
    }
    if ( ! function_exists( 'update_post_meta' ) ) {
        function update_post_meta( $post_id, $key, $value ) {
            return true;
        }
    }
    if ( ! function_exists( 'get_post_meta' ) ) {
        function get_post_meta( $post_id, $key, $single = false ) {
            return $single ? '' : array();
        }
    }
    if ( ! function_exists( 'get_attached_file' ) ) {
        function get_attached_file( $attachment_id ) {
            return '';
        }
    }
    if ( ! function_exists( 'get_post_mime_type' ) ) {
        function get_post_mime_type( $post_id ) {
            return 'image/jpeg';
        }
    }
    if ( ! function_exists( 'current_time' ) ) {
        function current_time( $type ) {
            return gmdate( 'Y-m-d H:i:s' );
        }
    }
    if ( ! function_exists( 'wp_parse_args' ) ) {
        function wp_parse_args( $args, $defaults ) {
            return array_merge( $defaults, (array) $args );
        }
    }
    if ( ! function_exists( 'esc_html' ) ) {
        function esc_html( $text ) {
            return htmlspecialchars( $text, ENT_QUOTES );
        }
    }
    if ( ! function_exists( 'esc_attr' ) ) {
        function esc_attr( $text ) {
            return htmlspecialchars( $text, ENT_QUOTES );
        }
    }
    if ( ! function_exists( 'esc_url' ) ) {
        function esc_url( $url ) {
            return $url;
        }
    }
}

// Define plugin constants
define( 'TIMU_VERSION', '1.0.0' );
define( 'TIMU_PLUGIN_DIR', dirname( dirname( __FILE__ ) ) . '/' );
define( 'TIMU_PLUGIN_URL', 'http://example.com/wp-content/plugins/module-images-support/' );
define( 'TIMU_PLUGIN_FILE', dirname( dirname( __FILE__ ) ) . '/module-images-support.php' );

// Load plugin files
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-metadata.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-dct-fingerprint.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-lsb-fingerprint.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-ownership.php';
require_once TIMU_PLUGIN_DIR . 'includes/class-timu-dmca.php';
