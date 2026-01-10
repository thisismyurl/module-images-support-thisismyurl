<?php
/**
 * TIMU DCT Fingerprint Handler
 * 
 * Handles DCT (Discrete Cosine Transform) frequency-domain fingerprinting.
 * 
 * @package Module_Images_Support
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class TIMU_DCT_Fingerprint {
    
    /**
     * Embed ownership ID in DCT coefficients.
     * 
     * Note: This is a simplified implementation. For production use,
     * consider using a proper DCT library or external tool.
     * 
     * @param int $attachment_id The attachment ID.
     * @param int $owner_id Owner/user ID to embed.
     * @return bool True on success, false on failure.
     */
    public static function embed_dct_fingerprint( $attachment_id, $owner_id ) {
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        // Only for JPEG (DCT-based compression)
        $mime_type = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/jpg' ) ) ) {
            return false;
        }
        
        // Check GD library support
        if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
            return false;
        }
        
        try {
            // Load image
            $image = @imagecreatefromjpeg( $file );
            if ( $image === false ) {
                return false;
            }
            
            // Get image dimensions
            $width = imagesx( $image );
            $height = imagesy( $image );
            
            // Create fingerprint data (32-bit binary representation of owner_id)
            $fingerprint = sprintf( '%032b', $owner_id );
            
            // Embed fingerprint in pseudo-DCT space
            // Note: This is a simplified approach using LSB modification in specific regions
            // For true DCT embedding, use a specialized library
            $embedded = self::embed_fingerprint_in_image( $image, $fingerprint, $width, $height );
            
            if ( ! $embedded ) {
                imagedestroy( $image );
                return false;
            }
            
            // Create backup of original file
            $backup_file = $file . '.original';
            if ( ! file_exists( $backup_file ) ) {
                @copy( $file, $backup_file );
            }
            
            // Save modified image
            $success = @imagejpeg( $image, $file, 90 );
            imagedestroy( $image );
            
            if ( ! $success ) {
                return false;
            }
            
            // Store fingerprint metadata
            update_post_meta( $attachment_id, '_timu_dct_fingerprint', array(
                'owner_id' => $owner_id,
                'embedded_at' => current_time( 'mysql' ),
                'recoverable' => true,
                'method' => 'simplified_dct',
            ) );
            
            return true;
            
        } catch ( Exception $e ) {
            error_log( 'TIMU DCT Fingerprint Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Embed fingerprint data into image using LSB in specific frequency regions.
     * 
     * @param resource $image GD image resource.
     * @param string   $fingerprint Binary fingerprint string.
     * @param int      $width Image width.
     * @param int      $height Image height.
     * @return bool True on success, false on failure.
     */
    private static function embed_fingerprint_in_image( $image, $fingerprint, $width, $height ) {
        $bit_index = 0;
        $fingerprint_length = strlen( $fingerprint );
        
        // Embed in middle-frequency regions (not corners, not center)
        // Using a pseudo-DCT approach by targeting specific image regions
        $start_x = (int) ( $width * 0.25 );
        $end_x = (int) ( $width * 0.75 );
        $start_y = (int) ( $height * 0.25 );
        $end_y = (int) ( $height * 0.75 );
        
        // Step through pixels in a pattern
        $step = max( 1, (int) sqrt( ( ( $end_x - $start_x ) * ( $end_y - $start_y ) ) / $fingerprint_length ) );
        
        for ( $y = $start_y; $y < $end_y && $bit_index < $fingerprint_length; $y += $step ) {
            for ( $x = $start_x; $x < $end_x && $bit_index < $fingerprint_length; $x += $step ) {
                // Get pixel color
                $rgb = imagecolorat( $image, $x, $y );
                $r = ( $rgb >> 16 ) & 0xFF;
                $g = ( $rgb >> 8 ) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Get bit to embed
                $bit = (int) $fingerprint[ $bit_index ];
                
                // Modify LSB of blue channel (least noticeable)
                $b = ( $b & ~1 ) | $bit;
                
                // Set modified pixel
                $new_color = imagecolorallocate( $image, $r, $g, $b );
                imagesetpixel( $image, $x, $y, $new_color );
                
                $bit_index++;
            }
        }
        
        return $bit_index >= $fingerprint_length;
    }
    
    /**
     * Detect DCT fingerprint from an image file.
     * 
     * @param string $file_path Path to the image file.
     * @return int|null Owner ID if found, null otherwise.
     */
    public static function detect_dct_fingerprint( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }
        
        // Check if it's a JPEG
        $image_info = @getimagesize( $file_path );
        if ( $image_info === false || $image_info[2] !== IMAGETYPE_JPEG ) {
            return null;
        }
        
        // Check GD library support
        if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
            return null;
        }
        
        try {
            // Load image
            $image = @imagecreatefromjpeg( $file_path );
            if ( $image === false ) {
                return null;
            }
            
            // Get image dimensions
            $width = imagesx( $image );
            $height = imagesy( $image );
            
            // Extract fingerprint
            $fingerprint = self::extract_fingerprint_from_image( $image, $width, $height );
            imagedestroy( $image );
            
            if ( $fingerprint === null ) {
                return null;
            }
            
            // Convert binary string to owner_id
            $owner_id = bindec( $fingerprint );
            
            return $owner_id > 0 ? $owner_id : null;
            
        } catch ( Exception $e ) {
            error_log( 'TIMU DCT Detection Error: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Extract fingerprint data from image.
     * 
     * @param resource $image GD image resource.
     * @param int      $width Image width.
     * @param int      $height Image height.
     * @return string|null Binary fingerprint string or null if failed.
     */
    private static function extract_fingerprint_from_image( $image, $width, $height ) {
        $extracted_bits = '';
        $fingerprint_length = 32; // 32-bit owner ID
        
        // Extract from same regions where we embedded
        $start_x = (int) ( $width * 0.25 );
        $end_x = (int) ( $width * 0.75 );
        $start_y = (int) ( $height * 0.25 );
        $end_y = (int) ( $height * 0.75 );
        
        // Calculate step
        $step = max( 1, (int) sqrt( ( ( $end_x - $start_x ) * ( $end_y - $start_y ) ) / $fingerprint_length ) );
        
        for ( $y = $start_y; $y < $end_y && strlen( $extracted_bits ) < $fingerprint_length; $y += $step ) {
            for ( $x = $start_x; $x < $end_x && strlen( $extracted_bits ) < $fingerprint_length; $x += $step ) {
                // Get pixel color
                $rgb = imagecolorat( $image, $x, $y );
                $b = $rgb & 0xFF;
                
                // Extract LSB from blue channel
                $bit = $b & 1;
                $extracted_bits .= $bit;
            }
        }
        
        return strlen( $extracted_bits ) >= $fingerprint_length ? substr( $extracted_bits, 0, $fingerprint_length ) : null;
    }
    
    /**
     * Verify DCT fingerprint for an attachment.
     * 
     * @param int $attachment_id The attachment ID.
     * @param int $expected_owner_id Expected owner ID.
     * @return bool True if fingerprint matches, false otherwise.
     */
    public static function verify_dct_fingerprint( $attachment_id, $expected_owner_id ) {
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        $detected_id = self::detect_dct_fingerprint( $file );
        
        return $detected_id === $expected_owner_id;
    }
}
