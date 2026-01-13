<?php
/**
 * Cropping Functions with Focal Point Support
 *
 * @package TIMU
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculate crop coordinates centered on a focal point.
 *
 * @param int    $attachment_id   The attachment post ID.
 * @param string $focal_point_key The key of the focal point to use.
 * @param int    $target_width    The desired crop width.
 * @param int    $target_height   The desired crop height.
 * @return array|WP_Error Crop coordinates array or WP_Error on failure.
 *                        Array contains: ['x', 'y', 'width', 'height']
 */
function timu_crop_to_focal_point( $attachment_id, $focal_point_key, $target_width, $target_height ) {
	// Get focal points.
	$focal_points = timu_get_focal_points( $attachment_id );
	
	// Sanitize key.
	$sanitized_key = sanitize_key( $focal_point_key );
	
	// Check if focal point exists.
	if ( ! isset( $focal_points[ $sanitized_key ] ) ) {
		return new WP_Error(
			'focal_point_not_found',
			__( 'The specified focal point does not exist.', 'timu' ),
			array( 'attachment_id' => $attachment_id, 'focal_point_key' => $focal_point_key )
		);
	}
	
	$focal = $focal_points[ $sanitized_key ];
	
	// Get attached file path.
	$file = get_attached_file( $attachment_id );
	
	if ( ! $file || ! file_exists( $file ) ) {
		return new WP_Error(
			'file_not_found',
			__( 'The attachment file could not be found.', 'timu' ),
			array( 'attachment_id' => $attachment_id )
		);
	}
	
	// Get image dimensions.
	$image_size = @getimagesize( $file );
	
	if ( false === $image_size ) {
		return new WP_Error(
			'invalid_image',
			__( 'Unable to read image dimensions.', 'timu' ),
			array( 'file' => $file )
		);
	}
	
	$image_width  = $image_size[0];
	$image_height = $image_size[1];
	
	// Validate target dimensions.
	if ( $target_width <= 0 || $target_height <= 0 ) {
		return new WP_Error(
			'invalid_dimensions',
			__( 'Target dimensions must be positive integers.', 'timu' ),
			array( 'target_width' => $target_width, 'target_height' => $target_height )
		);
	}
	
	// Calculate aspect ratio of target.
	$aspect_ratio = $target_width / $target_height;
	
	// Calculate crop dimensions maintaining aspect ratio.
	$crop_width  = min( $image_width, $image_height * $aspect_ratio );
	$crop_height = $crop_width / $aspect_ratio;
	
	// If calculated height exceeds image height, recalculate based on height.
	if ( $crop_height > $image_height ) {
		$crop_height = $image_height;
		$crop_width  = $crop_height * $aspect_ratio;
	}
	
	// Convert focal point percentages to pixel coordinates.
	$focal_x = ( $image_width * $focal['x'] ) / 100;
	$focal_y = ( $image_height * $focal['y'] ) / 100;
	
	// Center crop on focal point.
	$crop_left = $focal_x - ( $crop_width / 2 );
	$crop_top  = $focal_y - ( $crop_height / 2 );
	
	// Ensure crop doesn't go beyond image boundaries.
	$crop_left = max( 0, $crop_left );
	$crop_top  = max( 0, $crop_top );
	
	// Adjust if crop extends beyond right or bottom edge.
	$crop_left = min( $crop_left, $image_width - $crop_width );
	$crop_top  = min( $crop_top, $image_height - $crop_height );
	
	// Prepare crop data.
	$crop_data = array(
		'x'      => round( $crop_left ),
		'y'      => round( $crop_top ),
		'width'  => round( $crop_width ),
		'height' => round( $crop_height ),
	);
	
	// Allow filtering of crop data.
	$crop_data = apply_filters( 'timu_crop_coordinates', $crop_data, $attachment_id, $focal_point_key, $target_width, $target_height );
	
	return $crop_data;
}

/**
 * Apply focal point crop to an image and generate a new file.
 *
 * @param int    $attachment_id   The attachment post ID.
 * @param string $focal_point_key The key of the focal point to use.
 * @param int    $target_width    The desired crop width.
 * @param int    $target_height   The desired crop height.
 * @param string $suffix          Optional suffix for the generated filename.
 * @return string|WP_Error Path to the cropped image or WP_Error on failure.
 */
