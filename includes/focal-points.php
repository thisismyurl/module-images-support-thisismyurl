<?php
/**
 * Focal Points Management Functions
 *
 * @package TIMU
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save multiple focal points for an image attachment.
 *
 * @param int   $attachment_id The attachment post ID.
 * @param array $focal_points  Array of focal points with keys and data.
 *                             Example: [
 *                                 'primary' => ['x' => 50, 'y' => 40, 'label' => 'Main subject'],
 *                                 'secondary' => ['x' => 80, 'y' => 20, 'label' => 'Background'],
 *                             ]
 * @return bool True on success, false on failure.
 */
function timu_set_focal_points( $attachment_id, $focal_points = array() ) {
	// Validate attachment ID.
	if ( ! $attachment_id || ! is_numeric( $attachment_id ) ) {
		return false;
	}
	
	// Validate that it's an attachment.
	if ( 'attachment' !== get_post_type( $attachment_id ) ) {
		return false;
	}
	
	// Validate and sanitize focal points data.
	$sanitized_points = array();
	foreach ( $focal_points as $key => $point ) {
		// Validate required fields.
		if ( ! isset( $point['x'] ) || ! isset( $point['y'] ) ) {
			continue;
		}
		
		// Sanitize key.
		$sanitized_key = sanitize_key( $key );
		
		// Sanitize and validate coordinates (should be 0-100).
		$x = floatval( $point['x'] );
		$y = floatval( $point['y'] );
		
		// Clamp values between 0 and 100.
		$x = max( 0, min( 100, $x ) );
		$y = max( 0, min( 100, $y ) );
		
		$sanitized_points[ $sanitized_key ] = array(
			'x'     => $x,
			'y'     => $y,
			'label' => isset( $point['label'] ) ? sanitize_text_field( $point['label'] ) : '',
		);
	}
	
	// Update post meta.
	$result = update_post_meta( $attachment_id, '_timu_focal_points', $sanitized_points );
	
	// Trigger action hook for extensibility.
	do_action( 'timu_focal_points_updated', $attachment_id, $sanitized_points );
	
	return false !== $result;
}

/**
 * Get focal points for an image attachment.
 *
 * @param int $attachment_id The attachment post ID.
 * @return array Array of focal points or empty array if none exist.
 */
function timu_get_focal_points( $attachment_id ) {
	// Validate attachment ID.
	if ( ! $attachment_id || ! is_numeric( $attachment_id ) ) {
		return array();
	}
	
	// Get focal points from post meta.
	$focal_points = get_post_meta( $attachment_id, '_timu_focal_points', true );
	
	// Return empty array if no focal points exist.
	if ( ! is_array( $focal_points ) ) {
		return array();
	}
	
	return $focal_points;
}

/**
 * Delete all focal points for an image attachment.
 *
 * @param int $attachment_id The attachment post ID.
 * @return bool True on success, false on failure.
 */
function timu_delete_focal_points( $attachment_id ) {
	// Validate attachment ID.
	if ( ! $attachment_id || ! is_numeric( $attachment_id ) ) {
		return false;
	}
	
	// Delete post meta.
	$result = delete_post_meta( $attachment_id, '_timu_focal_points' );
	
	// Trigger action hook for extensibility.
	do_action( 'timu_focal_points_deleted', $attachment_id );
	
	return $result;
}

/**
 * Add a single focal point to an image attachment.
 *
 * @param int    $attachment_id    The attachment post ID.
 * @param string $focal_point_key  The key for the focal point.
 * @param float  $x                The x coordinate (0-100).
 * @param float  $y                The y coordinate (0-100).
 * @param string $label            Optional label for the focal point.
 * @return bool True on success, false on failure.
 */
function timu_add_focal_point( $attachment_id, $focal_point_key, $x, $y, $label = '' ) {
	// Get existing focal points.
	$focal_points = timu_get_focal_points( $attachment_id );
	
	// Sanitize key.
	$sanitized_key = sanitize_key( $focal_point_key );
	
	// Sanitize and validate coordinates.
	$x = max( 0, min( 100, floatval( $x ) ) );
	$y = max( 0, min( 100, floatval( $y ) ) );
	
	// Add new focal point.
	$focal_points[ $sanitized_key ] = array(
		'x'     => $x,
		'y'     => $y,
		'label' => sanitize_text_field( $label ),
	);
	
	// Save updated focal points.
	return timu_set_focal_points( $attachment_id, $focal_points );
}

/**
 * Remove a single focal point from an image attachment.
 *
 * @param int    $attachment_id   The attachment post ID.
 * @param string $focal_point_key The key of the focal point to remove.
 * @return bool True on success, false on failure.
 */
function timu_remove_focal_point( $attachment_id, $focal_point_key ) {
	// Get existing focal points.
	$focal_points = timu_get_focal_points( $attachment_id );
	
	// Sanitize key.
	$sanitized_key = sanitize_key( $focal_point_key );
	
	// Remove focal point if it exists.
	if ( isset( $focal_points[ $sanitized_key ] ) ) {
		unset( $focal_points[ $sanitized_key ] );
		return timu_set_focal_points( $attachment_id, $focal_points );
	}
	
	return false;
}

/**
 * Get a specific focal point by key.
 *
 * @param int    $attachment_id   The attachment post ID.
 * @param string $focal_point_key The key of the focal point.
 * @return array|null Focal point data or null if not found.
 */
function timu_get_focal_point( $attachment_id, $focal_point_key ) {
	// Get all focal points.
	$focal_points = timu_get_focal_points( $attachment_id );
	
	// Sanitize key.
	$sanitized_key = sanitize_key( $focal_point_key );
	
	// Return specific focal point or null.
	return isset( $focal_points[ $sanitized_key ] ) ? $focal_points[ $sanitized_key ] : null;
}
