<?php
/**
 * Module Images Support - Art Direction Mapper
 *
 * @package ModuleImagesSupport
 * @version 1.0.0
 * @description WordPress image enhancement module with art direction support for device-specific crops
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Set art direction metadata for an attachment.
 *
 * Define device-specific crops and aspect ratios for responsive images.
 *
 * @param int   $attachment_id The attachment post ID.
 * @param array $directions    Array of device-specific configurations.
 *                             Each device key should contain:
 *                             - 'crop' (array|null): Crop coordinates ['x', 'y', 'width', 'height']
 *                             - 'focal_point' (string): Focal point reference
 *                             - 'aspect_ratio' (string): Aspect ratio like '16:9'
 *
 * @return bool True on success, false on failure.
 *
 * @example
 * timu_set_art_direction(123, [
 *     'mobile' => [
 *         'crop' => ['x' => 0, 'y' => 100, 'width' => 600, 'height' => 900],
 *         'focal_point' => 'primary',
 *         'aspect_ratio' => '2:3',
 *     ],
 *     'tablet' => [
 *         'crop' => ['x' => 0, 'y' => 0, 'width' => 1024, 'height' => 768],
 *         'focal_point' => 'secondary',
 *         'aspect_ratio' => '4:3',
 *     ],
 *     'desktop' => [
 *         'crop' => null,
 *         'focal_point' => 'primary',
 *         'aspect_ratio' => '16:9',
 *     ],
 * ]);
 */
function timu_set_art_direction($attachment_id, $directions = []) {
    if (!is_numeric($attachment_id) || $attachment_id <= 0) {
        return false;
    }
    
    if (!is_array($directions)) {
        return false;
    }
    
    // Validate each direction configuration
    $valid_directions = [];
    foreach ($directions as $device => $config) {
        if (!is_array($config)) {
            continue;
        }
        
        // Validate crop if present
        if (isset($config['crop']) && $config['crop'] !== null) {
            if (!timu_validate_crop_config($config['crop'])) {
                continue;
            }
        }
        
        $valid_directions[$device] = $config;
    }
    
    return update_post_meta($attachment_id, '_timu_art_direction', $valid_directions);
}

/**
 * Get art direction metadata for an attachment.
 *
 * @param int $attachment_id The attachment post ID.
 *
 * @return array Art direction configuration or empty array if not set.
 */
function timu_get_art_direction($attachment_id) {
    if (!is_numeric($attachment_id) || $attachment_id <= 0) {
        return [];
    }
    
    $directions = get_post_meta($attachment_id, '_timu_art_direction', true);
    
    return is_array($directions) ? $directions : [];
}

/**
 * Validate crop configuration.
 *
 * @param array $crop Crop configuration array.
 *
 * @return bool True if valid, false otherwise.
 */
function timu_validate_crop_config($crop) {
    if (!is_array($crop)) {
        return false;
    }
    
    $required_keys = ['x', 'y', 'width', 'height'];
    foreach ($required_keys as $key) {
        if (!isset($crop[$key]) || !is_numeric($crop[$key]) || $crop[$key] < 0) {
            return false;
        }
    }
    
    return $crop['width'] > 0 && $crop['height'] > 0;
}

/**
 * Generate art-directed image with device-specific crop.
 *
 * Creates a cropped version of the image based on art direction configuration.
 *
 * @param int    $attachment_id The attachment post ID.
 * @param string $size          The device size key (mobile, tablet, desktop).
 * @param array  $direction     The art direction configuration for this size.
 *
 * @return string|false Path to the generated image or false on failure.
 */
