<?php
/**
 * Example: Basic Focal Point Usage
 *
 * This example demonstrates the basic usage of focal points API.
 *
 * @package TIMU
 */

// This file is for demonstration purposes only.
// Don't include in production - these are just examples.

// Example 1: Setting focal points for an image
function example_set_focal_points() {
	// Assume we have an attachment ID
	$attachment_id = 123;
	
	// Define multiple focal points
	$focal_points = array(
		'primary' => array(
			'x'     => 50,  // Center horizontally
			'y'     => 40,  // Slightly above center
			'label' => 'Main subject',
		),
		'secondary' => array(
			'x'     => 80,  // Right side
			'y'     => 20,  // Upper area
			'label' => 'Background element',
		),
		'face' => array(
			'x'     => 45,
			'y'     => 35,
			'label' => 'Person\'s face',
		),
	);
	
	// Save the focal points
	$result = timu_set_focal_points( $attachment_id, $focal_points );
	
	if ( $result ) {
		echo "Focal points saved successfully!\n";
	}
}

// Example 2: Getting and using focal points
function example_get_focal_points() {
	$attachment_id = 123;
	
	// Get all focal points
	$focal_points = timu_get_focal_points( $attachment_id );
	
	if ( ! empty( $focal_points ) ) {
		echo "Found " . count( $focal_points ) . " focal points:\n";
		
		foreach ( $focal_points as $key => $point ) {
			echo "- {$key}: {$point['label']} at ({$point['x']}%, {$point['y']}%)\n";
		}
	}
	
	// Get a specific focal point
	$primary = timu_get_focal_point( $attachment_id, 'primary' );
	
	if ( $primary ) {
		echo "\nPrimary focal point: {$primary['label']} at ({$primary['x']}%, {$primary['y']}%)\n";
	}
}

// Example 3: Adding and removing individual focal points
function example_add_remove_focal_points() {
	$attachment_id = 123;
	
	// Add a new focal point
	timu_add_focal_point( $attachment_id, 'logo', 85, 90, 'Company logo' );
	echo "Added 'logo' focal point\n";
	
	// Remove a focal point
	timu_remove_focal_point( $attachment_id, 'secondary' );
	echo "Removed 'secondary' focal point\n";
	
	// Delete all focal points
	// timu_delete_focal_points( $attachment_id );
}

// Example 4: Cropping to a focal point
function example_crop_to_focal_point() {
	$attachment_id = 123;
	
	// Calculate crop coordinates for Instagram (square)
	$crop_data = timu_crop_to_focal_point( 
		$attachment_id, 
		'primary',  // Use primary focal point
		1080,       // Instagram post width
		1080        // Instagram post height
	);
	
	if ( ! is_wp_error( $crop_data ) ) {
		echo "Crop coordinates:\n";
		echo "- X: {$crop_data['x']}px\n";
		echo "- Y: {$crop_data['y']}px\n";
		echo "- Width: {$crop_data['width']}px\n";
		echo "- Height: {$crop_data['height']}px\n";
	} else {
		echo "Error: " . $crop_data->get_error_message() . "\n";
	}
}

// Example 5: Generating a cropped image
function example_generate_cropped_image() {
	$attachment_id = 123;
	
	// Generate a cropped image for Facebook Open Graph
	$cropped_path = timu_generate_focal_point_crop(
		$attachment_id,
		'primary',
		1200,  // Facebook OG width
		630,   // Facebook OG height
		'facebook-og'
	);
	
	if ( ! is_wp_error( $cropped_path ) ) {
		echo "Cropped image saved to: {$cropped_path}\n";
	} else {
		echo "Error: " . $cropped_path->get_error_message() . "\n";
	}
}

// Example 6: Context-aware focal point selection
function example_context_aware_cropping() {
	$attachment_id = 123;
	
	// Get recommended focal point for mobile context
	$focal_key = timu_get_recommended_focal_point( $attachment_id, 'mobile' );
	
	echo "Recommended focal point for mobile: {$focal_key}\n";
	
	// Use it for cropping
	$crop_data = timu_crop_to_focal_point( $attachment_id, $focal_key, 750, 1334 );
	
	if ( ! is_wp_error( $crop_data ) ) {
		echo "Mobile crop calculated successfully\n";
	}
}

// Example 7: Previewing crops for multiple aspect ratios
function example_preview_crops() {
	$attachment_id = 123;
	
	// Get crop previews for common aspect ratios
	$previews = timu_preview_focal_point_crops( $attachment_id, 'primary', 800 );
	
	if ( ! empty( $previews ) ) {
		echo "Crop previews:\n";
		
		foreach ( $previews as $ratio => $preview ) {
			echo "- {$preview['label']} ({$ratio}):\n";
			echo "  Crop: {$preview['crop_data']['width']} × {$preview['crop_data']['height']}px\n";
		}
	}
}

// Example 8: Using hooks - Custom focal point recommendation
function example_custom_recommendation() {
	add_filter( 'timu_recommended_focal_point', function( $recommended, $attachment_id, $context, $focal_points ) {
		// Custom logic for Instagram context
		if ( 'instagram' === $context && isset( $focal_points['social'] ) ) {
			return 'social';
		}
		
		// For mobile, prefer face focal point if it exists
		if ( 'mobile' === $context && isset( $focal_points['face'] ) ) {
			return 'face';
		}
		
		return $recommended;
	}, 10, 4 );
}

// Example 9: Using action hooks - Log focal point changes
function example_log_focal_point_changes() {
	add_action( 'timu_focal_points_updated', function( $attachment_id, $focal_points ) {
		error_log( sprintf(
			'Focal points updated for attachment %d: %d points defined',
			$attachment_id,
			count( $focal_points )
		) );
	}, 10, 2 );
	
	add_action( 'timu_focal_point_crop_generated', function( $attachment_id, $focal_point_key, $saved_path ) {
		error_log( sprintf(
			'Crop generated for attachment %d using focal point "%s": %s',
			$attachment_id,
			$focal_point_key,
			$saved_path
		) );
	}, 10, 3 );
}

// Example 10: Integration with watermarking
function example_watermark_integration() {
	add_filter( 'watermark_position', function( $position, $attachment_id ) {
		$focal_points = timu_get_focal_points( $attachment_id );
		
		// If there's a primary focal point, place watermark away from it
		if ( isset( $focal_points['primary'] ) ) {
			$focal = $focal_points['primary'];
			
			// If focal point is on left side, place watermark on right
			if ( $focal['x'] < 50 ) {
				$position = ( $focal['y'] < 50 ) ? 'bottom-right' : 'top-right';
			} else {
				$position = ( $focal['y'] < 50 ) ? 'bottom-left' : 'top-left';
			}
		}
		
		return $position;
	}, 10, 2 );
}
