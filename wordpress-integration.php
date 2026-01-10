<?php
/**
 * WordPress Integration Example
 *
 * Demonstrates how to integrate the Art Direction Mapper into a WordPress theme.
 *
 * @package ModuleImagesSupport
 */

/**
 * Step 1: Include the module in your theme's functions.php
 */
require_once get_template_directory() . '/modules/module-images-support.php';

/**
 * Step 2: Add admin interface (simplified example)
 * 
 * In a real implementation, you would add a meta box to the attachment editor
 * with a visual crop tool. This example shows the data structure.
 */
function timu_add_art_direction_meta_box() {
    add_meta_box(
        'timu_art_direction',
        'Art Direction',
        'timu_render_art_direction_meta_box',
        'attachment',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'timu_add_art_direction_meta_box');

/**
 * Render the art direction meta box
 */
function timu_render_art_direction_meta_box($post) {
    $directions = timu_get_art_direction($post->ID);
    
    wp_nonce_field('timu_art_direction_save', 'timu_art_direction_nonce');
    
    ?>
    <div class="timu-art-direction-editor">
        <p><strong>Device-Specific Crops</strong></p>
        <p class="description">Define custom crops for different devices. Leave empty to use default.</p>
        
        <!-- In a real implementation, this would be a visual editor -->
        <div class="timu-device-section">
            <h4>Mobile (max-width: 767px)</h4>
            <label>
                Aspect Ratio:
                <input type="text" name="timu_mobile_aspect_ratio" 
                       value="<?php echo esc_attr($directions['mobile']['aspect_ratio'] ?? '2:3'); ?>" 
                       placeholder="2:3" />
            </label>
        </div>
        
        <div class="timu-device-section">
            <h4>Tablet (768px - 1023px)</h4>
            <label>
                Aspect Ratio:
                <input type="text" name="timu_tablet_aspect_ratio" 
                       value="<?php echo esc_attr($directions['tablet']['aspect_ratio'] ?? '4:3'); ?>" 
                       placeholder="4:3" />
            </label>
        </div>
        
        <div class="timu-device-section">
            <h4>Desktop (min-width: 1024px)</h4>
            <label>
                Aspect Ratio:
                <input type="text" name="timu_desktop_aspect_ratio" 
                       value="<?php echo esc_attr($directions['desktop']['aspect_ratio'] ?? '16:9'); ?>" 
                       placeholder="16:9" />
            </label>
        </div>
        
        <p class="description">
            Note: In a production implementation, include a visual crop editor here.
        </p>
    </div>
    <?php
}

/**
 * Save art direction metadata
 */
function timu_save_art_direction_meta($post_id) {
    // Verify nonce
    if (!isset($_POST['timu_art_direction_nonce']) || 
        !wp_verify_nonce($_POST['timu_art_direction_nonce'], 'timu_art_direction_save')) {
        return;
    }
    
    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Build directions array from POST data
    // In a real implementation, this would include crop coordinates from a visual editor
    $directions = [
        'mobile' => [
            'crop' => null, // Would come from visual editor
            'focal_point' => 'primary',
            'aspect_ratio' => sanitize_text_field($_POST['timu_mobile_aspect_ratio'] ?? '2:3'),
        ],
        'tablet' => [
            'crop' => null,
            'focal_point' => 'primary',
            'aspect_ratio' => sanitize_text_field($_POST['timu_tablet_aspect_ratio'] ?? '4:3'),
        ],
        'desktop' => [
            'crop' => null,
            'focal_point' => 'primary',
            'aspect_ratio' => sanitize_text_field($_POST['timu_desktop_aspect_ratio'] ?? '16:9'),
        ],
    ];
    
    timu_set_art_direction($post_id, $directions);
}
add_action('edit_attachment', 'timu_save_art_direction_meta');

/**
 * Step 3: Replace standard post thumbnails with art-directed versions
 */
function timu_filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
    // Check if art direction is configured for this image
    $directions = timu_get_art_direction($post_thumbnail_id);
    
    if (empty($directions)) {
        return $html; // No art direction, use default
    }
    
    // Generate art-directed markup
    $alt = get_post_meta($post_thumbnail_id, '_wp_attachment_image_alt', true);
    if (empty($alt)) {
        $alt = get_the_title($post_id);
    }
    
    $class = '';
    if (isset($attr['class'])) {
        $class = $attr['class'];
    } elseif (is_string($size)) {
        $class = 'attachment-' . $size;
    }
    
    return timu_get_responsive_image_markup(
        $post_thumbnail_id,
        ['mobile', 'tablet', 'desktop'],
        ['webp', 'image/jpeg'],
        [
            'alt' => $alt,
            'class' => $class,
            'loading' => 'lazy',
        ]
    );
}
add_filter('post_thumbnail_html', 'timu_filter_post_thumbnail_html', 10, 5);

