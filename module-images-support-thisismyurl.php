<?php
/**
 * Plugin Name: Module Images Support - ThisIsMyUrl
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and batch adjustment inheritance.
 * Version: 1.0.0
 * Author: thisismyurl
 * Author URI: https://github.com/thisismyurl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: timu
 *
 * @package TIMU
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('TIMU_VERSION', '1.0.0');
define('TIMU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIMU_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Copy adjustment layers from source image to batch of target images
 *
 * @param int   $source_attachment_id Source image attachment ID
 * @param array $target_ids           Array of target attachment IDs
 * @return array Results for each target ID
 */
function timu_copy_adjustments_to_batch($source_attachment_id, $target_ids) {
    // Validate source attachment
    if (!get_post($source_attachment_id) || get_post_type($source_attachment_id) !== 'attachment') {
        return ['error' => 'Invalid source attachment ID'];
    }
    
    // Get source adjustments
    $source_adjustments = get_post_meta($source_attachment_id, '_timu_adjustment_layers', true);
    if (!is_array($source_adjustments)) {
        $source_adjustments = [];
    }
    
    $results = [];
    
    foreach ($target_ids as $target_id) {
        // Validate target attachment
        if (!get_post($target_id) || get_post_type($target_id) !== 'attachment') {
            $results[$target_id] = [
                'success' => false,
                'error' => 'Invalid attachment ID',
            ];
            continue;
        }
        
        // Clear existing adjustments
        delete_post_meta($target_id, '_timu_adjustment_layers');
        
        // Copy adjustments with new IDs
        $copied_adjustments = [];
        foreach ($source_adjustments as $adj) {
            // Create a copy of the adjustment
            $new_adj = $adj;
            
            // Remove ID to create new ones
            unset($new_adj['id']);
            $new_adj['id'] = uniqid('adj_');
            
            $copied_adjustments[] = $new_adj;
        }
        
        // Store all adjustments as a single meta value
        if (!empty($copied_adjustments)) {
            update_post_meta($target_id, '_timu_adjustment_layers', $copied_adjustments);
        }
        
        $results[$target_id] = [
            'success' => true,
            'layers_applied' => count($copied_adjustments),
        ];
    }
    
    // Fire action hook for extensibility
    do_action('timu_adjustments_inherited', $source_attachment_id, $target_ids, count($source_adjustments));
    
    return $results;
}

/**
 * Preview inherited adjustments on a target image without saving
 *
 * @param int $source_id Source attachment ID
 * @param int $target_id Target attachment ID
 * @return array|WP_Error Preview result with rendered image URL or error
 */
function timu_preview_inherited_adjustments($source_id, $target_id) {
    // Validate attachments
    if (!get_post($source_id) || get_post_type($source_id) !== 'attachment') {
        return new WP_Error('invalid_source', 'Invalid source attachment ID');
    }
    
    if (!get_post($target_id) || get_post_type($target_id) !== 'attachment') {
        return new WP_Error('invalid_target', 'Invalid target attachment ID');
    }
    
    // Get source adjustments
    $source_adj = get_post_meta($source_id, '_timu_adjustment_layers', true);
    if (!is_array($source_adj)) {
        $source_adj = [];
    }
    
    // Get target file path
    $target_file = get_attached_file($target_id);
    
    if (!$target_file || !file_exists($target_file)) {
        return new WP_Error('file_not_found', 'Target file not found');
    }
    
    // Apply source adjustments to target image temporarily
    $preview_result = timu_render_with_adjustments_temporary($target_file, $source_adj);
    
    return $preview_result;
}

/**
 * Render an image with adjustments temporarily (without saving)
 *
 * @param string $image_path      Path to the image file
 * @param array  $adjustment_layers Array of adjustment layers to apply
 * @return array Preview result with temporary URL and metadata
 */
function timu_render_with_adjustments_temporary($image_path, $adjustment_layers) {
    if (!file_exists($image_path)) {
        return [
            'success' => false,
            'error' => 'Image file not found',
        ];
    }
    
    // Load the image
    $image_editor = wp_get_image_editor($image_path);
    
    if (is_wp_error($image_editor)) {
        return [
            'success' => false,
            'error' => $image_editor->get_error_message(),
        ];
    }
    
    // Apply each adjustment layer
    foreach ($adjustment_layers as $layer) {
        $result = timu_apply_adjustment_layer($image_editor, $layer);
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'error' => $result->get_error_message(),
                'layer' => $layer,
            ];
        }
    }
    
    // Generate temporary filename
    $upload_dir = wp_upload_dir();
    $temp_filename = 'timu-preview-' . uniqid() . '-' . basename($image_path);
    $temp_path = $upload_dir['basedir'] . '/timu-temp/' . $temp_filename;
    
    // Create temp directory if it doesn't exist
    $temp_dir = dirname($temp_path);
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    // Save temporary preview
    $save_result = $image_editor->save($temp_path);
    
    if (is_wp_error($save_result)) {
        return [
            'success' => false,
            'error' => $save_result->get_error_message(),
        ];
    }
    
    // Generate URL for temporary file
    $temp_url = $upload_dir['baseurl'] . '/timu-temp/' . $temp_filename;
    
    return [
        'success' => true,
        'preview_url' => $temp_url,
        'preview_path' => $temp_path,
        'layers_applied' => count($adjustment_layers),
        'expires' => time() + 3600, // 1 hour expiry
    ];
}

