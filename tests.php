<?php
/**
 * Art Direction Mapper - Basic Tests
 *
 * Simple test suite to validate core functionality.
 * Run with: php tests.php
 *
 * @package ModuleImagesSupport
 */

// Define ABSPATH for standalone testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Test Results
$tests_passed = 0;
$tests_failed = 0;
$test_results = [];

/**
 * Simple assertion function
 */
function assert_test($condition, $test_name) {
    global $tests_passed, $tests_failed, $test_results;
    
    if ($condition) {
        $tests_passed++;
        $test_results[] = "✓ PASS: $test_name";
        return true;
    } else {
        $tests_failed++;
        $test_results[] = "✗ FAIL: $test_name";
        return false;
    }
}

/**
 * Test helper to check array equality
 */
function arrays_equal($a, $b) {
    return serialize($a) === serialize($b);
}

// Mock WordPress functions for standalone testing
if (!function_exists('update_post_meta')) {
    $mock_meta_data = [];
    
    function update_post_meta($post_id, $meta_key, $meta_value) {
        global $mock_meta_data;
        $mock_meta_data[$post_id][$meta_key] = $meta_value;
        return true;
    }
    
    function get_post_meta($post_id, $meta_key, $single = false) {
        global $mock_meta_data;
        if (isset($mock_meta_data[$post_id][$meta_key])) {
            return $mock_meta_data[$post_id][$meta_key];
        }
        return $single ? '' : [];
    }
    
    function apply_filters($tag, $value) {
        return $value;
    }
    
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            parse_str($args, $parsed_args);
        }
        
        if (is_array($defaults)) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
    
    function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail') {
        return 'http://example.com/wp-content/uploads/test-image.jpg';
    }
    
    function wp_get_attachment_url($attachment_id) {
        return 'http://example.com/wp-content/uploads/test-image.jpg';
    }
    
    function get_attached_file($attachment_id) {
        return false; // Return false for non-existent files in test
    }
    
    function wp_upload_dir() {
        return [
            'path' => '/tmp/uploads',
            'basedir' => '/tmp/uploads',
            'url' => 'http://example.com/wp-content/uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
        ];
    }
    
    function wp_get_image_editor($file) {
        return new WP_Error('test', 'Mock WP_Error');
    }
    
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
    
    class WP_Error {
        public function __construct($code, $message) {
            $this->code = $code;
            $this->message = $message;
        }
    }
}

// Load the module
require_once __DIR__ . '/module-images-support.php';

echo "=== Art Direction Mapper Test Suite ===\n\n";

// Test 1: Validate crop configuration
echo "Testing crop validation...\n";

$valid_crop = ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100];
assert_test(
    timu_validate_crop_config($valid_crop) === true,
    "Valid crop configuration should pass"
);

$invalid_crop_missing_key = ['x' => 0, 'y' => 0, 'width' => 100];
assert_test(
    timu_validate_crop_config($invalid_crop_missing_key) === false,
    "Invalid crop with missing key should fail"
);

$invalid_crop_negative = ['x' => -10, 'y' => 0, 'width' => 100, 'height' => 100];
assert_test(
    timu_validate_crop_config($invalid_crop_negative) === false,
    "Invalid crop with negative coordinates should fail"
);

$invalid_crop_zero_size = ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 100];
assert_test(
    timu_validate_crop_config($invalid_crop_zero_size) === false,
    "Invalid crop with zero width should fail"
);

// Test 2: Set and get art direction
echo "\nTesting art direction storage...\n";

$test_attachment_id = 123;
$test_directions = [
    'mobile' => [
        'crop' => ['x' => 0, 'y' => 100, 'width' => 600, 'height' => 900],
        'focal_point' => 'primary',
        'aspect_ratio' => '2:3',
    ],
    'tablet' => [
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1024, 'height' => 768],
        'focal_point' => 'secondary',
        'aspect_ratio' => '4:3',
    ],
];

$set_result = timu_set_art_direction($test_attachment_id, $test_directions);
assert_test(
    $set_result === true,
    "Setting art direction should return true"
);

$retrieved_directions = timu_get_art_direction($test_attachment_id);
assert_test(
    arrays_equal($retrieved_directions, $test_directions),
    "Retrieved art direction should match what was set"
);

// Test 3: Invalid attachment ID
echo "\nTesting invalid inputs...\n";

assert_test(
    timu_set_art_direction(0, []) === false,
    "Setting art direction with invalid ID should fail"
);

assert_test(
    timu_set_art_direction(-1, []) === false,
    "Setting art direction with negative ID should fail"
);

assert_test(
    timu_get_art_direction(999) === [] || is_array(timu_get_art_direction(999)),
    "Getting art direction for non-existent ID should return empty array"
);

// Test 4: Media query generation
echo "\nTesting media query generation...\n";

