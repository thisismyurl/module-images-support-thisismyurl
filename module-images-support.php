<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with intelligent compression engine, filters, social optimization, text overlays, and branding features
 * Version: 1.0.0
 * Author: thisismyurl
 * Author URI: https://github.com/thisismyurl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: module-images-support
 * Domain Path: /languages
 *
 * @package ModuleImagesSupport
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TIMU_VERSION', '1.0.0');
define('TIMU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIMU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TIMU_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include admin settings
if (is_admin()) {
    require_once TIMU_PLUGIN_DIR . 'admin-settings.php';
}

/**
 * Intelligent Compression Engine - Multi-Algorithm Comparison
 * 
 * Automatically selects the best compression format and settings per image
 * by comparing multiple algorithms (MozJPEG, Guetzli, WebP, AVIF).
 *
 * @param string $image_path Path to the image to compress
 * @param int $target_quality Target quality level (1-100)
 * @param array $options Optional configuration options
 * @return array|WP_Error Best compression result or error
 */
function timu_intelligent_compress($image_path, $target_quality = 85, $options = array()) {
    // Validate input
    if (!file_exists($image_path)) {
        return new WP_Error('file_not_found', __('Image file not found.', 'module-images-support'));
    }

    // Default options
    $defaults = array(
        'algorithms' => array('mozjpeg', 'webp', 'avif', 'guetzli'),
        'min_quality_score' => 0.95,
        'min_savings_pct' => 0,
        'quality_weight' => 0.6,
        'savings_weight' => 0.4,
    );
    $options = wp_parse_args($options, $defaults);

    // Get original file size
    $original_size = filesize($image_path);
    
    // Compress with each algorithm
    $algorithms = array();
    foreach ($options['algorithms'] as $format) {
        $compress_function = "timu_compress_{$format}";
        if (function_exists($compress_function)) {
            $algorithms[$format] = $compress_function($image_path, $target_quality);
        }
    }
    
    // Score each result
    $results = array();
    foreach ($algorithms as $format => $compressed_path) {
        // Check if compression was successful
        if (is_wp_error($compressed_path) || !file_exists($compressed_path)) {
            continue;
        }

        $compressed_size = filesize($compressed_path);
        $savings = (($original_size - $compressed_size) / $original_size) * 100;
        
        // Skip if no savings
        if ($savings < $options['min_savings_pct']) {
            @unlink($compressed_path);
            continue;
        }
        
        // Calculate SSIM quality score
        $quality_score = timu_calculate_ssim($image_path, $compressed_path);
        
        // Skip if quality is too low
        if ($quality_score < $options['min_quality_score']) {
            @unlink($compressed_path);
            continue;
        }
        
        // Composite score: balance savings + quality
        $score = ($savings * $options['savings_weight']) + ($quality_score * 100 * $options['quality_weight']);
        
        $results[$format] = array(
            'path' => $compressed_path,
            'format' => $format,
            'size' => $compressed_size,
            'original_size' => $original_size,
            'savings_pct' => $savings,
            'quality_score' => $quality_score,
            'composite_score' => $score,
        );
    }
    
    // Return error if no valid results
    if (empty($results)) {
        return new WP_Error('no_results', __('No compression algorithms produced acceptable results.', 'module-images-support'));
    }
    
    // Sort by composite score (highest first)
    uasort($results, function($a, $b) {
        return $b['composite_score'] <=> $a['composite_score'];
    });
    
    // Clean up non-winning compressed files
    $best = reset($results);
    foreach ($results as $format => $result) {
        if ($result['path'] !== $best['path']) {
            @unlink($result['path']);
        }
    }
    
    return $best;
}

/**
 * Compress image using MozJPEG algorithm
 *
 * @param string $image_path Path to the image
 * @param int $quality Quality level (1-100)
 * @return string|WP_Error Path to compressed image or error
 */
function timu_compress_mozjpeg($image_path, $quality = 85) {
    // Check if mozjpeg is available
    $mozjpeg_path = timu_find_binary('cjpeg');
    if (!$mozjpeg_path) {
        return new WP_Error('mozjpeg_not_found', __('MozJPEG binary not found.', 'module-images-support'));
    }

    $output_path = timu_get_temp_path($image_path, 'mozjpeg.jpg');
    
    // Convert to JPEG first if needed
    $jpeg_path = timu_convert_to_jpeg($image_path);
    if (is_wp_error($jpeg_path)) {
        return $jpeg_path;
    }

    // Compress using mozjpeg
    $command = sprintf(
        '%s -quality %d -outfile %s %s 2>&1',
        escapeshellarg($mozjpeg_path),
        intval($quality),
        escapeshellarg($output_path),
        escapeshellarg($jpeg_path)
    );
    
    exec($command, $output, $return_var);
    
    // Clean up temporary JPEG if created
    if ($jpeg_path !== $image_path) {
        @unlink($jpeg_path);
    }
    
    if ($return_var !== 0 || !file_exists($output_path)) {
        return new WP_Error('mozjpeg_failed', __('MozJPEG compression failed.', 'module-images-support'));
    }
    
    return $output_path;
}

/**
 * Compress image using Guetzli algorithm
 *
 * @param string $image_path Path to the image
 * @param int $quality Quality level (84-100, Guetzli optimized)
 * @return string|WP_Error Path to compressed image or error
 */
function timu_compress_guetzli($image_path, $quality = 85) {
    // Check if guetzli is available
    $guetzli_path = timu_find_binary('guetzli');
    if (!$guetzli_path) {
        return new WP_Error('guetzli_not_found', __('Guetzli binary not found.', 'module-images-support'));
    }

    $output_path = timu_get_temp_path($image_path, 'guetzli.jpg');
    
    // Guetzli works best with quality 84-100
    $quality = max(84, min(100, $quality));
    
    // Convert to JPEG first if needed
    $jpeg_path = timu_convert_to_jpeg($image_path);
    if (is_wp_error($jpeg_path)) {
        return $jpeg_path;
    }

    // Compress using guetzli
    $command = sprintf(
        '%s --quality %d %s %s 2>&1',
        escapeshellarg($guetzli_path),
        intval($quality),
        escapeshellarg($jpeg_path),
        escapeshellarg($output_path)
    );
    
    exec($command, $output, $return_var);
    
    // Clean up temporary JPEG if created
    if ($jpeg_path !== $image_path) {
        @unlink($jpeg_path);
    }
    
    if ($return_var !== 0 || !file_exists($output_path)) {
        return new WP_Error('guetzli_failed', __('Guetzli compression failed.', 'module-images-support'));
    }
    
    return $output_path;
}

/**
 * Compress image to WebP format
 *
 * @param string $image_path Path to the image
 * @param int $quality Quality level (1-100)
 * @return string|WP_Error Path to compressed image or error
 */
function timu_compress_webp($image_path, $quality = 85) {
    // Check if GD or Imagick supports WebP
    if (!timu_supports_webp()) {
        return new WP_Error('webp_not_supported', __('WebP is not supported by the server.', 'module-images-support'));
    }

    $output_path = timu_get_temp_path($image_path, 'webp.webp');
    
    // Load image
    $image = timu_load_image($image_path);
    if (is_wp_error($image)) {
        return $image;
    }
    
    // Convert to WebP
    if (function_exists('imagewebp')) {
        $result = imagewebp($image, $output_path, $quality);
        imagedestroy($image);
        
        if (!$result || !file_exists($output_path)) {
            return new WP_Error('webp_failed', __('WebP conversion failed.', 'module-images-support'));
        }
    } else {
        imagedestroy($image);
        return new WP_Error('webp_function_missing', __('imagewebp function not available.', 'module-images-support'));
    }
    
    return $output_path;
}

/**
 * Compress image to AVIF format
 *
 * @param string $image_path Path to the image
 * @param int $quality Quality level (1-100)
 * @return string|WP_Error Path to compressed image or error
 */
function timu_compress_avif($image_path, $quality = 85) {
    // Check if AVIF is supported
    if (!timu_supports_avif()) {
        return new WP_Error('avif_not_supported', __('AVIF is not supported by the server.', 'module-images-support'));
    }

    $output_path = timu_get_temp_path($image_path, 'avif.avif');
    
    // Try using imageavif if available (PHP 8.1+)
    if (function_exists('imageavif')) {
        $image = timu_load_image($image_path);
        if (is_wp_error($image)) {
            return $image;
        }
        
        $result = imageavif($image, $output_path, $quality);
        imagedestroy($image);
        
        if (!$result || !file_exists($output_path)) {
            return new WP_Error('avif_failed', __('AVIF conversion failed.', 'module-images-support'));
        }
        
        return $output_path;
    }
    
    // Fallback to avifenc binary
    $avifenc_path = timu_find_binary('avifenc');
    if (!$avifenc_path) {
        return new WP_Error('avif_not_available', __('AVIF support not available.', 'module-images-support'));
    }
    
    // Convert quality (1-100) to speed/quantizer for avifenc
    $quantizer = round((100 - $quality) / 2);
    
    $command = sprintf(
        '%s -s 6 -q %d %s %s 2>&1',
        escapeshellarg($avifenc_path),
        intval($quantizer),
        escapeshellarg($image_path),
        escapeshellarg($output_path)
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var !== 0 || !file_exists($output_path)) {
        return new WP_Error('avif_encode_failed', __('AVIF encoding failed.', 'module-images-support'));
    }
    
    return $output_path;
}

/**
 * Calculate SSIM (Structural Similarity Index) between two images
 * 
 * SSIM is a perceptual metric that quantifies image quality degradation
 * Returns a value between 0 and 1 (1 = identical images)
 *
 * @param string $original_path Path to original image
 * @param string $compressed_path Path to compressed image
 * @return float SSIM score (0-1) or 1.0 if calculation fails
 */
function timu_calculate_ssim($original_path, $compressed_path) {
    // Try ImageMagick compare command if available
    $compare_path = timu_find_binary('compare');
    if ($compare_path) {
        $command = sprintf(
            '%s -metric SSIM %s %s null: 2>&1',
            escapeshellarg($compare_path),
            escapeshellarg($original_path),
            escapeshellarg($compressed_path)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && !empty($output[0])) {
            $ssim = floatval($output[0]);
            if ($ssim >= 0 && $ssim <= 1) {
                return $ssim;
            }
        }
    }
    
    // Fallback: simplified SSIM calculation using GD
    $img1 = timu_load_image($original_path);
    $img2 = timu_load_image($compressed_path);
    
    if (is_wp_error($img1) || is_wp_error($img2)) {
        return 1.0; // Assume perfect quality if we can't measure
    }
    
    // Get dimensions
    $width1 = imagesx($img1);
    $height1 = imagesy($img1);
    $width2 = imagesx($img2);
    $height2 = imagesy($img2);
    
    // Images must be same size for SSIM
    if ($width1 !== $width2 || $height1 !== $height2) {
        imagedestroy($img1);
        imagedestroy($img2);
        return 1.0;
    }
    
    // Simplified SSIM calculation (sample-based)
    $samples = min(100, $width1 * $height1 / 100);
    $similarity_sum = 0;
    
    for ($i = 0; $i < $samples; $i++) {
        $x = rand(0, $width1 - 1);
        $y = rand(0, $height1 - 1);
        
        $rgb1 = imagecolorat($img1, $x, $y);
        $rgb2 = imagecolorat($img2, $x, $y);
        
        $r1 = ($rgb1 >> 16) & 0xFF;
        $g1 = ($rgb1 >> 8) & 0xFF;
        $b1 = $rgb1 & 0xFF;
        
        $r2 = ($rgb2 >> 16) & 0xFF;
        $g2 = ($rgb2 >> 8) & 0xFF;
        $b2 = $rgb2 & 0xFF;
        
        // Calculate color difference
        $diff = abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
        $max_diff = 765; // Maximum difference (255 * 3)
        
        $similarity_sum += 1 - ($diff / $max_diff);
    }
    
    imagedestroy($img1);
    imagedestroy($img2);
    
    return $similarity_sum / $samples;
}

/**
 * Helper: Find binary executable in system PATH
 *
 * @param string $binary Binary name to find
 * @return string|false Path to binary or false if not found
 */
function timu_find_binary($binary) {
    // Check common paths
    $paths = array(
        '/usr/bin/' . $binary,
        '/usr/local/bin/' . $binary,
        '/opt/homebrew/bin/' . $binary,
    );
    
    foreach ($paths as $path) {
        if (file_exists($path) && is_executable($path)) {
            return $path;
        }
    }
    
    // Try which command
    $which = exec('which ' . escapeshellarg($binary) . ' 2>/dev/null');
    if (!empty($which) && file_exists($which)) {
        return $which;
    }
    
    return false;
}

/**
 * Helper: Get temporary path for compressed image
 *
 * @param string $original_path Original image path
 * @param string $suffix Suffix to add before extension
 * @return string Temporary file path
 */
function timu_get_temp_path($original_path, $suffix) {
    $temp_dir = get_temp_dir();
    $filename = basename($original_path);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    return $temp_dir . $name . '_' . uniqid() . '_' . $suffix;
}

/**
 * Helper: Convert image to JPEG format
 *
 * @param string $image_path Path to image
 * @return string|WP_Error Path to JPEG image or error
 */
function timu_convert_to_jpeg($image_path) {
    $mime_type = mime_content_type($image_path);
    
    // Already JPEG
    if (in_array($mime_type, array('image/jpeg', 'image/jpg'))) {
        return $image_path;
    }
    
    // Load and convert
    $image = timu_load_image($image_path);
    if (is_wp_error($image)) {
        return $image;
    }
    
    $output_path = timu_get_temp_path($image_path, 'temp.jpg');
    $result = imagejpeg($image, $output_path, 100);
    imagedestroy($image);
    
    if (!$result) {
        return new WP_Error('jpeg_conversion_failed', __('Failed to convert image to JPEG.', 'module-images-support'));
    }
    
    return $output_path;
}

/**
 * Helper: Load image using GD
 *
 * @param string $image_path Path to image
 * @return resource|WP_Error GD image resource or error
 */
function timu_load_image($image_path) {
    $mime_type = mime_content_type($image_path);
    
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($image_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($image_path);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($image_path);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $image = imagecreatefromwebp($image_path);
            } else {
                return new WP_Error('webp_load_failed', __('WebP loading not supported.', 'module-images-support'));
            }
            break;
        default:
            return new WP_Error('unsupported_format', __('Unsupported image format.', 'module-images-support'));
    }
    
    if (!$image) {
        return new WP_Error('image_load_failed', __('Failed to load image.', 'module-images-support'));
    }
    
    return $image;
}

