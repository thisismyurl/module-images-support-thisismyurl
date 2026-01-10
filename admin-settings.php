<?php
/**
 * Admin Settings Page
 *
 * @package ModuleImagesSupport
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu
 */
function timu_add_admin_menu() {
    add_options_page(
        __('Image Compression Settings', 'module-images-support'),
        __('Image Compression', 'module-images-support'),
        'manage_options',
        'timu-settings',
        'timu_render_settings_page'
    );
}
add_action('admin_menu', 'timu_add_admin_menu');

/**
 * Register settings
 */
function timu_register_settings() {
    register_setting('timu_settings', 'timu_enable_auto_compress');
    register_setting('timu_settings', 'timu_compression_quality');
    register_setting('timu_settings', 'timu_min_quality_score');
    register_setting('timu_settings', 'timu_compression_algorithms');
    register_setting('timu_settings', 'timu_quality_weight');
    register_setting('timu_settings', 'timu_savings_weight');
}
add_action('admin_init', 'timu_register_settings');

/**
 * Render settings page
 */
function timu_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['timu_settings_nonce']) && wp_verify_nonce($_POST['timu_settings_nonce'], 'timu_settings')) {
        update_option('timu_enable_auto_compress', isset($_POST['timu_enable_auto_compress']));
        update_option('timu_compression_quality', intval($_POST['timu_compression_quality']));
        update_option('timu_min_quality_score', floatval($_POST['timu_min_quality_score']));
        update_option('timu_quality_weight', floatval($_POST['timu_quality_weight']));
        update_option('timu_savings_weight', floatval($_POST['timu_savings_weight']));
        
        $algorithms = isset($_POST['timu_compression_algorithms']) ? $_POST['timu_compression_algorithms'] : array();
        update_option('timu_compression_algorithms', $algorithms);
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'module-images-support') . '</p></div>';
    }

    // Get current settings
    $enable_auto_compress = get_option('timu_enable_auto_compress', false);
    $compression_quality = get_option('timu_compression_quality', 85);
    $min_quality_score = get_option('timu_min_quality_score', 0.95);
    $quality_weight = get_option('timu_quality_weight', 0.6);
    $savings_weight = get_option('timu_savings_weight', 0.4);
    $selected_algorithms = get_option('timu_compression_algorithms', array('mozjpeg', 'webp', 'avif'));

    // Check algorithm availability
    $available_algorithms = timu_get_available_algorithms();
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('timu_settings', 'timu_settings_nonce'); ?>
            
            <h2><?php _e('Intelligent Compression Engine', 'module-images-support'); ?></h2>
            <p><?php _e('Automatically selects the best compression format and settings for each image by comparing multiple algorithms.', 'module-images-support'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="timu_enable_auto_compress">
                            <?php _e('Enable Auto-Compression', 'module-images-support'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="timu_enable_auto_compress" 
                               name="timu_enable_auto_compress" 
                               value="1" 
                               <?php checked($enable_auto_compress); ?>>
                        <p class="description">
                            <?php _e('Automatically compress images on upload using the intelligent compression engine.', 'module-images-support'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="timu_compression_quality">
                            <?php _e('Target Quality', 'module-images-support'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="timu_compression_quality" 
                               name="timu_compression_quality" 
                               value="<?php echo esc_attr($compression_quality); ?>" 
                               min="1" 
                               max="100" 
                               step="1"
                               style="width: 80px;">
                        <p class="description">
                            <?php _e('Target quality level (1-100). Higher values preserve more quality but result in larger files.', 'module-images-support'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="timu_min_quality_score">
                            <?php _e('Minimum Quality Score', 'module-images-support'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="timu_min_quality_score" 
                               name="timu_min_quality_score" 
                               value="<?php echo esc_attr($min_quality_score); ?>" 
                               min="0" 
                               max="1" 
                               step="0.01"
                               style="width: 80px;">
                        <p class="description">
                            <?php _e('Minimum SSIM quality score (0-1). Compressed images below this threshold will be rejected. Default: 0.95', 'module-images-support'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <?php _e('Compression Algorithms', 'module-images-support'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php _e('Select compression algorithms', 'module-images-support'); ?></span>
                            </legend>
                            
                            <?php
                            $algorithms = array(
                                'mozjpeg' => array(
                                    'label' => 'MozJPEG',
                                    'desc' => 'Optimized JPEG encoder (requires cjpeg binary)',
                                ),
                                'guetzli' => array(
                                    'label' => 'Guetzli',
                                    'desc' => 'Perceptual JPEG encoder (requires guetzli binary)',
                                ),
                                'webp' => array(
                                    'label' => 'WebP',
                                    'desc' => 'Modern image format with superior compression (Chrome 23+, Firefox 65+)',
                                ),
                                'avif' => array(
                                    'label' => 'AVIF',
                                    'desc' => 'Next-gen format with best compression (Chrome 85+, Firefox 93+)',
                                ),
                            );
                            
                            foreach ($algorithms as $key => $algo) {
                                $is_available = in_array($key, $available_algorithms);
                                $is_checked = in_array($key, $selected_algorithms);
                                ?>
                                <label>
                                    <input type="checkbox" 
                                           name="timu_compression_algorithms[]" 
                                           value="<?php echo esc_attr($key); ?>"
                                           <?php checked($is_checked); ?>
                                           <?php disabled(!$is_available); ?>>
                                    <strong><?php echo esc_html($algo['label']); ?></strong>
                                    <?php if (!$is_available): ?>
                                        <span style="color: #d63638;">(<?php _e('Not Available', 'module-images-support'); ?>)</span>
                                    <?php else: ?>
                                        <span style="color: #00a32a;">(<?php _e('Available', 'module-images-support'); ?>)</span>
                                    <?php endif; ?>
                                    <br>
                                    <span class="description"><?php echo esc_html($algo['desc']); ?></span>
                                </label>
                                <br><br>
                                <?php
                            }
                            ?>
                            
                            <p class="description">
                                <?php _e('Select which algorithms to use for comparison. The engine will test all selected algorithms and choose the best result.', 'module-images-support'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <?php _e('Scoring Weights', 'module-images-support'); ?>
                    </th>
                    <td>
                        <label>
                            <?php _e('Quality Weight:', 'module-images-support'); ?>
                            <input type="number" 
                                   name="timu_quality_weight" 
                                   value="<?php echo esc_attr($quality_weight); ?>" 
                                   min="0" 
                                   max="1" 
                                   step="0.1"
                                   style="width: 80px;">
                        </label>
                        <br><br>
                        <label>
                            <?php _e('Savings Weight:', 'module-images-support'); ?>
                            <input type="number" 
                                   name="timu_savings_weight" 
                                   value="<?php echo esc_attr($savings_weight); ?>" 
                                   min="0" 
                                   max="1" 
                                   step="0.1"
                                   style="width: 80px;">
                        </label>
                        <p class="description">
                            <?php _e('Balance between quality preservation and file size reduction. Should sum to 1.0. Default: 0.6 quality, 0.4 savings.', 'module-images-support'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Settings', 'module-images-support')); ?>
        </form>
        
        <hr>
        
        <h2><?php _e('System Information', 'module-images-support'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('PHP Version', 'module-images-support'); ?></th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('GD Library', 'module-images-support'); ?></th>
                <td><?php echo function_exists('gd_info') ? __('Installed', 'module-images-support') : __('Not Installed', 'module-images-support'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('WebP Support', 'module-images-support'); ?></th>
                <td><?php echo timu_supports_webp() ? __('Yes', 'module-images-support') : __('No', 'module-images-support'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('AVIF Support', 'module-images-support'); ?></th>
                <td><?php echo timu_supports_avif() ? __('Yes', 'module-images-support') : __('No', 'module-images-support'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('ImageMagick Compare', 'module-images-support'); ?></th>
                <td><?php echo timu_find_binary('compare') ? __('Available', 'module-images-support') : __('Not Available', 'module-images-support'); ?></td>
            </tr>
        </table>
        
        <hr>
        
        <h2><?php _e('Browser Support', 'module-images-support'); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Format', 'module-images-support'); ?></th>
                    <th><?php _e('Browser Support', 'module-images-support'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>AVIF</strong></td>
                    <td>Chrome 85+, Firefox 93+, Opera 71+</td>
                </tr>
                <tr>
                    <td><strong>WebP</strong></td>
                    <td>Chrome 23+, Firefox 65+, Edge 18+, Opera 12.1+, Safari 14+</td>
                </tr>
                <tr>
                    <td><strong>JPEG</strong></td>
                    <td>All browsers (fallback)</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Get list of available compression algorithms
 *
 * @return array Available algorithm names
 */
function timu_get_available_algorithms() {
    $available = array();
    
    // Check MozJPEG
    if (timu_find_binary('cjpeg')) {
        $available[] = 'mozjpeg';
    }
    
    // Check Guetzli
    if (timu_find_binary('guetzli')) {
        $available[] = 'guetzli';
    }
    
    // Check WebP
    if (timu_supports_webp()) {
        $available[] = 'webp';
    }
    
    // Check AVIF
    if (timu_supports_avif()) {
        $available[] = 'avif';
    }
    
    return $available;
}
