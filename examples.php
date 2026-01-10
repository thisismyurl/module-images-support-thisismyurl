<?php
/**
 * Safe Zone Indicators - Example Usage
 * 
 * This file demonstrates how to use the safe zone indicator functions
 * to visualize safe zones for different devices and platforms.
 */

// Load the main plugin file
require_once __DIR__ . '/module-images-support.php';

/**
 * Example 1: Generate a test image with safe zone overlay
 */
function example_generate_image_with_safe_zone() {
    // Create a sample image (800x600)
    $width = 800;
    $height = 600;
    $image = imagecreatetruecolor($width, $height);
    
    // Fill with a gradient background
    for ($y = 0; $y < $height; $y++) {
        $color = imagecolorallocate($image, 
            (int)(100 + ($y / $height) * 155), 
            (int)(150 - ($y / $height) * 50), 
            (int)(200 - ($y / $height) * 100)
        );
        imageline($image, 0, $y, $width, $y, $color);
    }
    
    // Add some text to show content placement
    $text_color = imagecolorallocate($image, 255, 255, 255);
    $text = "Sample Image Content";
    imagestring($image, 5, ($width / 2) - 80, $height / 2, $text, $text_color);
    
    // Apply safe zone overlay for mobile
    timu_apply_safe_zone_overlay($image, 'mobile');
    
    // Save the image
    $output_dir = __DIR__ . '/examples';
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0755, true);
    }
    
    imagepng($image, $output_dir . '/mobile-safe-zone.png');
    imagedestroy($image);
    
    echo "Generated: examples/mobile-safe-zone.png\n";
}

/**
 * Example 2: Generate overlays for all device contexts
 */
function example_generate_all_contexts() {
    $width = 800;
    $height = 600;
    $contexts = timu_get_available_contexts();
    
    $output_dir = __DIR__ . '/examples';
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0755, true);
    }
    
    foreach ($contexts as $context) {
        // Create base image
        $image = imagecreatetruecolor($width, $height);
        
        // Blue gradient background
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorallocate($image, 
                (int)(50 + ($y / $height) * 100), 
                (int)(100 + ($y / $height) * 100), 
                (int)(200)
            );
            imageline($image, 0, $y, $width, $y, $color);
        }
        
        // Add context label
        $white = imagecolorallocate($image, 255, 255, 255);
        $label = strtoupper(str_replace('_', ' ', $context));
        imagestring($image, 5, 20, 20, $label, $white);
        
        // Apply safe zone overlay
        timu_apply_safe_zone_overlay($image, $context);
        
        // Save
        $filename = $output_dir . '/' . $context . '-safe-zone.png';
        imagepng($image, $filename);
        imagedestroy($image);
        
        echo "Generated: examples/" . $context . "-safe-zone.png\n";
    }
}

/**
 * Example 3: Get safe zone information
 */
function example_print_safe_zone_info() {
    $width = 1920;
    $height = 1080;
    $contexts = timu_get_available_contexts();
    
    echo "\nSafe Zone Information for {$width}x{$height} image:\n";
    echo str_repeat("=", 70) . "\n\n";
    
    foreach ($contexts as $context) {
        $safe_zone = timu_get_safe_zones($width, $height, $context);
        
        echo strtoupper($context) . ":\n";
        echo "  Position: (" . round($safe_zone['x']) . ", " . round($safe_zone['y']) . ")\n";
        echo "  Size: " . round($safe_zone['width']) . "x" . round($safe_zone['height']) . "\n";
        echo "  Safe Area: " . round(($safe_zone['width'] * $safe_zone['height']) / ($width * $height) * 100, 1) . "%\n";
        echo "\n";
    }
}

/**
 * Example 4: Test point in safe zone
 */
function example_test_point_in_safe_zone() {
    $width = 800;
    $height = 600;
    
    // Test points
    $test_points = [
        ['x' => 400, 'y' => 300, 'label' => 'Center'],
        ['x' => 50, 'y' => 50, 'label' => 'Top-Left Corner'],
        ['x' => 750, 'y' => 550, 'label' => 'Bottom-Right Corner'],
        ['x' => 400, 'y' => 30, 'label' => 'Top Notch Area'],
    ];
    
    echo "\nTesting points in safe zones ({$width}x{$height}):\n";
    echo str_repeat("=", 70) . "\n\n";
    
    foreach (timu_get_available_contexts() as $context) {
        echo strtoupper($context) . ":\n";
        foreach ($test_points as $point) {
            $is_safe = timu_is_point_in_safe_zone(
                $point['x'], 
                $point['y'], 
                $width, 
                $height, 
                $context
            );
            $status = $is_safe ? "✓ SAFE" : "✗ UNSAFE";
            echo "  {$point['label']} ({$point['x']}, {$point['y']}): {$status}\n";
        }
        echo "\n";
    }
}

// Run examples if this file is executed directly
if (php_sapi_name() === 'cli') {
    echo "Safe Zone Indicators - Example Usage\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Check if GD is available
    if (!extension_loaded('gd')) {
        echo "Error: GD extension is not loaded. Please install php-gd.\n";
        exit(1);
    }
    
    echo "Running examples...\n\n";
    
    // Run all examples
    example_print_safe_zone_info();
    example_test_point_in_safe_zone();
    example_generate_image_with_safe_zone();
    example_generate_all_contexts();
    
    echo "\nAll examples completed successfully!\n";
    echo "Check the 'examples/' directory for generated images.\n";
}
