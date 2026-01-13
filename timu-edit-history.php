<?php
/**
 * Plugin Name: TIMU Edit History Timeline
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: Visual timeline of all image edits with one-click rollback to any point
 * Version: 1.0.0
 * Author: thisismyurl
 * License: GPL-2.0+
 * Text Domain: timu-edit-history
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('TIMU_EDIT_HISTORY_VERSION', '1.0.0');
define('TIMU_EDIT_HISTORY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIMU_EDIT_HISTORY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Save edit version on every operation
 *
 * @param int    $attachment_id The attachment ID
 * @param string $edit_type     Type of edit (crop, exposure, contrast, etc.)
 * @param array  $edit_params   Parameters for the edit operation
 */
function timu_save_edit_version($attachment_id, $edit_type, $edit_params) {
    $edit_history = get_post_meta($attachment_id, '_timu_edit_history', true);
    
    if (!is_array($edit_history)) {
        $edit_history = [];
    }
    
    $file_path = get_attached_file($attachment_id);
    
    $version = [
        'timestamp' => current_time('mysql'),
        'type' => $edit_type,
        'params' => $edit_params,
        'file_path' => $file_path,
        'file_hash' => file_exists($file_path) ? md5_file($file_path) : '',
        'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
    ];
    
    $edit_history[] = $version;
    
    // Limit history to configurable number of versions (default 50)
    $max_versions = apply_filters('timu_edit_history_max_versions', 50);
    if (count($edit_history) > $max_versions) {
        array_shift($edit_history);
    }
    
    update_post_meta($attachment_id, '_timu_edit_history', $edit_history);
    
    do_action('timu_edit_version_saved', $attachment_id, $edit_type, $edit_params);
}
add_action('timu_image_edited', 'timu_save_edit_version', 10, 3);

/**
 * Restore to any point in edit history
 *
 * @param int $attachment_id  The attachment ID
 * @param int $version_index  Index of the version to restore
 * @return bool|WP_Error      True on success, WP_Error on failure
 */
function timu_restore_edit_version($attachment_id, $version_index) {
    $edit_history = get_post_meta($attachment_id, '_timu_edit_history', true);
    
    if (!is_array($edit_history)) {
        return new WP_Error('no_history', __('No edit history found', 'timu-edit-history'));
    }
    
    if (!isset($edit_history[$version_index])) {
        return new WP_Error('invalid_version', __('Version not found', 'timu-edit-history'));
    }
    
    $version = $edit_history[$version_index];
    
    // Restore from backup (if Vault available)
    if (function_exists('timu_restore_from_vault')) {
        timu_restore_from_vault($attachment_id, $version['file_path']);
    }
    
    // Truncate history to this point
    $edit_history = array_slice($edit_history, 0, $version_index + 1);
    update_post_meta($attachment_id, '_timu_edit_history', $edit_history);
    
    do_action('timu_version_restored', $attachment_id, $version_index);
    
    return true;
}

/**
 * Get edit history for an attachment
 *
 * @param int $attachment_id The attachment ID
 * @return array             Array of edit history versions
 */
function timu_get_edit_history($attachment_id) {
    $edit_history = get_post_meta($attachment_id, '_timu_edit_history', true);
    
    if (!is_array($edit_history)) {
        return [];
    }
    
    return $edit_history;
}

/**
 * Clear edit history for an attachment
 *
 * @param int $attachment_id The attachment ID
 * @return bool              True on success
 */
function timu_clear_edit_history($attachment_id) {
    delete_post_meta($attachment_id, '_timu_edit_history');
    do_action('timu_edit_history_cleared', $attachment_id);
    return true;
}

/**
 * AJAX handler for restoring edit version
 */
function timu_ajax_restore_version() {
    check_ajax_referer('timu-edit-history', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'timu-edit-history')]);
    }
    
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    $version_index = isset($_POST['version_index']) ? intval($_POST['version_index']) : 0;
    
    if (!$attachment_id) {
        wp_send_json_error(['message' => __('Invalid attachment ID', 'timu-edit-history')]);
    }
    
    $result = timu_restore_edit_version($attachment_id, $version_index);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success([
        'message' => __('Version restored successfully', 'timu-edit-history'),
        'history' => timu_get_edit_history($attachment_id)
    ]);
}
add_action('wp_ajax_timu_restore_version', 'timu_ajax_restore_version');

/**
 * AJAX handler for getting edit history
 */
function timu_ajax_get_history() {
    check_ajax_referer('timu-edit-history', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'timu-edit-history')]);
    }
    
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    
    if (!$attachment_id) {
        wp_send_json_error(['message' => __('Invalid attachment ID', 'timu-edit-history')]);
    }
    
    $history = timu_get_edit_history($attachment_id);
    
    wp_send_json_success(['history' => $history]);
}
add_action('wp_ajax_timu_get_history', 'timu_ajax_get_history');

