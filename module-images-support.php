<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and SSIM quality scoring
 * Version: 1.0.0
 * Author: Christopher Ross
 * Author URI: https://thisismyurl.com
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

/**
 * Convert RGB image to grayscale for structural comparison
 *
 * @param resource $image GD image resource
 * @return array 2D array of grayscale values
 */
function timu_rgb_to_grayscale($image) {
    $width = imagesx($image);
    $height = imagesy($image);
    $grayscale = array();
    
    for ($y = 0; $y < $height; $y++) {
        $grayscale[$y] = array();
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Standard grayscale conversion (ITU-R BT.601)
            $gray = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
            $grayscale[$y][$x] = $gray;
        }
    }
    
    return $grayscale;
}

/**
 * Extract a window from grayscale image data
 *
 * @param array $grayscale 2D array of grayscale values
 * @param int $x X coordinate of window start
 * @param int $y Y coordinate of window start
 * @param int $size Window size (width and height)
 * @return array 2D array representing the extracted window
 */
function timu_extract_window($grayscale, $x, $y, $size) {
    $window = array();
    
    for ($i = 0; $i < $size; $i++) {
        $window[$i] = array();
        for ($j = 0; $j < $size; $j++) {
            $window[$i][$j] = isset($grayscale[$y + $i][$x + $j]) ? $grayscale[$y + $i][$x + $j] : 0;
        }
    }
    
    return $window;
}

/**
 * Calculate SSIM for a single window
 *
 * @param array $window1 First window data
 * @param array $window2 Second window data
 * @return float SSIM score (0-1)
 */
function timu_ssim_window($window1, $window2) {
    $size = count($window1);
    $n = $size * $size;
    
    // Calculate means
    $mean1 = 0;
    $mean2 = 0;
    for ($i = 0; $i < $size; $i++) {
        for ($j = 0; $j < $size; $j++) {
            $mean1 += $window1[$i][$j];
            $mean2 += $window2[$i][$j];
        }
    }
    $mean1 /= $n;
    $mean2 /= $n;
    
    // Calculate variances and covariance
    $var1 = 0;
    $var2 = 0;
    $covar = 0;
    
    for ($i = 0; $i < $size; $i++) {
        for ($j = 0; $j < $size; $j++) {
            $diff1 = $window1[$i][$j] - $mean1;
            $diff2 = $window2[$i][$j] - $mean2;
            $var1 += $diff1 * $diff1;
            $var2 += $diff2 * $diff2;
            $covar += $diff1 * $diff2;
        }
    }
    
    $var1 /= $n;
    $var2 /= $n;
    $covar /= $n;
    
    // SSIM constants (for dynamic range L=255)
    $c1 = pow(0.01 * 255, 2);
    $c2 = pow(0.03 * 255, 2);
    
    // Calculate SSIM
    $numerator = (2 * $mean1 * $mean2 + $c1) * (2 * $covar + $c2);
    $denominator = ($mean1 * $mean1 + $mean2 * $mean2 + $c1) * ($var1 + $var2 + $c2);
    
    if ($denominator == 0) {
        return 1.0;
    }
    
    return $numerator / $denominator;
}

/**
 * Load image from file path with support for multiple formats
 *
 * @param string $path Path to image file
 * @return resource|false GD image resource or false on failure
 */
function timu_load_image($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $image_info = @getimagesize($path);
    if ($image_info === false) {
        return false;
    }
    
    $mime_type = $image_info['mime'];
    
    switch ($mime_type) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/gif':
            return @imagecreatefromgif($path);
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                return @imagecreatefromwebp($path);
            }
            break;
    }
    
    return false;
}

/**
 * Calculate SSIM score between two images
 *
 * @param string $original_path Path to original image
 * @param string $compressed_path Path to compressed image
 * @param bool $ignore_luminance Whether to ignore luminance differences (default: false)
 * @return float|WP_Error SSIM score (0-1) or WP_Error on failure
 */
