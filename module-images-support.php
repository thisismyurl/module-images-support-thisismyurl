<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and safe zone indicators
 * Version: 1.0.0
 * Author: thisismyurl
 * Author URI: https://github.com/thisismyurl
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: module-images-support
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Get safe zones for different device contexts
 * 
 * Returns safe zone dimensions and positions for various device types
 * to help with cropping and content placement.
 *
 * @param int    $image_width  Width of the image in pixels
 * @param int    $image_height Height of the image in pixels
 * @param string $context      Device/platform context (mobile, iphone_x, tv_overscan, instagram)
 * @return array Array containing x, y, width, height of the safe zone
 */
function timu_get_safe_zones($image_width, $image_height, $context = 'mobile') {
    // Safe zones for different contexts
    $safe_zones = [
        'mobile' => [
            'notch_top' => 0.08, // 8% for notch
            'safe_area_inset' => 0.05, // 5% margin
        ],
        'iphone_x' => [
            'notch_top' => 0.12,
            'safe_area_inset' => 0.04,
        ],
        'tv_overscan' => [
            'overscan_left' => 0.05,
            'overscan_right' => 0.05,
            'overscan_top' => 0.05,
            'overscan_bottom' => 0.05,
        ],
        'instagram' => [
            'safe_width' => 0.9,
            'safe_height' => 0.9,
            'center_align' => true,
        ],
        'facebook' => [
            'safe_width' => 0.92,
            'safe_height' => 0.92,
            'center_align' => true,
        ],
    ];
    
    // Default to mobile if context not found
    if (!isset($safe_zones[$context])) {
        $context = 'mobile';
    }
    
    $zone = $safe_zones[$context];
    
    // Calculate safe zone dimensions based on context type
    if (isset($zone['center_align']) && $zone['center_align']) {
        // Center-aligned safe zones (Instagram, Facebook)
        $safe_width = $image_width * ($zone['safe_width'] ?? 0.9);
        $safe_height = $image_height * ($zone['safe_height'] ?? 0.9);
        
        return [
            'x' => ($image_width - $safe_width) / 2,
            'y' => ($image_height - $safe_height) / 2,
            'width' => $safe_width,
            'height' => $safe_height,
        ];
    } elseif (isset($zone['overscan_left'])) {
        // TV overscan zones
        $left = $image_width * ($zone['overscan_left'] ?? 0);
        $right = $image_width * ($zone['overscan_right'] ?? 0);
        $top = $image_height * ($zone['overscan_top'] ?? 0);
        $bottom = $image_height * ($zone['overscan_bottom'] ?? 0);
        
        return [
            'x' => $left,
            'y' => $top,
            'width' => $image_width - $left - $right,
            'height' => $image_height - $top - $bottom,
        ];
    } else {
        // Mobile notch zones
        $inset = $zone['safe_area_inset'] ?? 0;
        $notch_top = $zone['notch_top'] ?? 0;
        
        return [
            'x' => $image_width * $inset,
            'y' => $image_height * $notch_top,
            'width' => $image_width * (1 - 2 * $inset),
            'height' => $image_height * (1 - $notch_top - $inset),
        ];
    }
}

/**
 * Render safe zone overlay on an image
 * 
 * Creates a visual overlay showing safe zones with darkened unsafe areas
 * and a green border around the safe zone.
 *
 * @param int    $image_width  Width of the overlay in pixels
 * @param int    $image_height Height of the overlay in pixels
 * @param string $context      Device/platform context
 * @return resource|GdImage    GD image resource with the overlay
 */
function timu_render_safe_zone_overlay($image_width, $image_height, $context = 'mobile') {
    // Create canvas with transparency support
    $canvas = imagecreatetruecolor($image_width, $image_height);
    imagesavealpha($canvas, true);
    imagealphablending($canvas, false);
    
    // Fill with transparent background first
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    
    // Enable alpha blending for drawing
    imagealphablending($canvas, true);
    
    // Outer dark area (unsafe) - semi-transparent black
    $unsafe_color = imagecolorallocatealpha($canvas, 0, 0, 0, 100);
    imagefilledrectangle($canvas, 0, 0, $image_width - 1, $image_height - 1, $unsafe_color);
    
    // Get safe zone coordinates
    $safe_zone = timu_get_safe_zones($image_width, $image_height, $context);
    
    // Clear the safe zone area (make it transparent)
    imagealphablending($canvas, false);
    $clear_color = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefilledrectangle($canvas,
        (int)$safe_zone['x'], 
        (int)$safe_zone['y'],
        (int)($safe_zone['x'] + $safe_zone['width']),
        (int)($safe_zone['y'] + $safe_zone['height']),
        $clear_color
    );
    
    // Enable alpha blending for border
    imagealphablending($canvas, true);
    
    // Border indicating safe zone - green with some transparency
    $border_color = imagecolorallocatealpha($canvas, 0, 255, 0, 30);
    imagesetthickness($canvas, 3);
    imagerectangle($canvas,
        (int)$safe_zone['x'], 
        (int)$safe_zone['y'],
        (int)($safe_zone['x'] + $safe_zone['width']),
        (int)($safe_zone['y'] + $safe_zone['height']),
        $border_color
    );
    
    return $canvas;
}

/**
 * Apply safe zone overlay to an existing image
 * 
 * Merges the safe zone overlay with an existing image resource.
 *
 * @param resource|GdImage $image   The source image
 * @param string          $context Device/platform context
 * @return resource|GdImage Modified image with overlay
 */
function timu_apply_safe_zone_overlay($image, $context = 'mobile') {
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create overlay
    $overlay = timu_render_safe_zone_overlay($width, $height, $context);
    
    // Merge overlay with image
    imagecopy($image, $overlay, 0, 0, 0, 0, $width, $height);
    
    // Clean up
    imagedestroy($overlay);
    
    return $image;
}

/**
 * Get list of available safe zone contexts
 *
 * @return array List of available context names
 */
function timu_get_available_contexts() {
    return ['mobile', 'iphone_x', 'tv_overscan', 'instagram', 'facebook'];
}

/**
 * Check if a point is within the safe zone
 *
 * @param int    $x            X coordinate to check
 * @param int    $y            Y coordinate to check
 * @param int    $image_width  Image width
 * @param int    $image_height Image height
 * @param string $context      Device/platform context
 * @return bool True if point is in safe zone, false otherwise
 */
function timu_is_point_in_safe_zone($x, $y, $image_width, $image_height, $context = 'mobile') {
    $safe_zone = timu_get_safe_zones($image_width, $image_height, $context);
    
    return $x >= $safe_zone['x'] && 
           $x <= ($safe_zone['x'] + $safe_zone['width']) &&
           $y >= $safe_zone['y'] && 
           $y <= ($safe_zone['y'] + $safe_zone['height']);
}