function timu_generate_art_directed_image($attachment_id, $size, $direction) {
    $image_path = get_attached_file($attachment_id);
    
    if (!$image_path || !file_exists($image_path)) {
        return false;
    }
    
    // If no crop is specified, use the original image
    if (!isset($direction['crop']) || $direction['crop'] === null) {
        return $image_path;
    }
    
    $crop = $direction['crop'];
    
    // Generate a unique filename for this crop
    $path_info = pathinfo($image_path);
    $upload_dir = wp_upload_dir();
    
    $cropped_filename = sprintf(
        '%s-%s-%dx%d-%dx%d.%s',
        $path_info['filename'],
        $size,
        $crop['x'],
        $crop['y'],
        $crop['width'],
        $crop['height'],
        $path_info['extension']
    );
    
    $cropped_path = $upload_dir['path'] . '/' . $cropped_filename;
    
    // Check if cropped version already exists
    if (file_exists($cropped_path)) {
        return $cropped_path;
    }
    
    // Load the image editor
    $image_editor = wp_get_image_editor($image_path);
    
    if (is_wp_error($image_editor)) {
        return false;
    }
    
    // Perform the crop
    $crop_result = $image_editor->crop(
        $crop['x'],
        $crop['y'],
        $crop['width'],
        $crop['height']
    );
    
    if (is_wp_error($crop_result)) {
        return false;
    }
    
    // Save the cropped image
    $save_result = $image_editor->save($cropped_path);
    
    if (is_wp_error($save_result)) {
        return false;
    }
    
    return $cropped_path;
}

/**
 * Generate srcset string with DPR variants.
 *
 * Creates multiple resolution versions of an image for different pixel densities.
 *
 * @param string $image_path Base image path.
 * @param string $size       Device size identifier.
 * @param array  $dpr_levels DPR levels to generate (default: [1, 2, 3]).
 *
 * @return string Srcset string with DPR variants.
 */
function timu_generate_srcset($image_path, $size, $dpr_levels = [1, 2, 3]) {
    if (!file_exists($image_path)) {
        return '';
    }
    
    $upload_dir = wp_upload_dir();
    $srcset_parts = [];
    
    foreach ($dpr_levels as $dpr) {
        if ($dpr === 1) {
            // Use original for 1x
            $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $image_path);
            $srcset_parts[] = esc_url($url);
        } else {
            // Generate higher DPR version
            $dpr_path = timu_generate_dpr_variant($image_path, $dpr);
            
            if ($dpr_path && file_exists($dpr_path)) {
                $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $dpr_path);
                $srcset_parts[] = esc_url($url) . ' ' . $dpr . 'x';
            }
        }
    }
    
    return implode(', ', $srcset_parts);
}

/**
 * Generate a DPR variant of an image.
 *
 * @param string $image_path Original image path.
 * @param float  $dpr        Device pixel ratio multiplier.
 *
 * @return string|false Path to DPR variant or false on failure.
 */
function timu_generate_dpr_variant($image_path, $dpr) {
    if ($dpr <= 1 || !file_exists($image_path)) {
        return false;
    }
    
    $path_info = pathinfo($image_path);
    $dpr_filename = $path_info['filename'] . '-' . $dpr . 'x.' . $path_info['extension'];
    $dpr_path = $path_info['dirname'] . '/' . $dpr_filename;
    
    // Check if DPR variant already exists
    if (file_exists($dpr_path)) {
        return $dpr_path;
    }
    
    // Load the image editor
    $image_editor = wp_get_image_editor($image_path);
    
    if (is_wp_error($image_editor)) {
        return false;
    }
    
    // Get current dimensions
    $size = $image_editor->get_size();
    
    if (!$size) {
        return false;
    }
    
    // Calculate new dimensions
    $new_width = round($size['width'] * $dpr);
    $new_height = round($size['height'] * $dpr);
    
    // Resize the image
    $resize_result = $image_editor->resize($new_width, $new_height, false);
    
    if (is_wp_error($resize_result)) {
        return false;
    }
    
    // Save the DPR variant
    $save_result = $image_editor->save($dpr_path);
    
    if (is_wp_error($save_result)) {
        return false;
    }
    
    return $dpr_path;
}