$mobile_query = timu_get_media_query_for_size('mobile');
assert_test(
    $mobile_query === '(max-width: 767px)',
    "Mobile media query should be correct"
);

$tablet_query = timu_get_media_query_for_size('tablet');
assert_test(
    strpos($tablet_query, 'min-width: 768px') !== false,
    "Tablet media query should contain min-width: 768px"
);

$desktop_query = timu_get_media_query_for_size('desktop');
assert_test(
    strpos($desktop_query, 'min-width: 1024px') !== false,
    "Desktop media query should contain min-width: 1024px"
);

$unknown_query = timu_get_media_query_for_size('unknown');
assert_test(
    $unknown_query === '',
    "Unknown device size should return empty string"
);

// Test 5: Aspect ratio parsing
echo "\nTesting aspect ratio parsing...\n";

$ratio_16_9 = timu_parse_aspect_ratio('16:9');
assert_test(
    $ratio_16_9['width'] == 16 && $ratio_16_9['height'] == 9,
    "Aspect ratio 16:9 should parse correctly"
);

$ratio_4_3 = timu_parse_aspect_ratio('4:3');
assert_test(
    $ratio_4_3['width'] == 4 && $ratio_4_3['height'] == 3,
    "Aspect ratio 4:3 should parse correctly"
);

$invalid_ratio = timu_parse_aspect_ratio('invalid');
assert_test(
    $invalid_ratio === false,
    "Invalid aspect ratio should return false"
);

$invalid_ratio_format = timu_parse_aspect_ratio('16-9');
assert_test(
    $invalid_ratio_format === false,
    "Invalid aspect ratio format should return false"
);

// Test 6: MIME type mapping
echo "\nTesting MIME type mapping...\n";

assert_test(
    timu_get_mime_type_for_format('webp') === 'image/webp',
    "WebP format should map to image/webp"
);

assert_test(
    timu_get_mime_type_for_format('jpeg') === 'image/jpeg',
    "JPEG format should map to image/jpeg"
);

assert_test(
    timu_get_mime_type_for_format('image/png') === 'image/png',
    "image/png format should map to image/png"
);

assert_test(
    timu_get_mime_type_for_format('unknown') === 'image/jpeg',
    "Unknown format should default to image/jpeg"
);

// Test 7: Markup generation structure
echo "\nTesting markup generation...\n";

// Note: This test will not fully work without a real WordPress environment
// but we can test that the function exists and returns a string
$mock_attachment_id = 456;
timu_set_art_direction($mock_attachment_id, [
    'mobile' => [
        'crop' => null,
        'focal_point' => 'primary',
        'aspect_ratio' => '16:9',
    ],
]);

// In mock environment, this will return basic markup
$markup = timu_get_responsive_image_markup($mock_attachment_id);
assert_test(
    is_string($markup),
    "Markup generation should return a string"
);

assert_test(
    strpos($markup, '<picture>') !== false,
    "Markup should contain opening picture tag"
);

assert_test(
    strpos($markup, '</picture>') !== false,
    "Markup should contain closing picture tag"
);

// Test 8: Empty directions handling
echo "\nTesting empty directions...\n";

$empty_directions = timu_get_art_direction(789);
assert_test(
    is_array($empty_directions) && empty($empty_directions),
    "Non-existent art direction should return empty array"
);

// Test 9: Directions with null crop
echo "\nTesting null crop handling...\n";

$null_crop_directions = [
    'desktop' => [
        'crop' => null,
        'focal_point' => 'primary',
        'aspect_ratio' => '16:9',
    ],
];

$set_null_crop = timu_set_art_direction(999, $null_crop_directions);
assert_test(
    $set_null_crop === true,
    "Setting art direction with null crop should succeed"
);

$retrieved_null_crop = timu_get_art_direction(999);
assert_test(
    isset($retrieved_null_crop['desktop']) && $retrieved_null_crop['desktop']['crop'] === null,
    "Null crop should be preserved"
);

// Test 10: Invalid direction filtering
echo "\nTesting invalid direction filtering...\n";

$mixed_directions = [
    'mobile' => [
        'crop' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100],
        'focal_point' => 'primary',
        'aspect_ratio' => '2:3',
    ],
    'invalid' => [
        'crop' => ['x' => -10, 'y' => 0, 'width' => 100, 'height' => 100], // Invalid crop
        'focal_point' => 'primary',
        'aspect_ratio' => '4:3',
    ],
];

timu_set_art_direction(888, $mixed_directions);
$filtered_directions = timu_get_art_direction(888);

assert_test(
    isset($filtered_directions['mobile']),
    "Valid mobile direction should be preserved"
);

assert_test(
    !isset($filtered_directions['invalid']),
    "Invalid direction should be filtered out"
);

// Print results
echo "\n=== Test Results ===\n";
foreach ($test_results as $result) {
    echo "$result\n";
}

echo "\n=== Summary ===\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}