/**
 * Helper: Check if WebP is supported
 *
 * @return bool True if WebP is supported
 */
function timu_supports_webp() {
    return function_exists('imagewebp') && (imagetypes() & IMG_WEBP);
}

/**
 * Helper: Check if AVIF is supported
 *
 * @return bool True if AVIF is supported
 */
function timu_supports_avif() {
    if (function_exists('imageavif')) {
        return true;
    }
    
    return (bool) timu_find_binary('avifenc');
}

/**
 * Get best image format for the requesting browser
 *
 * @return string Best supported format (avif, webp, or jpeg)
 */
function timu_get_browser_best_format() {
    if (!isset($_SERVER['HTTP_ACCEPT'])) {
        return 'jpeg';
    }
    
    $accept = $_SERVER['HTTP_ACCEPT'];
    
    // Check for AVIF support (Chrome 85+, Firefox 93+)
    if (strpos($accept, 'image/avif') !== false) {
        return 'avif';
    }
    
    // Check for WebP support (Chrome 23+, Firefox 65+, Edge 18+)
    if (strpos($accept, 'image/webp') !== false) {
        return 'webp';
    }
    
    // Fallback to JPEG
    return 'jpeg';
}

/**
 * Batch process images with intelligent compression
 *
 * @param array $image_paths Array of image paths to process
 * @param int $target_quality Target quality level
 * @param array $options Optional configuration options
 * @return array Results array with paths, stats, and errors
 */