/**
 * Get media query string for a device size.
 *
 * Returns the appropriate CSS media query for device breakpoints.
 *
 * @param string $size Device size identifier (mobile, tablet, desktop).
 *
 * @return string Media query string.
 */
function timu_get_media_query_for_size($size) {
    $media_queries = [
        'mobile' => '(max-width: 767px)',
        'tablet' => '(min-width: 768px) and (max-width: 1023px)',
        'desktop' => '(min-width: 1024px)',
    ];
    
    // Allow filtering of media queries
    $media_queries = apply_filters('timu_media_queries', $media_queries);
    
    return isset($media_queries[$size]) ? $media_queries[$size] : '';
}

/**
 * Parse aspect ratio string into width and height values.
 *
 * @param string $aspect_ratio Aspect ratio string (e.g., '16:9', '4:3').
 *
 * @return array|false Array with 'width' and 'height' keys, or false on failure.
 */
function timu_parse_aspect_ratio($aspect_ratio) {
    if (!is_string($aspect_ratio) || strpos($aspect_ratio, ':') === false) {
        return false;
    }
    
    $parts = explode(':', $aspect_ratio);
    
    if (count($parts) !== 2) {
        return false;
    }
    
    $width = floatval($parts[0]);
    $height = floatval($parts[1]);
    
    if ($width <= 0 || $height <= 0) {
        return false;
    }
    
    return [
        'width' => $width,
        'height' => $height,
    ];
}

/**
 * Generate responsive image markup with art direction.
 *
 * Creates a <picture> element with device-specific crops and srcset attributes.
 *
 * @param int   $attachment_id The attachment post ID.
 * @param array $sizes         Device sizes to include (default: ['mobile', 'tablet', 'desktop']).
 * @param array $formats       Image formats to generate (default: ['webp', 'image/jpeg']).
 * @param array $args          Additional arguments:
 *                             - 'alt' (string): Alt text for the image
 *                             - 'class' (string): CSS class for the img element
 *                             - 'loading' (string): Loading attribute (lazy, eager, auto)
 *                             - 'dpr_levels' (array): DPR levels to generate
 *
 * @return string HTML markup for the responsive image.
 *
 * @example
 * echo timu_get_responsive_image_markup(123, ['mobile', 'tablet', 'desktop']);
 */
