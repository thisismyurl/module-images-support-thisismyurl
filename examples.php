<?php
/**
 * Art Direction Mapper - Usage Examples
 *
 * This file demonstrates various use cases for the Art Direction Mapper module.
 *
 * @package ModuleImagesSupport
 */

// Ensure the main module is loaded
require_once __DIR__ . '/module-images-support.php';

/**
 * Example 1: Basic Art Direction Setup
 *
 * Set up device-specific crops for a featured image on a blog post.
 */
function example_basic_art_direction() {
    // Assume we have an attachment ID for our hero image
    $attachment_id = 123;
    
    // Define art direction for different devices
    $directions = [
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
        'desktop' => [
            'crop' => null, // Use full image
            'focal_point' => 'primary',
            'aspect_ratio' => '16:9',
        ],
    ];
    
    // Save the art direction configuration
    timu_set_art_direction($attachment_id, $directions);
    
    // Generate responsive markup
    echo timu_get_responsive_image_markup($attachment_id);
}

/**
 * Example 2: Product Gallery with Custom Crops
 *
 * E-commerce site showing different product angles for different devices.
 */
function example_product_gallery() {
    $product_image_id = 456;
    
    $directions = [
        'mobile' => [
            // Close-up crop for mobile - focus on product detail
            'crop' => ['x' => 200, 'y' => 150, 'width' => 800, 'height' => 1200],
            'focal_point' => 'center',
            'aspect_ratio' => '2:3',
        ],
        'tablet' => [
            // Medium view showing product with some context
            'crop' => ['x' => 100, 'y' => 100, 'width' => 1200, 'height' => 900],
            'focal_point' => 'center',
            'aspect_ratio' => '4:3',
        ],
        'desktop' => [
            // Wide view showing full product and lifestyle context
            'crop' => null,
            'focal_point' => 'center',
            'aspect_ratio' => '16:9',
        ],
    ];
    
    timu_set_art_direction($product_image_id, $directions);
    
    // Generate markup with custom attributes
    echo timu_get_responsive_image_markup(
        $product_image_id,
        ['mobile', 'tablet', 'desktop'],
        ['webp', 'image/jpeg'],
        [
            'alt' => 'Premium Product Name',
            'class' => 'product-image',
            'loading' => 'eager', // Above the fold
            'dpr_levels' => [1, 2, 3],
        ]
    );
}

/**
 * Example 3: News Article Hero with Editorial Crops
 *
 * News site with editorial focus on different story aspects per device.
 */
function example_news_hero() {
    $article_hero_id = 789;
    
    $directions = [
        'mobile' => [
            // Portrait crop focusing on main subject
            'crop' => ['x' => 300, 'y' => 0, 'width' => 600, 'height' => 800],
            'focal_point' => 'primary',
            'aspect_ratio' => '3:4',
        ],
        'tablet' => [
            // Landscape crop showing more context
            'crop' => ['x' => 0, 'y' => 200, 'width' => 1400, 'height' => 800],
            'focal_point' => 'primary',
            'aspect_ratio' => '16:9',
        ],
        'desktop' => [
            // Cinematic wide crop
            'crop' => ['x' => 0, 'y' => 300, 'width' => 2000, 'height' => 900],
            'focal_point' => 'primary',
            'aspect_ratio' => '21:9',
        ],
    ];
    
    timu_set_art_direction($article_hero_id, $directions);
    
    echo timu_get_responsive_image_markup(
        $article_hero_id,
        ['mobile', 'tablet', 'desktop'],
        ['webp', 'image/jpeg'],
        [
            'alt' => 'Breaking News: Major Story Headline',
            'class' => 'article-hero',
            'loading' => 'eager',
        ]
    );
}

/**
 * Example 4: Portfolio Image with Artistic Crops
 *
 * Photography portfolio with different compositional choices per device.
 */