/**
 * Step 4: Add shortcode for manual usage
 */
function timu_art_direction_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
        'sizes' => 'mobile,tablet,desktop',
        'formats' => 'webp,jpeg',
        'alt' => '',
        'class' => '',
        'loading' => 'lazy',
    ], $atts);
    
    $attachment_id = intval($atts['id']);
    if ($attachment_id <= 0) {
        return '';
    }
    
    $sizes = array_map('trim', explode(',', $atts['sizes']));
    $formats = array_map('trim', explode(',', $atts['formats']));
    
    // Map format shortcuts to full MIME types
    $format_map = [
        'webp' => 'webp',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'avif' => 'avif',
    ];
    
    $formats = array_map(function($f) use ($format_map) {
        return $format_map[$f] ?? $f;
    }, $formats);
    
    return timu_get_responsive_image_markup(
        $attachment_id,
        $sizes,
        $formats,
        [
            'alt' => $atts['alt'],
            'class' => $atts['class'],
            'loading' => $atts['loading'],
        ]
    );
}
add_shortcode('art_direction', 'timu_art_direction_shortcode');

/**
 * Step 5: Template function for theme developers
 */
function timu_the_art_directed_image($attachment_id, $args = []) {
    $defaults = [
        'sizes' => ['mobile', 'tablet', 'desktop'],
        'formats' => ['webp', 'image/jpeg'],
        'echo' => true,
    ];
    
    $args = wp_parse_args($args, $defaults);
    $echo = $args['echo'];
    unset($args['echo'], $args['sizes'], $args['formats']);
    
    $markup = timu_get_responsive_image_markup(
        $attachment_id,
        $defaults['sizes'],
        $defaults['formats'],
        $args
    );
    
    if ($echo) {
        echo $markup;
    } else {
        return $markup;
    }
}

/**
 * Step 6: Automatically apply art direction to featured images
 */
function timu_setup_featured_image_art_direction($post_id) {
    // Only run for specific post types
    if (!in_array(get_post_type($post_id), ['post', 'page'])) {
        return;
    }
    
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if (!$thumbnail_id) {
        return;
    }
    
    // Check if art direction already exists
    $existing = timu_get_art_direction($thumbnail_id);
    if (!empty($existing)) {
        return;
    }
    
    // Auto-generate basic art direction
    $metadata = wp_get_attachment_metadata($thumbnail_id);
    if (!$metadata || !isset($metadata['width']) || !isset($metadata['height'])) {
        return;
    }
    
    $width = $metadata['width'];
    $height = $metadata['height'];
    
    $directions = [
        'mobile' => [
            'crop' => null, // Auto-crop based on aspect ratio
            'focal_point' => 'center',
            'aspect_ratio' => '2:3',
        ],
        'tablet' => [
            'crop' => null,
            'focal_point' => 'center',
            'aspect_ratio' => '4:3',
        ],
        'desktop' => [
            'crop' => null,
            'focal_point' => 'center',
            'aspect_ratio' => '16:9',
        ],
    ];
    
    timu_set_art_direction($thumbnail_id, $directions);
}
add_action('save_post', 'timu_setup_featured_image_art_direction');

