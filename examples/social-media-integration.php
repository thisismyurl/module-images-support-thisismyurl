<?php
/**
 * Example: Social Media Integration
 *
 * This example shows how to use focal points for social media image optimization.
 *
 * @package TIMU
 */

// This file is for demonstration purposes only.

/**
 * Generate optimized social media images using focal points.
 */
class Social_Media_Image_Generator {
	
	/**
	 * Social media image dimensions.
	 *
	 * @var array
	 */
	private static $dimensions = array(
		'facebook_post'   => array( 1200, 630 ),
		'facebook_story'  => array( 1080, 1920 ),
		'twitter_post'    => array( 1200, 675 ),
		'twitter_card'    => array( 1200, 628 ),
		'instagram_post'  => array( 1080, 1080 ),
		'instagram_story' => array( 1080, 1920 ),
		'linkedin_post'   => array( 1200, 627 ),
		'pinterest_pin'   => array( 1000, 1500 ),
	);
	
	/**
	 * Generate social media image for a specific platform.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $platform      The social media platform.
	 * @return string|WP_Error Path to generated image or error.
	 */
	public static function generate( $attachment_id, $platform ) {
		if ( ! isset( self::$dimensions[ $platform ] ) ) {
			return new WP_Error( 'invalid_platform', 'Invalid social media platform specified.' );
		}
		
		list( $width, $height ) = self::$dimensions[ $platform ];
		
		// Get recommended focal point for this platform
		$focal_key = timu_get_recommended_focal_point( $attachment_id, $platform );
		
		if ( ! $focal_key ) {
			return new WP_Error( 'no_focal_point', 'No focal point available for cropping.' );
		}
		
		// Generate cropped image
		return timu_generate_focal_point_crop(
			$attachment_id,
			$focal_key,
			$width,
			$height,
			$platform
		);
	}
	
	/**
	 * Get all available social media platforms.
	 *
	 * @return array Platform keys and labels.
	 */
	public static function get_platforms() {
		return array(
			'facebook_post'   => 'Facebook Post (1200×630)',
			'facebook_story'  => 'Facebook Story (1080×1920)',
			'twitter_post'    => 'Twitter Post (1200×675)',
			'twitter_card'    => 'Twitter Card (1200×628)',
			'instagram_post'  => 'Instagram Post (1080×1080)',
			'instagram_story' => 'Instagram Story (1080×1920)',
			'linkedin_post'   => 'LinkedIn Post (1200×627)',
			'pinterest_pin'   => 'Pinterest Pin (1000×1500)',
		);
	}
}

/**
 * Example: Generate images for all social media platforms.
 */
function example_generate_all_social_images( $attachment_id ) {
	$platforms = Social_Media_Image_Generator::get_platforms();
	$results   = array();
	
	foreach ( array_keys( $platforms ) as $platform ) {
		$result = Social_Media_Image_Generator::generate( $attachment_id, $platform );
		
		if ( ! is_wp_error( $result ) ) {
			$results[ $platform ] = $result;
			echo "✓ Generated {$platforms[$platform]}: {$result}\n";
		} else {
			echo "✗ Failed {$platforms[$platform]}: {$result->get_error_message()}\n";
		}
	}
	
	return $results;
}

/**
 * Example: Add Open Graph meta tags with focal point cropping.
 */
function example_add_og_tags() {
	add_action( 'wp_head', function() {
		if ( ! is_single() ) {
			return;
		}
		
		$thumbnail_id = get_post_thumbnail_id();
		
		if ( ! $thumbnail_id ) {
			return;
		}
		
		// Generate Facebook OG image
		$og_image = timu_generate_focal_point_crop(
			$thumbnail_id,
			timu_get_recommended_focal_point( $thumbnail_id, 'facebook' ),
			1200,
			630,
			'og'
		);
		
		if ( ! is_wp_error( $og_image ) ) {
			$og_image_url = wp_get_attachment_url( $thumbnail_id );
			// In real implementation, you'd need to convert file path to URL
			echo '<meta property="og:image" content="' . esc_url( $og_image_url ) . '" />' . "\n";
			echo '<meta property="og:image:width" content="1200" />' . "\n";
			echo '<meta property="og:image:height" content="630" />' . "\n";
		}
	} );
}