function example_portfolio_image() {
    $portfolio_image_id = 321;
    
    $directions = [
        'mobile' => [
            // Vertical portrait composition
            'crop' => ['x' => 400, 'y' => 0, 'width' => 800, 'height' => 1200],
            'focal_point' => 'primary',
            'aspect_ratio' => '2:3',
        ],
        'tablet' => [
            // Square crop for gallery view
            'crop' => ['x' => 200, 'y' => 200, 'width' => 1200, 'height' => 1200],
            'focal_point' => 'center',
            'aspect_ratio' => '1:1',
        ],
        'desktop' => [
            // Full panoramic view
            'crop' => null,
            'focal_point' => 'primary',
            'aspect_ratio' => '16:9',
        ],
    ];
    
    timu_set_art_direction($portfolio_image_id, $directions);
    
    // Use only WebP for modern browsers, JPEG fallback
    echo timu_get_responsive_image_markup(
        $portfolio_image_id,
        ['mobile', 'tablet', 'desktop'],
        ['webp', 'image/jpeg']
    );
}

/**
 * Example 5: Dynamic Art Direction from User Input
 *
 * Allow users to define their own crops through a custom interface.
 */
function example_dynamic_user_crops($attachment_id, $user_crop_data) {
    // Validate and sanitize user input
    $sanitized_directions = [];
    
    foreach ($user_crop_data as $device => $data) {
        // Ensure device is valid
        if (!in_array($device, ['mobile', 'tablet', 'desktop'])) {
            continue;
        }
        
        $direction = [];
        
        // Sanitize crop coordinates
        if (isset($data['crop']) && is_array($data['crop'])) {
            $direction['crop'] = [
                'x' => absint($data['crop']['x']),
                'y' => absint($data['crop']['y']),
                'width' => absint($data['crop']['width']),
                'height' => absint($data['crop']['height']),
            ];
        } else {
            $direction['crop'] = null;
        }
        
        // Sanitize focal point
        $direction['focal_point'] = isset($data['focal_point']) 
            ? sanitize_text_field($data['focal_point']) 
            : 'primary';
        
        // Sanitize aspect ratio
        $direction['aspect_ratio'] = isset($data['aspect_ratio']) 
            ? sanitize_text_field($data['aspect_ratio']) 
            : '16:9';
        
        $sanitized_directions[$device] = $direction;
    }
    
    // Save sanitized directions
    timu_set_art_direction($attachment_id, $sanitized_directions);
    
    return timu_get_responsive_image_markup($attachment_id);
}

/**
 * Example 6: Programmatic Crop Generation
 *
 * Automatically generate crops based on image dimensions and aspect ratios.
 */
function example_auto_generate_crops($attachment_id) {
    // Get image metadata
    $metadata = wp_get_attachment_metadata($attachment_id);
    
    if (!$metadata || !isset($metadata['width']) || !isset($metadata['height'])) {
        return false;
    }
    
    $width = $metadata['width'];
    $height = $metadata['height'];
    
    $directions = [];
    
    // Mobile: 2:3 portrait crop from center
    $mobile_width = min($width, 600);
    $mobile_height = round($mobile_width * 1.5);
    $directions['mobile'] = [
        'crop' => [
            'x' => max(0, round(($width - $mobile_width) / 2)),
            'y' => max(0, round(($height - $mobile_height) / 2)),
            'width' => $mobile_width,
            'height' => min($mobile_height, $height),
        ],
        'focal_point' => 'center',
        'aspect_ratio' => '2:3',
    ];
    
    // Tablet: 4:3 crop from center
    $tablet_width = min($width, 1024);
    $tablet_height = round($tablet_width * 0.75);
    $directions['tablet'] = [
        'crop' => [
            'x' => max(0, round(($width - $tablet_width) / 2)),
            'y' => max(0, round(($height - $tablet_height) / 2)),
            'width' => $tablet_width,
            'height' => min($tablet_height, $height),
        ],
        'focal_point' => 'center',
        'aspect_ratio' => '4:3',
    ];
    
    // Desktop: Use full image
    $directions['desktop'] = [
        'crop' => null,
        'focal_point' => 'center',
        'aspect_ratio' => round($width / $height, 2) . ':1',
    ];
    
    timu_set_art_direction($attachment_id, $directions);
    
    return timu_get_responsive_image_markup($attachment_id);
}

