<?php
/**
 * Plugin Name: Module Images Support
 * Plugin URI: https://github.com/thisismyurl/module-images-support-thisismyurl
 * Description: WordPress image enhancement module with filters, social optimization, text overlays, branding features, and perspective correction
 * Version: 1.0.0
 * Author: thisismyurl
 * License: GPL2
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

/**
 * Detect horizon skew in an image
 * 
 * Analyzes an image to detect if the horizon line is tilted using edge detection
 * to find the longest horizontal line and measure its angle.
 * 
 * @param string $image_path Path to the image file
 * @return array Array containing 'skewed' boolean and 'angle' in degrees
 */
function timu_detect_horizon_skew($image_path) {
    // Validate image path
    if (!file_exists($image_path)) {
        return [
            'skewed' => false,
            'angle' => 0,
            'error' => 'Image file not found',
        ];
    }
    
    // Check if Imagick is available
    if (!extension_loaded('imagick')) {
        return [
            'skewed' => false,
            'angle' => 0,
            'error' => 'Imagick extension not available',
        ];
    }
    
    try {
        // Load image with Imagick
        $imagick = new Imagick($image_path);
        
        // Apply edge detection to find lines
        $imagick->edgeImage(1);
        
        // Analyze edge positions to detect tilt angle
        $tilt_angle = timu_measure_horizon_tilt($imagick);
        
        // Clean up
        $imagick->clear();
        $imagick->destroy();
        
        return [
            'skewed' => abs($tilt_angle) > 2, // >2 degrees = noticeable
            'angle' => $tilt_angle,
        ];
    } catch (Exception $e) {
        return [
            'skewed' => false,
            'angle' => 0,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Measure the tilt angle of the horizon in an edge-detected image
 * 
 * @param Imagick $imagick Edge-detected Imagick object
 * @return float Tilt angle in degrees (-180 to 180)
 */
function timu_measure_horizon_tilt($imagick) {
    // Clone the image to avoid modifying the original
    $edge_image = clone $imagick;
    
    try {
        // Convert to grayscale for analysis
        $edge_image->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        
        // Get image dimensions
        $width = $edge_image->getImageWidth();
        $height = $edge_image->getImageHeight();
        
        // Use Hough transform concept - analyze horizontal slices
        // Sample the middle third of the image where horizon typically appears
        $start_y = (int)($height * 0.33);
        $end_y = (int)($height * 0.67);
        $sample_height = $end_y - $start_y;
        
        // Analyze edge pixels to find dominant angle
        $angles = [];
        $step = max(1, (int)($height / 20)); // Sample every ~5% of height
        
        for ($y = $start_y; $y < $end_y; $y += $step) {
            // Get pixel intensity across this row
            $edge_points = [];
            for ($x = 0; $x < $width; $x += max(1, (int)($width / 100))) {
                $pixel = $edge_image->getImagePixelColor($x, $y);
                $colors = $pixel->getColor();
                $intensity = ($colors['r'] + $colors['g'] + $colors['b']) / 3;
                
                if ($intensity > 128) { // Edge detected
                    $edge_points[] = ['x' => $x, 'y' => $y];
                }
            }
            
            // If we found edge points, try to fit a line
            if (count($edge_points) > 2) {
                $angle = timu_calculate_line_angle($edge_points);
                if ($angle !== null) {
                    $angles[] = $angle;
                }
            }
        }
        
        // Clean up
        $edge_image->clear();
        $edge_image->destroy();
        
        // Return median angle to avoid outliers
        if (empty($angles)) {
            return 0;
        }
        
        sort($angles);
        $count = count($angles);
        $median_angle = $count % 2 == 0
            ? ($angles[$count / 2 - 1] + $angles[$count / 2]) / 2
            : $angles[(int)($count / 2)];
        
        return $median_angle;
    } catch (Exception $e) {
        // If analysis fails, assume no tilt
        return 0;
    }
}

/**
 * Calculate the angle of a line through given points
 * 
 * @param array $points Array of points with 'x' and 'y' keys
 * @return float|null Angle in degrees, or null if calculation fails
 */
function timu_calculate_line_angle($points) {
    if (count($points) < 2) {
        return null;
    }
    
    // Simple linear regression to find best fit line
    $n = count($points);
    $sum_x = 0;
    $sum_y = 0;
    $sum_xy = 0;
    $sum_x2 = 0;
    
    foreach ($points as $point) {
        $sum_x += $point['x'];
        $sum_y += $point['y'];
        $sum_xy += $point['x'] * $point['y'];
        $sum_x2 += $point['x'] * $point['x'];
    }
    
    $denominator = ($n * $sum_x2) - ($sum_x * $sum_x);
    
    if ($denominator == 0) {
        return 0;
    }
    
    // Calculate slope
    $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / $denominator;
    
    // Convert slope to angle in degrees
    $angle = rad2deg(atan($slope));
    
    return $angle;
}

/**
 * Straighten horizon by rotating image
 * 
 * Rotates an image to straighten the horizon line. Ignores angles smaller than 0.5 degrees.
 * 
 * @param string $image_path Path to the image file
 * @param float $angle Degrees to rotate (-180 to 180)
 * @return Imagick|string Imagick object with straightened image, or original path if no rotation needed
 */
function timu_straighten_horizon($image_path, $angle) {
    // Too small to bother
    if (abs($angle) < 0.5) {
        return $image_path;
    }
    
    // Check if Imagick is available
    if (!extension_loaded('imagick')) {
        return $image_path;
    }
    
    try {
        $imagick = new Imagick($image_path);
        
        // Rotate with white background (crop later)
        $imagick->rotateImage(new ImagickPixel('white'), -$angle);
        
        // Auto-crop to remove white borders
        $imagick->trimImage(0);
        $imagick->setImagePage(0, 0, 0, 0); // Reset virtual canvas
        
        return $imagick;
    } catch (Exception $e) {
        // Return original path if rotation fails
        return $image_path;
    }
}

/**
 * Correct perspective distortion (keystoning)
 * 
 * Applies perspective transformation to correct keystoning by remapping the corners
 * of a trapezoid to a rectangle.
 * 
 * @param string $image_path Path to the image file
 * @param array $trapezoid_points Array of corner coordinates:
 *              [
 *                  'top_left' => [x, y],
 *                  'top_right' => [x, y],
 *                  'bottom_left' => [x, y],
 *                  'bottom_right' => [x, y],
 *              ]
 * @return Imagick|string Imagick object with corrected perspective, or original path if correction fails
 */
function timu_correct_perspective($image_path, $trapezoid_points) {
    // Validate input
    if (!file_exists($image_path)) {
        return $image_path;
    }
    
    // Check if Imagick is available
    if (!extension_loaded('imagick')) {
        return $image_path;
    }
    
    // Validate trapezoid points
    $required_keys = ['top_left', 'top_right', 'bottom_left', 'bottom_right'];
    foreach ($required_keys as $key) {
        if (!isset($trapezoid_points[$key]) || count($trapezoid_points[$key]) != 2) {
            return $image_path;
        }
    }
    
    try {
        $imagick = new Imagick($image_path);
        
        // Get destination dimensions (will be a rectangle)
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();
        
        // Build perspective distortion arguments
        // Format: source_x, source_y, dest_x, dest_y (for each corner)
        $arguments = [
            // Top-left corner: from trapezoid to rectangle corner
            $trapezoid_points['top_left'][0], $trapezoid_points['top_left'][1],
            0, 0,
            
            // Top-right corner
            $trapezoid_points['top_right'][0], $trapezoid_points['top_right'][1],
            $width, 0,
            
            // Bottom-left corner
            $trapezoid_points['bottom_left'][0], $trapezoid_points['bottom_left'][1],
            0, $height,
            
            // Bottom-right corner
            $trapezoid_points['bottom_right'][0], $trapezoid_points['bottom_right'][1],
            $width, $height,
        ];
        
        // Apply perspective distortion
        $imagick->distortImage(Imagick::DISTORTION_PERSPECTIVE, $arguments, true);
        
        return $imagick;
    } catch (Exception $e) {
        // Return original path if perspective correction fails
        return $image_path;
    }
}

/**
 * Auto-detect and apply perspective corrections
 * 
 * Convenience function that detects if an image needs straightening and applies it.
 * 
 * @param string $image_path Path to the image file
 * @param array $options Optional parameters:
 *                       - 'threshold': Minimum angle in degrees to trigger correction (default: 2)
 *                       - 'save_path': Path to save corrected image (default: overwrites original)
 * @return array Result with 'success', 'angle', 'corrected', and optional 'path' keys
 */
function timu_auto_correct_perspective($image_path, $options = []) {
    $threshold = isset($options['threshold']) ? $options['threshold'] : 2;
    $save_path = isset($options['save_path']) ? $options['save_path'] : $image_path;
    
    // Detect horizon skew
    $detection = timu_detect_horizon_skew($image_path);
    
    if (isset($detection['error'])) {
        return [
            'success' => false,
            'error' => $detection['error'],
            'corrected' => false,
        ];
    }
    
    $result = [
        'success' => true,
        'angle' => $detection['angle'],
        'corrected' => false,
    ];
    
    // Apply correction if needed
    if ($detection['skewed'] && abs($detection['angle']) >= $threshold) {
        $straightened = timu_straighten_horizon($image_path, $detection['angle']);
        
        if ($straightened instanceof Imagick) {
            try {
                $straightened->writeImage($save_path);
                $straightened->clear();
                $straightened->destroy();
                
                $result['corrected'] = true;
                $result['path'] = $save_path;
            } catch (Exception $e) {
                $result['error'] = 'Failed to save corrected image: ' . $e->getMessage();
            }
        }
    }
    
    return $result;
}

// Initialize plugin
add_action('plugins_loaded', 'timu_init');

/**
 * Initialize the plugin
 */
function timu_init() {
    // Check for required PHP extensions
    if (!extension_loaded('imagick')) {
        add_action('admin_notices', 'timu_imagick_missing_notice');
    }
}

/**
 * Display admin notice if Imagick is not available
 */
function timu_imagick_missing_notice() {
    ?>
    <div class="notice notice-warning">
        <p><strong>Module Images Support:</strong> The Imagick PHP extension is not installed. Some features including perspective correction will not be available.</p>
    </div>
    <?php
}

// Register activation hook
register_activation_hook(__FILE__, 'timu_activate');

/**
 * Plugin activation hook
 */
function timu_activate() {
    // Set default options
    add_option('timu_perspective_threshold', 2);
    add_option('timu_auto_straighten', false);
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'timu_deactivate');

/**
 * Plugin deactivation hook
 */
function timu_deactivate() {
    // Cleanup if needed
}