/**
 * Step 7: Clear cache when image is replaced
 */
function timu_clear_cache_on_image_update($post_id) {
    if (wp_attachment_is_image($post_id)) {
        timu_clear_art_direction_cache($post_id);
    }
}
add_action('attachment_updated', 'timu_clear_cache_on_image_update');

/**
 * Step 8: Add REST API endpoint for dynamic updates
 */
function timu_register_rest_routes() {
    register_rest_route('timu/v1', '/art-direction/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'timu_rest_get_art_direction',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);
    
    register_rest_route('timu/v1', '/art-direction/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'timu_rest_update_art_direction',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('rest_api_init', 'timu_register_rest_routes');

function timu_rest_get_art_direction($request) {
    $attachment_id = intval($request['id']);
    $directions = timu_get_art_direction($attachment_id);
    
    return rest_ensure_response([
        'id' => $attachment_id,
        'directions' => $directions,
    ]);
}

function timu_rest_update_art_direction($request) {
    $attachment_id = intval($request['id']);
    $directions = $request->get_json_params();
    
    if (!isset($directions['directions'])) {
        return new WP_Error('invalid_data', 'Directions data required', ['status' => 400]);
    }
    
    $result = timu_set_art_direction($attachment_id, $directions['directions']);
    
    if ($result) {
        return rest_ensure_response([
            'success' => true,
            'id' => $attachment_id,
            'directions' => timu_get_art_direction($attachment_id),
        ]);
    }
    
    return new WP_Error('save_failed', 'Failed to save art direction', ['status' => 500]);
}

/**
 * Step 9: Theme template usage examples
 */

// Example 1: In single.php - Display featured image with art direction
function example_single_post_featured_image() {
    if (has_post_thumbnail()) {
        timu_the_art_directed_image(
            get_post_thumbnail_id(),
            [
                'alt' => get_the_title(),
                'class' => 'featured-image',
                'loading' => 'eager',
            ]
        );
    }
}

// Example 2: In archive.php - Display thumbnail with art direction
function example_archive_thumbnail() {
    if (has_post_thumbnail()) {
        timu_the_art_directed_image(
            get_post_thumbnail_id(),
            [
                'sizes' => ['mobile', 'tablet', 'desktop'],
                'formats' => ['webp', 'image/jpeg'],
                'alt' => get_the_title(),
                'class' => 'archive-thumbnail',
            ]
        );
    }
}

// Example 3: In WooCommerce product template
function example_woocommerce_product_image() {
    global $product;
    $attachment_id = $product->get_image_id();
    
    if ($attachment_id) {
        echo timu_get_responsive_image_markup(
            $attachment_id,
            ['mobile', 'tablet', 'desktop'],
            ['webp', 'image/jpeg'],
            [
                'alt' => $product->get_name(),
                'class' => 'product-image',
            ]
        );
    }
}

/**
 * Step 10: Enqueue admin styles and scripts (for visual editor)
 */
function timu_enqueue_admin_assets($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }
    
    // In a real implementation, enqueue your crop editor JavaScript and CSS
    // wp_enqueue_script('timu-crop-editor', get_template_directory_uri() . '/js/crop-editor.js', ['jquery'], '1.0', true);
    // wp_enqueue_style('timu-crop-editor', get_template_directory_uri() . '/css/crop-editor.css', [], '1.0');
    
    // Pass configuration to JavaScript
    wp_localize_script('timu-crop-editor', 'timuConfig', [
        'breakpoints' => [
            'mobile' => 767,
            'tablet' => 1023,
            'desktop' => 1024,
        ],
        'defaultAspectRatios' => [
            'mobile' => '2:3',
            'tablet' => '4:3',
            'desktop' => '16:9',
        ],
    ]);
}
add_action('admin_enqueue_scripts', 'timu_enqueue_admin_assets');