/**
 * Example 7: WordPress Hook Integration
 *
 * Automatically apply art direction to featured images.
 */
function example_hook_integration() {
    // Hook into post thumbnail HTML generation
    add_filter('post_thumbnail_html', function($html, $post_id, $post_thumbnail_id) {
        // Check if art direction is configured
        $directions = timu_get_art_direction($post_thumbnail_id);
        
        if (!empty($directions)) {
            // Replace default thumbnail with art-directed version
            return timu_get_responsive_image_markup(
                $post_thumbnail_id,
                ['mobile', 'tablet', 'desktop'],
                ['webp', 'image/jpeg'],
                [
                    'alt' => get_the_title($post_id),
                    'class' => 'wp-post-image',
                ]
            );
        }
        
        return $html;
    }, 10, 3);
}

/**
 * Example 8: Clear Cache for Regeneration
 *
 * Clear cached variants when source image is updated.
 */
function example_clear_cache($attachment_id) {
    // Clear all generated variants
    timu_clear_art_direction_cache($attachment_id);
    
    // Optionally regenerate with new settings
    $directions = timu_get_art_direction($attachment_id);
    
    if (!empty($directions)) {
        // Regenerate markup (which will create new variants)
        $markup = timu_get_responsive_image_markup($attachment_id);
        return $markup;
    }
    
    return false;
}

/**
 * Example 9: Custom Media Queries
 *
 * Override default breakpoints with custom values.
 */
function example_custom_breakpoints() {
    // Filter media queries
    add_filter('timu_media_queries', function($queries) {
        return [
            'mobile' => '(max-width: 639px)',
            'tablet' => '(min-width: 640px) and (max-width: 1279px)',
            'desktop' => '(min-width: 1280px)',
        ];
    });
    
    // Now use the module with custom breakpoints
    $attachment_id = 999;
    
    $directions = [
        'mobile' => [
            'crop' => ['x' => 0, 'y' => 0, 'width' => 640, 'height' => 960],
            'focal_point' => 'primary',
            'aspect_ratio' => '2:3',
        ],
        'tablet' => [
            'crop' => ['x' => 0, 'y' => 0, 'width' => 1280, 'height' => 960],
            'focal_point' => 'primary',
            'aspect_ratio' => '4:3',
        ],
        'desktop' => [
            'crop' => null,
            'focal_point' => 'primary',
            'aspect_ratio' => '16:9',
        ],
    ];
    
    timu_set_art_direction($attachment_id, $directions);
    
    return timu_get_responsive_image_markup($attachment_id);
}

/**
 * Example 10: Retrieve and Modify Existing Art Direction
 *
 * Read existing configuration and update specific devices.
 */
function example_update_art_direction($attachment_id) {
    // Get existing art direction
    $directions = timu_get_art_direction($attachment_id);
    
    // Update only mobile crop
    if (isset($directions['mobile'])) {
        $directions['mobile']['crop'] = [
            'x' => 100,
            'y' => 200,
            'width' => 700,
            'height' => 1000,
        ];
    }
    
    // Add new device size
    $directions['large-desktop'] = [
        'crop' => null,
        'focal_point' => 'primary',
        'aspect_ratio' => '21:9',
    ];
    
    // Save updated configuration
    timu_set_art_direction($attachment_id, $directions);
    
    // Generate markup for all devices including the new one
    return timu_get_responsive_image_markup(
        $attachment_id,
        ['mobile', 'tablet', 'desktop', 'large-desktop']
    );
}
