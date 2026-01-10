<?php
/**
 * Example Usage and Testing Script
 * 
 * This file demonstrates how to use the Intelligent Compression Engine
 * 
 * @package ModuleImagesSupport
 */

// This is an example file - not meant to be executed directly in production
// It shows how to use the plugin's API functions

/**
 * Example 1: Compress a single image
 */
function example_compress_single_image() {
    $image_path = '/path/to/your/image.jpg';
    $target_quality = 85;
    
    $result = timu_intelligent_compress($image_path, $target_quality);
    
    if (is_wp_error($result)) {
        echo "Error: " . $result->get_error_message() . "\n";
        return;
    }
    
    echo "Compression Results:\n";
    echo "-------------------\n";
    echo "Format: " . $result['format'] . "\n";
    echo "Original Size: " . number_format($result['original_size']) . " bytes\n";
    echo "Compressed Size: " . number_format($result['size']) . " bytes\n";
    echo "Savings: " . round($result['savings_pct'], 2) . "%\n";
    echo "Quality Score (SSIM): " . round($result['quality_score'], 4) . "\n";
    echo "Composite Score: " . round($result['composite_score'], 2) . "\n";
    echo "Output Path: " . $result['path'] . "\n";
}

/**
 * Example 2: Compress with custom options
 */
function example_compress_with_options() {
    $image_path = '/path/to/your/image.jpg';
    
    $options = array(
        'algorithms' => array('mozjpeg', 'webp', 'avif'), // Only compare these formats
        'min_quality_score' => 0.95,                      // Require 95% similarity
        'min_savings_pct' => 10,                          // Require at least 10% savings
        'quality_weight' => 0.7,                          // Prioritize quality (70%)
        'savings_weight' => 0.3,                          // File size (30%)
    );
    
    $result = timu_intelligent_compress($image_path, 90, $options);
    
    if (!is_wp_error($result)) {
        echo "Best format: " . $result['format'] . "\n";
    }
}

/**
 * Example 3: Batch compress multiple images
 */
function example_batch_compress() {
    $image_paths = array(
        '/path/to/image1.jpg',
        '/path/to/image2.png',
        '/path/to/image3.jpg',
    );
    
    $results = timu_batch_compress($image_paths, 85);
    
    echo "Batch Compression Results:\n";
    echo "-------------------------\n";
    echo "Total Original Size: " . number_format($results['total_original_size']) . " bytes\n";
    echo "Total Compressed Size: " . number_format($results['total_compressed_size']) . " bytes\n";
    echo "Total Savings: " . round($results['total_savings'], 2) . "%\n";
    echo "Successful: " . count($results['success']) . "\n";
    echo "Failed: " . count($results['failed']) . "\n";
    
    // Show details for each successful compression
    foreach ($results['success'] as $result) {
        echo "\n" . basename($result['path']) . ":\n";
        echo "  Format: " . $result['format'] . "\n";
        echo "  Savings: " . round($result['savings_pct'], 2) . "%\n";
        echo "  Quality: " . round($result['quality_score'], 4) . "\n";
    }
    
    // Show errors for failed compressions
    foreach ($results['failed'] as $failed) {
        echo "\nFailed: " . $failed['path'] . "\n";
        echo "  Error: " . $failed['error'] . "\n";
    }
}

/**
 * Example 4: Check browser support and serve appropriate format
 */
function example_browser_format_negotiation() {
    $best_format = timu_get_browser_best_format();
    
    echo "Best format for requesting browser: " . $best_format . "\n";
    
    // You can then serve the appropriate image format
    // based on what the browser supports
    switch ($best_format) {
        case 'avif':
            echo "Serving AVIF image (best compression)\n";
            break;
        case 'webp':
            echo "Serving WebP image (good compression)\n";
            break;
        default:
            echo "Serving JPEG image (fallback)\n";
            break;
    }
}

/**
 * Example 5: Test individual compression algorithms
 */