function timu_calculate_ssim($original_path, $compressed_path, $ignore_luminance = false) {
    // Load both images
    $orig_img = timu_load_image($original_path);
    $comp_img = timu_load_image($compressed_path);
    
    if ($orig_img === false) {
        return new WP_Error('image_load_failed', 'Failed to load original image');
    }
    
    if ($comp_img === false) {
        imagedestroy($orig_img);
        return new WP_Error('image_load_failed', 'Failed to load compressed image');
    }
    
    $width = imagesx($orig_img);
    $height = imagesy($orig_img);
    $comp_width = imagesx($comp_img);
    $comp_height = imagesy($comp_img);
    
    // Verify dimensions match
    if ($width !== $comp_width || $height !== $comp_height) {
        imagedestroy($orig_img);
        imagedestroy($comp_img);
        return new WP_Error(
            'dimension_mismatch',
            sprintf('Image dimensions do not match: %dx%d vs %dx%d', $width, $height, $comp_width, $comp_height)
        );
    }
    
    // Convert to grayscale for structural comparison
    $orig_gray = timu_rgb_to_grayscale($orig_img);
    $comp_gray = timu_rgb_to_grayscale($comp_img);
    
    imagedestroy($orig_img);
    imagedestroy($comp_img);
    
    // SSIM calculation with sliding window
    $ssim_sum = 0;
    $window_size = 11; // 11x11 sliding window
    $num_windows = 0;
    
    for ($x = 0; $x <= $width - $window_size; $x += $window_size) {
        for ($y = 0; $y <= $height - $window_size; $y += $window_size) {
            $orig_window = timu_extract_window($orig_gray, $x, $y, $window_size);
            $comp_window = timu_extract_window($comp_gray, $x, $y, $window_size);
            
            $ssim = timu_ssim_window($orig_window, $comp_window);
            $ssim_sum += $ssim;
            $num_windows++;
        }
    }
    
    // Average SSIM across all windows
    if ($num_windows == 0) {
        return new WP_Error('calculation_failed', 'No windows could be calculated for SSIM');
    }
    
    return $ssim_sum / $num_windows;
}

/**
 * Validate compression quality using SSIM threshold
 *
 * @param string $original Path to original image
 * @param string $compressed Path to compressed image
 * @param float $min_ssim Minimum acceptable SSIM score (default: 0.95)
 * @return bool|WP_Error True if quality is acceptable, WP_Error otherwise
 */
function timu_validate_compression_quality($original, $compressed, $min_ssim = 0.95) {
    $ssim = timu_calculate_ssim($original, $compressed);
    
    if (is_wp_error($ssim)) {
        return $ssim;
    }
    
    if ($ssim < $min_ssim) {
        return new WP_Error(
            'compression_quality_low',
            sprintf('Compression SSIM score %.3f below threshold %.3f', $ssim, $min_ssim)
        );
    }
    
    return true;
}

/**
 * Get quality threshold description based on SSIM score
 *
 * @param float $ssim SSIM score (0-1)
 * @return string Quality description
 */
function timu_get_quality_description($ssim) {
    if ($ssim >= 0.99) {
        return 'Imperceptible quality loss (best)';
    } elseif ($ssim >= 0.95) {
        return 'Visible only under scrutiny';
    } elseif ($ssim >= 0.90) {
        return 'Noticeable but acceptable';
    } else {
        return 'Visible degradation (reject)';
    }
}

/**
 * Get SSIM threshold from settings
 *
 * @return float SSIM threshold value
 */
function timu_get_ssim_threshold() {
    return get_option('timu_ssim_threshold', 0.95);
}

/**
 * Update SSIM threshold setting
 *
 * @param float $threshold New threshold value (0-1)
 * @return bool Whether the update was successful
 */
function timu_update_ssim_threshold($threshold) {
    $threshold = floatval($threshold);
    if ($threshold < 0 || $threshold > 1) {
        return false;
    }
    return update_option('timu_ssim_threshold', $threshold);
}

/**
 * Add SSIM score to image metadata
 *
 * @param int $attachment_id Attachment ID
 * @param float $ssim_score SSIM score
 * @param string $compared_to Path or description of what was compared
 * @return bool Whether the update was successful
 */
function timu_save_image_ssim_score($attachment_id, $ssim_score, $compared_to = '') {
    $metadata = array(
        'ssim_score' => $ssim_score,
        'compared_to' => $compared_to,
        'timestamp' => current_time('mysql'),
        'quality_description' => timu_get_quality_description($ssim_score)
    );
    
    return update_post_meta($attachment_id, '_timu_ssim_metadata', $metadata);
}