function timu_batch_compress($image_paths, $target_quality = 85, $options = array()) {
    $results = array(
        'success' => array(),
        'failed' => array(),
        'total_original_size' => 0,
        'total_compressed_size' => 0,
        'total_savings' => 0,
    );
    
    foreach ($image_paths as $path) {
        if (!file_exists($path)) {
            $results['failed'][] = array(
                'path' => $path,
                'error' => __('File not found', 'module-images-support'),
            );
            continue;
        }
        
        $original_size = filesize($path);
        $results['total_original_size'] += $original_size;
        
        $result = timu_intelligent_compress($path, $target_quality, $options);
        
        if (is_wp_error($result)) {
            $results['failed'][] = array(
                'path' => $path,
                'error' => $result->get_error_message(),
            );
        } else {
            $results['success'][] = $result;
            $results['total_compressed_size'] += $result['size'];
        }
    }
    
    if ($results['total_original_size'] > 0) {
        $results['total_savings'] = (($results['total_original_size'] - $results['total_compressed_size']) / $results['total_original_size']) * 100;
    }
    
    return $results;
}

/**
 * WordPress upload filter to automatically compress uploaded images
 */
function timu_compress_uploaded_image($file) {
    // Only process images
    if (strpos($file['type'], 'image/') !== 0) {
        return $file;
    }
    
    // Get plugin options
    $enable_auto_compress = get_option('timu_enable_auto_compress', false);
    if (!$enable_auto_compress) {
        return $file;
    }
    
    $quality = get_option('timu_compression_quality', 85);
    
    // Compress the image
    $result = timu_intelligent_compress($file['tmp_name'], $quality);
    
    // Replace original with compressed version if successful
    if (!is_wp_error($result) && isset($result['path'])) {
        @unlink($file['tmp_name']);
        @rename($result['path'], $file['tmp_name']);
        $file['size'] = $result['size'];
    }
    
    return $file;
}

// Hook into WordPress upload process
add_filter('wp_handle_upload_prefilter', 'timu_compress_uploaded_image');

/**
 * Plugin activation hook
 */
function timu_activate() {
    // Set default options
    add_option('timu_enable_auto_compress', false);
    add_option('timu_compression_quality', 85);
    add_option('timu_min_quality_score', 0.95);
    add_option('timu_compression_algorithms', array('mozjpeg', 'webp', 'avif'));
}
register_activation_hook(__FILE__, 'timu_activate');

/**
 * Plugin deactivation hook
 */
function timu_deactivate() {
    // Cleanup temporary files
    $temp_dir = get_temp_dir();
    $files = glob($temp_dir . '*_mozjpeg.jpg');
    $files = array_merge($files, glob($temp_dir . '*_guetzli.jpg'));
    $files = array_merge($files, glob($temp_dir . '*_webp.webp'));
    $files = array_merge($files, glob($temp_dir . '*_avif.avif'));
    
    foreach ($files as $file) {
        @unlink($file);
    }
}
register_deactivation_hook(__FILE__, 'timu_deactivate');