function timu_get_responsive_image_markup($attachment_id, $sizes = ['mobile', 'tablet', 'desktop'], $formats = ['webp', 'image/jpeg'], $args = []) {
    if (!is_numeric($attachment_id) || $attachment_id <= 0) {
        return '';
    }
    
    $directions = timu_get_art_direction($attachment_id);
    
    // Parse arguments
    $defaults = [
        'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        'class' => '',
        'loading' => 'lazy',
        'dpr_levels' => [1, 2, 3],
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $markup = '<picture>';
    
    // Generate source elements for each device size and format
    foreach ($sizes as $size) {
        if (!isset($directions[$size])) {
            continue;
        }
        
        $direction = $directions[$size];
        $media_query = timu_get_media_query_for_size($size);
        
        if (empty($media_query)) {
            continue;
        }
        
        // Generate cropped version
        $cropped_path = timu_generate_art_directed_image($attachment_id, $size, $direction);
        
        if (!$cropped_path) {
            continue;
        }
        
        // Generate sources for each format
        foreach ($formats as $format) {
            $format_path = timu_convert_image_format($cropped_path, $format);
            
            if (!$format_path) {
                continue;
            }
            
            $srcset = timu_generate_srcset($format_path, $size, $args['dpr_levels']);
            
            if (empty($srcset)) {
                continue;
            }
            
            $mime_type = timu_get_mime_type_for_format($format);
            
            $markup .= sprintf(
                '<source media="%s" srcset="%s" type="%s">',
                esc_attr($media_query),
                $srcset,
                esc_attr($mime_type)
            );
        }
    }
    
    // Fallback img element
    $fallback_url = wp_get_attachment_image_url($attachment_id, 'large');
    
    if (!$fallback_url) {
        $fallback_url = wp_get_attachment_url($attachment_id);
    }
    
    $img_attributes = [
        'src' => esc_url($fallback_url),
        'alt' => esc_attr($args['alt']),
    ];
    
    if (!empty($args['class'])) {
        $img_attributes['class'] = esc_attr($args['class']);
    }
    
    if (!empty($args['loading'])) {
        $img_attributes['loading'] = esc_attr($args['loading']);
    }
    
    $img_attrs_string = '';
    foreach ($img_attributes as $attr => $value) {
        $img_attrs_string .= sprintf(' %s="%s"', $attr, $value);
    }
    
    $markup .= sprintf('<img%s />', $img_attrs_string);
    $markup .= '</picture>';
    
    return $markup;
}

/**
 * Convert image to a different format.
 *
 * @param string $image_path Original image path.
 * @param string $format     Target format ('webp', 'image/jpeg', 'image/png', etc.).
 *
 * @return string|false Path to converted image or false on failure.
 */
function timu_convert_image_format($image_path, $format) {
    if (!file_exists($image_path)) {
        return false;
    }
    
    // Map format names to extensions
    $format_map = [
        'webp' => 'webp',
        'image/webp' => 'webp',
        'avif' => 'avif',
        'image/avif' => 'avif',
        'jpeg' => 'jpg',
        'jpg' => 'jpg',
        'image/jpeg' => 'jpg',
        'png' => 'png',
        'image/png' => 'png',
    ];
    
    $target_extension = isset($format_map[$format]) ? $format_map[$format] : $format;
    
    $path_info = pathinfo($image_path);
    
    // If already in target format, return as-is
    if ($path_info['extension'] === $target_extension) {
        return $image_path;
    }
    
    $converted_filename = $path_info['filename'] . '.' . $target_extension;
    $converted_path = $path_info['dirname'] . '/' . $converted_filename;
    
    // Check if converted version already exists
    if (file_exists($converted_path)) {
        return $converted_path;
    }
    
    // Load the image editor
    $image_editor = wp_get_image_editor($image_path);
    
    if (is_wp_error($image_editor)) {
        return $image_path; // Return original if conversion fails
    }
    
    // Set output format
    $mime_type = timu_get_mime_type_for_format($format);
    
    // Save in new format
    $save_result = $image_editor->save($converted_path, $mime_type);
    
    if (is_wp_error($save_result)) {
        return $image_path; // Return original if conversion fails
    }
    
    return $converted_path;
}

/**
 * Get MIME type for a format identifier.
 *
 * @param string $format Format identifier.
 *
 * @return string MIME type.
 */
function timu_get_mime_type_for_format($format) {
    $mime_types = [
        'webp' => 'image/webp',
        'image/webp' => 'image/webp',
        'avif' => 'image/avif',
        'image/avif' => 'image/avif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'image/jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'image/png' => 'image/png',
    ];
    
    return isset($mime_types[$format]) ? $mime_types[$format] : 'image/jpeg';
}

/**
 * Clear cached art-directed images for an attachment.
 *
 * Removes all generated crops, DPR variants, and format conversions.
 *
 * @param int $attachment_id The attachment post ID.
 *
 * @return bool True on success, false on failure.
 */
function timu_clear_art_direction_cache($attachment_id) {
    if (!is_numeric($attachment_id) || $attachment_id <= 0) {
        return false;
    }
    
    $image_path = get_attached_file($attachment_id);
    
    if (!$image_path || !file_exists($image_path)) {
        return false;
    }
    
    $path_info = pathinfo($image_path);
    $upload_dir = wp_upload_dir();
    
    // Find and delete all generated variants
    $pattern = $path_info['dirname'] . '/' . $path_info['filename'] . '-*';
    $files = glob($pattern);
    
    if (is_array($files)) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    return true;
}
