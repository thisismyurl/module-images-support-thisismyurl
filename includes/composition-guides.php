<?php
/**
 * Composition Guides Functions
 * 
 * Provides Rule of Thirds and Golden Ratio grid generation
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Rule of Thirds grid overlay
 * 
 * @param int $image_width Width of the image
 * @param int $image_height Height of the image
 * @return resource|GdImage GD image resource with grid overlay
 */
function timu_render_rule_of_thirds_grid($image_width, $image_height) {
    // Create transparent canvas
    $canvas = imagecreatetruecolor($image_width, $image_height);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    
    // Semi-transparent white color for grid lines
    $color = imagecolorallocatealpha($canvas, 255, 255, 255, 50);
    $thickness = 2;
    imagesetthickness($canvas, $thickness);
    
    // Vertical lines (divide into thirds)
    $x1 = (int)($image_width / 3);
    $x2 = (int)(2 * $image_width / 3);
    imageline($canvas, $x1, 0, $x1, $image_height, $color);
    imageline($canvas, $x2, 0, $x2, $image_height, $color);
    
    // Horizontal lines (divide into thirds)
    $y1 = (int)($image_height / 3);
    $y2 = (int)(2 * $image_height / 3);
    imageline($canvas, 0, $y1, $image_width, $y1, $color);
    imageline($canvas, 0, $y2, $image_width, $y2, $color);
    
    return $canvas;
}

/**
 * Render Golden Ratio spiral overlay
 * 
 * @param int $image_width Width of the image
 * @param int $image_height Height of the image
 * @return resource|GdImage GD image resource with spiral overlay
 */
function timu_render_golden_ratio_spiral($image_width, $image_height) {
    // Create transparent canvas
    $canvas = imagecreatetruecolor($image_width, $image_height);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    
    // Gold color with transparency
    $color = imagecolorallocatealpha($canvas, 255, 200, 0, 80);
    
    // Golden ratio (phi)
    $phi = (1 + sqrt(5)) / 2;
    
    // Calculate center point
    $center_x = $image_width / 2;
    $center_y = $image_height / 2;
    
    // Generate spiral points
    $points = array();
    $angle = 0;
    $max_iterations = 100;
    
    for ($i = 0; $i < $max_iterations; $i++) {
        $radius = ($image_width / 4) * exp($angle / (2 * M_PI));
        $points[] = array(
            'x' => $center_x + $radius * cos($angle),
            'y' => $center_y + $radius * sin($angle),
        );
        $angle += 0.1;
    }
    
    // Draw spiral as connected line segments
    imagesetthickness($canvas, 2);
    for ($i = 0; $i < count($points) - 1; $i++) {
        imageline(
            $canvas,
            (int)$points[$i]['x'],
            (int)$points[$i]['y'],
            (int)$points[$i + 1]['x'],
            (int)$points[$i + 1]['y'],
            $color
        );
    }
    
    return $canvas;
}

/**
 * Generate grid overlay as base64 PNG data
 * 
 * @param string $grid_type Type of grid ('rule-of-thirds' or 'golden-ratio')
 * @param int $width Image width
 * @param int $height Image height
 * @return string Base64 encoded PNG image data
 */
function timu_generate_grid_overlay($grid_type, $width, $height) {
    if ($grid_type === 'rule-of-thirds') {
        $canvas = timu_render_rule_of_thirds_grid($width, $height);
    } elseif ($grid_type === 'golden-ratio') {
        $canvas = timu_render_golden_ratio_spiral($width, $height);
    } else {
        return '';
    }
    
    // Capture image as PNG in memory
    ob_start();
    imagepng($canvas);
    $image_data = ob_get_clean();
    imagedestroy($canvas);
    
    // Return base64 encoded data
    return 'data:image/png;base64,' . base64_encode($image_data);
}