function timu_generate_focal_point_crop( $attachment_id, $focal_point_key, $target_width, $target_height, $suffix = '' ) {
	// Get crop coordinates.
	$crop_data = timu_crop_to_focal_point( $attachment_id, $focal_point_key, $target_width, $target_height );
	
	if ( is_wp_error( $crop_data ) ) {
		return $crop_data;
	}
	
	// Get image editor.
	$file   = get_attached_file( $attachment_id );
	$editor = wp_get_image_editor( $file );
	
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}
	
	// Perform crop.
	$crop_result = $editor->crop(
		$crop_data['x'],
		$crop_data['y'],
		$crop_data['width'],
		$crop_data['height'],
		$target_width,
		$target_height
	);
	
	if ( is_wp_error( $crop_result ) ) {
		return $crop_result;
	}
	
	// Generate filename with suffix.
	if ( empty( $suffix ) ) {
		$suffix = sprintf( 'focal-%s-%dx%d', $focal_point_key, $target_width, $target_height );
	}
	
	$saved = $editor->save( $editor->generate_filename( $suffix ) );
	
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	
	// Trigger action hook.
	do_action( 'timu_focal_point_crop_generated', $attachment_id, $focal_point_key, $saved['path'] );
	
	return $saved['path'];
}

/**
 * Get recommended focal point for a given context.
 *
 * @param int    $attachment_id The attachment post ID.
 * @param string $context       The context (e.g., 'mobile', 'desktop', 'social', 'instagram').
 * @return string|null The recommended focal point key or null if none found.
 */
function timu_get_recommended_focal_point( $attachment_id, $context = 'default' ) {
	// Get all focal points.
	$focal_points = timu_get_focal_points( $attachment_id );
	
	if ( empty( $focal_points ) ) {
		return null;
	}
	
	// Apply filter to allow custom context recommendations.
	$recommended = apply_filters( 'timu_recommended_focal_point', null, $attachment_id, $context, $focal_points );
	
	if ( null !== $recommended && isset( $focal_points[ $recommended ] ) ) {
		return $recommended;
	}
	
	// Default logic: return 'primary' if it exists, otherwise return first focal point.
	if ( isset( $focal_points['primary'] ) ) {
		return 'primary';
	}
	
	// Return first focal point key.
	$keys = array_keys( $focal_points );
	return $keys[0];
}

/**
 * Get common aspect ratios for preview.
 *
 * @return array Array of common aspect ratios with labels.
 */
function timu_get_common_aspect_ratios() {
	$ratios = array(
		'1:1'   => array(
			'label'  => __( 'Square (1:1)', 'timu' ),
			'width'  => 1,
			'height' => 1,
		),
		'4:3'   => array(
			'label'  => __( 'Standard (4:3)', 'timu' ),
			'width'  => 4,
			'height' => 3,
		),
		'16:9'  => array(
			'label'  => __( 'Widescreen (16:9)', 'timu' ),
			'width'  => 16,
			'height' => 9,
		),
		'3:2'   => array(
			'label'  => __( 'Classic (3:2)', 'timu' ),
			'width'  => 3,
			'height' => 2,
		),
		'9:16'  => array(
			'label'  => __( 'Mobile Story (9:16)', 'timu' ),
			'width'  => 9,
			'height' => 16,
		),
		'4:5'   => array(
			'label'  => __( 'Instagram Portrait (4:5)', 'timu' ),
			'width'  => 4,
			'height' => 5,
		),
	);
	
	// Allow filtering of aspect ratios.
	return apply_filters( 'timu_common_aspect_ratios', $ratios );
}

/**
 * Preview crop bounds for a focal point across multiple aspect ratios.
 *
 * @param int    $attachment_id   The attachment post ID.
 * @param string $focal_point_key The key of the focal point.
 * @param int    $preview_size    The size to use for preview calculations (default: 800).
 * @return array|WP_Error Array of crop previews or WP_Error on failure.
 */
function timu_preview_focal_point_crops( $attachment_id, $focal_point_key, $preview_size = 800 ) {
	// Get common aspect ratios.
	$aspect_ratios = timu_get_common_aspect_ratios();
	
	$previews = array();
	
	foreach ( $aspect_ratios as $ratio_key => $ratio_data ) {
		// Calculate dimensions based on aspect ratio.
		$target_width  = $preview_size;
		$target_height = round( $preview_size * ( $ratio_data['height'] / $ratio_data['width'] ) );
		
		// Get crop coordinates.
		$crop_data = timu_crop_to_focal_point( $attachment_id, $focal_point_key, $target_width, $target_height );
		
		if ( ! is_wp_error( $crop_data ) ) {
			$previews[ $ratio_key ] = array(
				'label'      => $ratio_data['label'],
				'ratio'      => $ratio_key,
				'crop_data'  => $crop_data,
			);
		}
	}
	
	return $previews;
}