/**
 * Apply a single adjustment layer to an image editor instance
 *
 * @param WP_Image_Editor $image_editor Image editor instance
 * @param array           $layer        Adjustment layer configuration
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function timu_apply_adjustment_layer($image_editor, $layer) {
    if (!isset($layer['type'])) {
        return new WP_Error('invalid_layer', 'Adjustment layer missing type');
    }
    
    $type = $layer['type'];
    
    switch ($type) {
        case 'brightness':
            if (isset($layer['value'])) {
                // Brightness adjustment (-100 to 100)
                $result = $image_editor->brightness($layer['value']);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            break;
            
        case 'contrast':
            if (isset($layer['value'])) {
                // Contrast adjustment (-100 to 100)
                $result = $image_editor->contrast($layer['value']);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            break;
            
        case 'grayscale':
            // Convert to grayscale
            $result = $image_editor->grayscale();
            if (is_wp_error($result)) {
                return $result;
            }
            break;
            
        case 'flip':
            if (isset($layer['direction'])) {
                // Flip horizontal or vertical
                $horizontal = $layer['direction'] === 'horizontal';
                $vertical = $layer['direction'] === 'vertical';
                $result = $image_editor->flip($horizontal, $vertical);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            break;
            
        case 'rotate':
            if (isset($layer['angle'])) {
                // Rotate by angle
                $result = $image_editor->rotate($layer['angle']);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            break;
            
        case 'crop':
            if (isset($layer['x'], $layer['y'], $layer['width'], $layer['height'])) {
                // Crop to specified dimensions
                $result = $image_editor->crop(
                    $layer['x'],
                    $layer['y'],
                    $layer['width'],
                    $layer['height']
                );
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            break;
            
        case 'resize':
            if (isset($layer['width'], $layer['height'])) {
                // Resize to specified dimensions
                $result = $image_editor->resize(
                    $layer['width'],
                    $layer['height'],
                    isset($layer['crop']) ? $layer['crop'] : false
                );
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            break;
            
        default:
            // Allow custom adjustment types via filter
            $result = apply_filters('timu_apply_custom_adjustment', true, $image_editor, $layer);
            if (is_wp_error($result)) {
                return $result;
            }
            break;
    }
    
    return true;
}

/**
 * Get adjustment layers for an attachment
 *
 * @param int $attachment_id Attachment ID
 * @return array Array of adjustment layers
 */
function timu_get_adjustments($attachment_id) {
    $adjustments = get_post_meta($attachment_id, '_timu_adjustment_layers', true);
    
    if (!is_array($adjustments)) {
        return [];
    }
    
    return $adjustments;
}

/**
 * Clear all adjustment layers for an attachment
 *
 * @param int $attachment_id Attachment ID
 * @return bool True on success, false on failure
 */
function timu_clear_adjustments($attachment_id) {
    return delete_post_meta($attachment_id, '_timu_adjustment_layers');
}

/**
 * Undo batch operations by restoring previous adjustments
 *
 * @param array $batch_data Batch operation data with backup information
 * @return array Results for each restored item
 */
function timu_undo_batch_operation($batch_data) {
    if (!isset($batch_data['backup']) || !is_array($batch_data['backup'])) {
        return ['error' => 'No backup data available'];
    }
    
    $results = [];
    
    foreach ($batch_data['backup'] as $attachment_id => $previous_adjustments) {
        // Delete current adjustments
        delete_post_meta($attachment_id, '_timu_adjustment_layers');
        
        // Restore previous adjustments
        if (!empty($previous_adjustments) && is_array($previous_adjustments)) {
            update_post_meta($attachment_id, '_timu_adjustment_layers', $previous_adjustments);
        }
        
        $results[$attachment_id] = [
            'success' => true,
            'restored' => true,
        ];
    }
    
    do_action('timu_batch_operation_undone', $batch_data);
    
    return $results;
}

/**
 * Create backup of adjustments before batch operation
 *
 * @param array $target_ids Array of attachment IDs to backup
 * @return array Backup data
 */
function timu_backup_adjustments($target_ids) {
    $backup = [];
    
    foreach ($target_ids as $target_id) {
        $backup[$target_id] = timu_get_adjustments($target_id);
    }
    
    return $backup;
}

/**
 * Clean up temporary preview files older than specified time
 *
 * @param int $max_age Maximum age in seconds (default: 3600 = 1 hour)
 * @return int Number of files deleted
 */
function timu_cleanup_temp_previews($max_age = 3600) {
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/timu-temp/';
    
    if (!file_exists($temp_dir)) {
        return 0;
    }
    
    $files = glob($temp_dir . 'timu-preview-*');
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $max_age) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

// Schedule cleanup of temporary preview files
add_action('timu_cleanup_temp_previews', 'timu_cleanup_temp_previews');

if (!wp_next_scheduled('timu_cleanup_temp_previews')) {
    wp_schedule_event(time(), 'hourly', 'timu_cleanup_temp_previews');
}

// Clean up scheduled event on plugin deactivation
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('timu_cleanup_temp_previews');
});