/**
 * Example: Custom focal point recommendations for social media.
 */
function example_social_media_recommendations() {
	add_filter( 'timu_recommended_focal_point', function( $recommended, $attachment_id, $context, $focal_points ) {
		// Map contexts to preferred focal point keys
		$context_map = array(
			'facebook'        => 'social',
			'facebook_post'   => 'social',
			'twitter'         => 'social',
			'twitter_post'    => 'social',
			'instagram'       => 'primary',
			'instagram_post'  => 'primary',
			'instagram_story' => 'face',
			'facebook_story'  => 'face',
			'pinterest'       => 'primary',
			'linkedin'        => 'professional',
		);
		
		// Check if we have a preferred focal point for this context
		if ( isset( $context_map[ $context ] ) ) {
			$preferred = $context_map[ $context ];
			
			if ( isset( $focal_points[ $preferred ] ) ) {
				return $preferred;
			}
		}
		
		// Fall back to default recommendation
		return $recommended;
	}, 10, 4 );
}

/**
 * Example: Batch generate social images for multiple attachments.
 */
function example_batch_generate_social_images( $attachment_ids, $platforms = null ) {
	if ( null === $platforms ) {
		$platforms = array_keys( Social_Media_Image_Generator::get_platforms() );
	}
	
	$total     = count( $attachment_ids ) * count( $platforms );
	$generated = 0;
	$failed    = 0;
	
	foreach ( $attachment_ids as $attachment_id ) {
		foreach ( $platforms as $platform ) {
			$result = Social_Media_Image_Generator::generate( $attachment_id, $platform );
			
			if ( ! is_wp_error( $result ) ) {
				$generated++;
			} else {
				$failed++;
			}
		}
	}
	
	return array(
		'total'     => $total,
		'generated' => $generated,
		'failed'    => $failed,
	);
}

/**
 * Example: Add admin interface for social media image generation.
 */
function example_admin_social_media_ui() {
	add_action( 'add_meta_boxes_attachment', function( $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return;
		}
		
		add_meta_box(
			'timu_social_media',
			__( 'Social Media Images', 'timu' ),
			function( $post ) {
				$platforms = Social_Media_Image_Generator::get_platforms();
				
				echo '<div class="timu-social-media-generator">';
				echo '<p>' . __( 'Generate optimized images for social media platforms:', 'timu' ) . '</p>';
				
				foreach ( $platforms as $key => $label ) {
					echo '<button type="button" class="button timu-generate-social" data-platform="' . esc_attr( $key ) . '">';
					echo esc_html( $label );
					echo '</button> ';
				}
				
				echo '<hr>';
				echo '<button type="button" class="button button-primary timu-generate-all-social">';
				echo __( 'Generate All', 'timu' );
				echo '</button>';
				echo '</div>';
			},
			'attachment',
			'side',
			'default'
		);
	} );
}

/**
 * Example: REST API endpoint for social media image generation.
 */
function example_register_rest_endpoint() {
	add_action( 'rest_api_init', function() {
		register_rest_route( 'timu/v1', '/social-image/(?P<id>\d+)/(?P<platform>[\w_]+)', array(
			'methods'             => 'POST',
			'callback'            => function( $request ) {
				$attachment_id = $request->get_param( 'id' );
				$platform      = $request->get_param( 'platform' );
				
				$result = Social_Media_Image_Generator::generate( $attachment_id, $platform );
				
				if ( is_wp_error( $result ) ) {
					return new WP_Error(
						$result->get_error_code(),
						$result->get_error_message(),
						array( 'status' => 400 )
					);
				}
				
				return array(
					'success' => true,
					'path'    => $result,
					'url'     => wp_get_attachment_url( $attachment_id ), // Simplified
				);
			},
			'permission_callback' => function() {
				return current_user_can( 'upload_files' );
			},
		) );
	} );
}