function example_test_individual_algorithms() {
    $image_path = '/path/to/your/image.jpg';
    $quality = 85;
    
    echo "Testing Individual Algorithms:\n";
    echo "-----------------------------\n";
    
    // Test MozJPEG
    $mozjpeg_result = timu_compress_mozjpeg($image_path, $quality);
    if (!is_wp_error($mozjpeg_result)) {
        $size = filesize($mozjpeg_result);
        echo "MozJPEG: " . number_format($size) . " bytes\n";
        @unlink($mozjpeg_result);
    } else {
        echo "MozJPEG: " . $mozjpeg_result->get_error_message() . "\n";
    }
    
    // Test WebP
    $webp_result = timu_compress_webp($image_path, $quality);
    if (!is_wp_error($webp_result)) {
        $size = filesize($webp_result);
        echo "WebP: " . number_format($size) . " bytes\n";
        @unlink($webp_result);
    } else {
        echo "WebP: " . $webp_result->get_error_message() . "\n";
    }
    
    // Test AVIF
    $avif_result = timu_compress_avif($image_path, $quality);
    if (!is_wp_error($avif_result)) {
        $size = filesize($avif_result);
        echo "AVIF: " . number_format($size) . " bytes\n";
        @unlink($avif_result);
    } else {
        echo "AVIF: " . $avif_result->get_error_message() . "\n";
    }
    
    // Test Guetzli
    $guetzli_result = timu_compress_guetzli($image_path, $quality);
    if (!is_wp_error($guetzli_result)) {
        $size = filesize($guetzli_result);
        echo "Guetzli: " . number_format($size) . " bytes\n";
        @unlink($guetzli_result);
    } else {
        echo "Guetzli: " . $guetzli_result->get_error_message() . "\n";
    }
}

/**
 * Example 6: Calculate SSIM between two images
 */
function example_calculate_ssim() {
    $original = '/path/to/original.jpg';
    $compressed = '/path/to/compressed.jpg';
    
    $ssim = timu_calculate_ssim($original, $compressed);
    
    echo "SSIM Score: " . round($ssim, 4) . "\n";
    
    if ($ssim >= 0.95) {
        echo "Quality: Excellent (≥ 0.95)\n";
    } elseif ($ssim >= 0.90) {
        echo "Quality: Good (≥ 0.90)\n";
    } elseif ($ssim >= 0.80) {
        echo "Quality: Acceptable (≥ 0.80)\n";
    } else {
        echo "Quality: Poor (< 0.80)\n";
    }
}

/**
 * Example 7: Check available compression algorithms
 */
function example_check_available_algorithms() {
    echo "Checking Available Compression Algorithms:\n";
    echo "-----------------------------------------\n";
    
    // Check MozJPEG
    $mozjpeg = timu_find_binary('cjpeg');
    echo "MozJPEG (cjpeg): " . ($mozjpeg ? "✓ Available at $mozjpeg" : "✗ Not found") . "\n";
    
    // Check Guetzli
    $guetzli = timu_find_binary('guetzli');
    echo "Guetzli: " . ($guetzli ? "✓ Available at $guetzli" : "✗ Not found") . "\n";
    
    // Check WebP support
    $webp = timu_supports_webp();
    echo "WebP: " . ($webp ? "✓ Supported" : "✗ Not supported") . "\n";
    
    // Check AVIF support
    $avif = timu_supports_avif();
    echo "AVIF: " . ($avif ? "✓ Supported" : "✗ Not supported") . "\n";
    
    // Check ImageMagick compare
    $compare = timu_find_binary('compare');
    echo "ImageMagick compare: " . ($compare ? "✓ Available at $compare" : "✗ Not found") . "\n";
    
    // Get available algorithms
    if (function_exists('timu_get_available_algorithms')) {
        $available = timu_get_available_algorithms();
        echo "\nAvailable algorithms for compression: " . implode(', ', $available) . "\n";
    }
}

/**
 * Example 8: WordPress integration - automatic compression on upload
 */
function example_wordpress_integration() {
    // This filter is automatically added by the plugin
    // You can customize it or add your own filters
    
    add_filter('wp_handle_upload_prefilter', function($file) {
        // Check if auto-compression is enabled
        $enable = get_option('timu_enable_auto_compress', false);
        
        if (!$enable || strpos($file['type'], 'image/') !== 0) {
            return $file;
        }
        
        echo "Compressing uploaded image: " . $file['name'] . "\n";
        
        $quality = get_option('timu_compression_quality', 85);
        $result = timu_intelligent_compress($file['tmp_name'], $quality);
        
        if (!is_wp_error($result)) {
            echo "Success! Compressed with " . $result['format'] . "\n";
            echo "Savings: " . round($result['savings_pct'], 2) . "%\n";
        }
        
        return $file;
    }, 999); // Higher priority to run after plugin's default filter
}

// Note: These are example functions demonstrating the API
// They should be called in an appropriate context (e.g., admin page, cron job, etc.)
