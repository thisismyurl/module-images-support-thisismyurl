<?php
/**
 * TIMU Ownership Handler
 * 
 * Integrates all three layers of ownership protection.
 * 
 * @package Module_Images_Support
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class TIMU_Ownership {
    
    /**
     * Initialize ownership hooks.
     */
    public static function init() {
        add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'embed_ownership_on_upload' ), 10, 2 );
    }
    
    /**
     * Embed full ownership chain on image upload.
     * 
     * @param array $metadata Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array Modified metadata.
     */
    public static function embed_ownership_on_upload( $metadata, $attachment_id ) {
        // Get settings
        $settings = get_option( 'timu_settings', array() );
        
        // Get uploader info
        $post = get_post( $attachment_id );
        if ( ! $post ) {
            return $metadata;
        }
        
        $owner_id = $post->post_author;
        
        // Prepare copyright info
        $user = get_userdata( $owner_id );
        $copyright_info = array(
            'copyright' => ! empty( $settings['copyright_text'] ) ? $settings['copyright_text'] : sprintf( '© %s %s', gmdate( 'Y' ), get_bloginfo( 'name' ) ),
            'creator' => ! empty( $settings['creator_name'] ) ? $settings['creator_name'] : ( $user ? $user->display_name : '' ),
            'rights_usage' => isset( $settings['rights_usage'] ) ? $settings['rights_usage'] : 'All Rights Reserved',
            'usage_terms' => isset( $settings['usage_terms'] ) ? $settings['usage_terms'] : '',
            'license_url' => isset( $settings['license_url'] ) ? $settings['license_url'] : '',
            'contact_url' => home_url( '/contact' ),
        );
        
        // Embed ownership chain
        self::embed_full_ownership_chain( $attachment_id, $owner_id, $copyright_info );
        
        return $metadata;
    }
    
    /**
     * Embed full ownership chain across all layers.
     * 
     * @param int   $attachment_id The attachment ID.
     * @param int   $owner_id Owner/user ID.
     * @param array $copyright_info Copyright information.
     * @return array Results of each layer embedding.
     */
    public static function embed_full_ownership_chain( $attachment_id, $owner_id, $copyright_info = array() ) {
        $settings = get_option( 'timu_settings', array() );
        $results = array(
            'metadata' => false,
            'dct_fingerprint' => false,
            'lsb_fingerprint' => false,
        );
        
        // Layer 1: EXIF/IPTC metadata
        if ( ! empty( $settings['enable_metadata'] ) ) {
            $results['metadata'] = TIMU_Metadata::embed_copyright_metadata( $attachment_id, $copyright_info );
        }
        
        // Layer 2: DCT fingerprinting (frequency domain)
        if ( ! empty( $settings['enable_dct_fingerprint'] ) ) {
            $results['dct_fingerprint'] = TIMU_DCT_Fingerprint::embed_dct_fingerprint( $attachment_id, $owner_id );
        }
        
        // Layer 3: LSB steganography
        if ( ! empty( $settings['enable_lsb_fingerprint'] ) ) {
            $results['lsb_fingerprint'] = TIMU_LSB_Fingerprint::embed_lsb_fingerprint( $attachment_id, $owner_id );
        }
        
        // Store all three verification methods
        update_post_meta( $attachment_id, '_timu_ownership_layers', $results );
        update_post_meta( $attachment_id, '_timu_owner_id', $owner_id );
        
        // Fire action hook
        do_action( 'timu_ownership_embedded', $attachment_id, $owner_id, $results );
        
        return $results;
    }
    
    /**
     * Verify ownership across multiple layers.
     * 
     * @param string   $file_path Path to the image file.
     * @param int|null $expected_owner_id Expected owner ID (optional).
     * @return array Verification results.
     */
    public static function verify_ownership( $file_path, $expected_owner_id = null ) {
        $results = array(
            'metadata' => TIMU_Metadata::read_copyright_metadata( $file_path ),
            'dct_fingerprint' => TIMU_DCT_Fingerprint::detect_dct_fingerprint( $file_path ),
            'lsb_fingerprint' => TIMU_LSB_Fingerprint::detect_lsb_fingerprint( $file_path ),
        );
        
        // Count how many layers detected a valid fingerprint
        $detected_layers = 0;
        $matching_layers = 0;
        
        foreach ( $results as $layer => $detected ) {
            if ( $layer === 'metadata' ) {
                // Metadata returns array, check if it has content
                if ( ! empty( $detected ) ) {
                    $detected_layers++;
                }
            } else {
                // Fingerprints return owner ID
                if ( $detected !== null && $detected > 0 ) {
                    $detected_layers++;
                    
                    if ( $expected_owner_id !== null && $detected === $expected_owner_id ) {
                        $matching_layers++;
                    }
                }
            }
        }
        
        // Determine verification status
        $verified = false;
        if ( $expected_owner_id !== null ) {
            // If expected ID provided, need at least 2 matching layers
            $verified = $matching_layers >= 2;
        } else {
            // If no expected ID, just check if ownership info was detected
            $verified = $detected_layers >= 1;
        }
        
        return array(
            'verified' => $verified,
            'confidence' => $expected_owner_id !== null ? ( $matching_layers / 2 ) : ( $detected_layers / 3 ),
            'layers' => $results,
            'detected_layers' => $detected_layers,
            'matching_layers' => $matching_layers,
        );
    }
    
    /**
     * Verify ownership for an attachment.
     * 
     * @param int $attachment_id The attachment ID.
     * @return array Verification results.
     */
    public static function verify_attachment_ownership( $attachment_id ) {
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return array(
                'verified' => false,
                'confidence' => 0,
                'layers' => array(),
                'error' => 'File not found',
            );
        }
        
        $expected_owner_id = get_post_meta( $attachment_id, '_timu_owner_id', true );
        
        return self::verify_ownership( $file, $expected_owner_id );
    }
    
    /**
     * Add visible copyright overlay to an image.
     * 
     * @param int    $attachment_id The attachment ID.
     * @param string $position Overlay position (default: 'bottom-right').
     * @return bool True on success, false on failure.
     */
    public static function add_copyright_overlay( $attachment_id, $position = 'bottom-right' ) {
        $copyright_info = get_post_meta( $attachment_id, '_timu_copyright_info', true );
        
        if ( empty( $copyright_info['copyright'] ) ) {
            return false;
        }
        
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        // Get mime type
        $mime_type = get_post_mime_type( $attachment_id );
        
        try {
            // Load image
            if ( $mime_type === 'image/png' ) {
                $image = @imagecreatefrompng( $file );
            } elseif ( in_array( $mime_type, array( 'image/jpeg', 'image/jpg' ) ) ) {
                $image = @imagecreatefromjpeg( $file );
            } else {
                return false;
            }
            
            if ( $image === false ) {
                return false;
            }
            
            // Add text overlay
            $text = $copyright_info['copyright'];
            $font_size = 3; // Built-in GD font size
            $text_color = imagecolorallocatealpha( $image, 255, 255, 255, 50 ); // Semi-transparent white
            $bg_color = imagecolorallocatealpha( $image, 0, 0, 0, 75 ); // Semi-transparent black
            
            // Calculate text dimensions
            $text_width = imagefontwidth( $font_size ) * strlen( $text );
            $text_height = imagefontheight( $font_size );
            $padding = 5;
            
            // Get image dimensions
            $img_width = imagesx( $image );
            $img_height = imagesy( $image );
            
            // Calculate position
            switch ( $position ) {
                case 'top-left':
                    $x = $padding;
                    $y = $padding;
                    break;
                case 'top-right':
                    $x = $img_width - $text_width - $padding;
                    $y = $padding;
                    break;
                case 'bottom-left':
                    $x = $padding;
                    $y = $img_height - $text_height - $padding;
                    break;
                case 'bottom-right':
                default:
                    $x = $img_width - $text_width - $padding;
                    $y = $img_height - $text_height - $padding;
                    break;
            }
            
            // Draw background rectangle
            imagefilledrectangle( 
                $image, 
                $x - 2, 
                $y - 2, 
                $x + $text_width + 2, 
                $y + $text_height + 2, 
                $bg_color 
            );
            
            // Draw text
            imagestring( $image, $font_size, $x, $y, $text, $text_color );
            
            // Save image
            if ( $mime_type === 'image/png' ) {
                $success = @imagepng( $image, $file );
            } else {
                $success = @imagejpeg( $image, $file, 90 );
            }
            
            imagedestroy( $image );
            
            return $success;
            
        } catch ( Exception $e ) {
            error_log( 'TIMU Copyright Overlay Error: ' . $e->getMessage() );
            return false;
        }
    }
}

// Initialize
TIMU_Ownership::init();