/**
 * Get SSIM score for an image
 *
 * @param int $attachment_id Attachment ID
 * @return array|false SSIM metadata or false if not found
 */
function timu_get_image_ssim_score($attachment_id) {
    return get_post_meta($attachment_id, '_timu_ssim_metadata', true);
}

/**
 * Initialize plugin
 */
function timu_init() {
    // Register default settings
    if (get_option('timu_ssim_threshold') === false) {
        add_option('timu_ssim_threshold', 0.95);
    }
}
add_action('init', 'timu_init');

/**
 * Add admin menu
 */
function timu_admin_menu() {
    add_options_page(
        'Image Quality Settings',
        'Image Quality',
        'manage_options',
        'timu-image-quality',
        'timu_settings_page'
    );
}
add_action('admin_menu', 'timu_admin_menu');

/**
 * Render settings page
 */
function timu_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle form submission
    if (isset($_POST['timu_save_settings']) && check_admin_referer('timu_settings_nonce')) {
        $threshold = isset($_POST['timu_ssim_threshold']) ? floatval($_POST['timu_ssim_threshold']) : 0.95;
        if (timu_update_ssim_threshold($threshold)) {
            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Invalid threshold value. Must be between 0 and 1.</p></div>';
        }
    }
    
    $current_threshold = timu_get_ssim_threshold();
    
    ?>
    <div class="wrap">
        <h1>Image Quality Settings</h1>
        <p>Configure SSIM (Structural Similarity Index) quality scoring for compressed images.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('timu_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="timu_ssim_threshold">SSIM Quality Threshold</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="timu_ssim_threshold" 
                               name="timu_ssim_threshold" 
                               value="<?php echo esc_attr($current_threshold); ?>" 
                               step="0.01" 
                               min="0" 
                               max="1" 
                               class="regular-text">
                        <p class="description">
                            Minimum acceptable SSIM score (0-1). Default: 0.95<br>
                            <strong>Quality Thresholds:</strong><br>
                            • 0.99+: Imperceptible quality loss (best)<br>
                            • 0.95-0.99: Visible only under scrutiny<br>
                            • 0.90-0.95: Noticeable but acceptable<br>
                            • &lt;0.90: Reject (visible degradation)
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="timu_save_settings" 
                       id="submit" 
                       class="button button-primary" 
                       value="Save Settings">
            </p>
        </form>
        
        <hr>
        
        <h2>About SSIM Quality Scoring</h2>
        <p>The Structural Similarity Index (SSIM) measures the perceptual quality difference between two images. 
        A score of 1.0 indicates identical images, while lower scores indicate quality degradation.</p>
        
        <p><strong>Performance Impact:</strong></p>
        <ul>
            <li>Time Cost: ~500ms per image (background process recommended)</li>
            <li>Storage: Minimal (scores stored as post meta)</li>
            <li>Benefit: Ensures no over-compressed images with visible quality loss</li>
        </ul>
    </div>
    <?php
}

/**
 * Add SSIM column to media library
 */
function timu_add_media_column($columns) {
    $columns['ssim_score'] = 'Quality Score';
    return $columns;
}
add_filter('manage_media_columns', 'timu_add_media_column');

/**
 * Display SSIM score in media library column
 */
function timu_display_media_column($column_name, $attachment_id) {
    if ($column_name === 'ssim_score') {
        $ssim_data = timu_get_image_ssim_score($attachment_id);
        
        if ($ssim_data && isset($ssim_data['ssim_score'])) {
            $score = $ssim_data['ssim_score'];
            $color = 'green';
            
            if ($score < 0.90) {
                $color = 'red';
            } elseif ($score < 0.95) {
                $color = 'orange';
            } elseif ($score < 0.99) {
                $color = '#666';
            }
            
            printf(
                '<span style="color: %s; font-weight: bold;">%.3f</span><br><small>%s</small>',
                esc_attr($color),
                esc_html($score),
                esc_html($ssim_data['quality_description'])
            );
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
add_action('manage_media_custom_column', 'timu_display_media_column', 10, 2);
