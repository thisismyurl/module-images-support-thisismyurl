<?php
/**
 * AJAX Handlers for Composition Guides
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler for generating grid overlays
 */
add_action('wp_ajax_timu_generate_grid', 'timu_ajax_generate_grid');

function timu_ajax_generate_grid() {
    // Verify nonce
    check_ajax_referer('timu_composition_guides_nonce', 'nonce');
    
    // Get parameters
    $grid_type = isset($_POST['grid_type']) ? sanitize_text_field($_POST['grid_type']) : '';
    $width = isset($_POST['width']) ? absint($_POST['width']) : 800;
    $height = isset($_POST['height']) ? absint($_POST['height']) : 600;
    
    // Validate grid type
    if (!in_array($grid_type, array('rule-of-thirds', 'golden-ratio'))) {
        wp_send_json_error(array('message' => 'Invalid grid type'));
        return;
    }
    
    // Validate dimensions
    if ($width < 1 || $width > 5000 || $height < 1 || $height > 5000) {
        wp_send_json_error(array('message' => 'Invalid dimensions'));
        return;
    }
    
    // Generate grid overlay
    $grid_data = timu_generate_grid_overlay($grid_type, $width, $height);
    
    if (empty($grid_data)) {
        wp_send_json_error(array('message' => 'Failed to generate grid'));
        return;
    }
    
    // Return success with grid data
    wp_send_json_success(array(
        'grid_data' => $grid_data,
        'grid_type' => $grid_type,
        'width' => $width,
        'height' => $height
    ));
}