/**
 * AJAX handler for exporting edit history
 */
function timu_ajax_export_history() {
    check_ajax_referer('timu-edit-history', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'timu-edit-history')]);
    }
    
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    
    if (!$attachment_id) {
        wp_send_json_error(['message' => __('Invalid attachment ID', 'timu-edit-history')]);
    }
    
    $history = timu_get_edit_history($attachment_id);
    
    wp_send_json_success([
        'history' => $history,
        'attachment' => [
            'id' => $attachment_id,
            'title' => get_the_title($attachment_id),
            'url' => wp_get_attachment_url($attachment_id)
        ]
    ]);
}
add_action('wp_ajax_timu_export_history', 'timu_ajax_export_history');

/**
 * Enqueue admin scripts and styles
 */
function timu_enqueue_admin_scripts($hook) {
    // Only load on media pages
    if ($hook !== 'post.php' && $hook !== 'upload.php' && $hook !== 'post-new.php') {
        return;
    }
    
    wp_enqueue_style(
        'timu-edit-history',
        TIMU_EDIT_HISTORY_PLUGIN_URL . 'assets/css/edit-history.css',
        [],
        TIMU_EDIT_HISTORY_VERSION
    );
    
    wp_enqueue_script(
        'timu-edit-history',
        TIMU_EDIT_HISTORY_PLUGIN_URL . 'assets/js/edit-history.js',
        ['jquery'],
        TIMU_EDIT_HISTORY_VERSION,
        true
    );
    
    wp_localize_script('timu-edit-history', 'timuEditHistory', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('timu-edit-history'),
        'strings' => [
            'restore' => __('Restore', 'timu-edit-history'),
            'original' => __('Original', 'timu-edit-history'),
            'confirmRestore' => __('Are you sure you want to restore this version?', 'timu-edit-history'),
            'exportHistory' => __('Export History', 'timu-edit-history'),
            'clearHistory' => __('Clear History', 'timu-edit-history'),
            'noHistory' => __('No edit history available', 'timu-edit-history'),
        ]
    ]);
}
add_action('admin_enqueue_scripts', 'timu_enqueue_admin_scripts');

/**
 * Add meta box to attachment edit screen
 */
function timu_add_edit_history_meta_box() {
    add_meta_box(
        'timu-edit-history-metabox',
        __('Edit History Timeline', 'timu-edit-history'),
        'timu_render_edit_history_meta_box',
        'attachment',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes_attachment', 'timu_add_edit_history_meta_box');

/**
 * Render edit history meta box
 */
function timu_render_edit_history_meta_box($post) {
    $history = timu_get_edit_history($post->ID);
    ?>
    <div class="timu-edit-history-container" data-attachment-id="<?php echo esc_attr($post->ID); ?>">
        <div class="timu-edit-timeline">
            <?php if (empty($history)): ?>
                <p class="timu-no-history"><?php _e('No edit history available', 'timu-edit-history'); ?></p>
            <?php else: ?>
                <?php foreach ($history as $index => $version): ?>
                    <div class="timeline-item" data-version="<?php echo esc_attr($index); ?>">
                        <?php if (!empty($version['thumbnail'])): ?>
                            <img src="<?php echo esc_url($version['thumbnail']); ?>" alt="" class="timeline-thumbnail" />
                        <?php endif; ?>
                        <div class="timeline-details">
                            <span class="timeline-type">
                                <?php echo $index === 0 ? __('Original', 'timu-edit-history') : esc_html($version['type']); ?>
                            </span>
                            <span class="timeline-timestamp"><?php echo esc_html($version['timestamp']); ?></span>
                        </div>
                        <button class="button timu-restore-btn" data-version="<?php echo esc_attr($index); ?>">
                            <?php _e('Restore', 'timu-edit-history'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="timu-history-actions">
            <button class="button timu-export-history-btn"><?php _e('Export History (JSON)', 'timu-edit-history'); ?></button>
            <button class="button timu-clear-history-btn"><?php _e('Clear History', 'timu-edit-history'); ?></button>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler for clearing edit history
 */
function timu_ajax_clear_history() {
    check_ajax_referer('timu-edit-history', 'nonce');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'timu-edit-history')]);
    }
    
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    
    if (!$attachment_id) {
        wp_send_json_error(['message' => __('Invalid attachment ID', 'timu-edit-history')]);
    }
    
    timu_clear_edit_history($attachment_id);
    
    wp_send_json_success(['message' => __('Edit history cleared', 'timu-edit-history')]);
}
add_action('wp_ajax_timu_clear_history', 'timu_ajax_clear_history');
