<?php
/**
 * TIMU LSB Fingerprint Handler
 * 
 * Handles LSB (Least Significant Bit) steganography for ownership fingerprinting.
 * 
 * @package Module_Images_Support
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class TIMU_LSB_Fingerprint {
    
    /**
     * Embed ownership ID using LSB steganography.
     * 
     * @param int $attachment_id The attachment ID.
     * @param int $owner_id Owner/user ID to embed.
     * @return bool True on success, false on failure.
     */
    public static function embed_lsb_fingerprint( $attachment_id, $owner_id ) {
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        // Support JPEG and PNG
        $mime_type = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/jpg', 'image/png' ) ) ) {
            return false;
        }
        
        // Check GD library support
        if ( ! function_exists( 'imagecreatefromjpeg' ) || ! function_exists( 'imagecreatefrompng' ) ) {
            return false;
        }
        
        try {
            // Load image based on type
            if ( $mime_type === 'image/png' ) {
                $image = @imagecreatefrompng( $file );
            } else {
                $image = @imagecreatefromjpeg( $file );
            }
            
            if ( $image === false ) {
                return false;
            }
            
            // Get image dimensions
            $width = imagesx( $image );
            $height = imagesy( $image );
            
            // Create fingerprint payload
            // Format: MAGIC_NUMBER(16bit) + OWNER_ID(32bit) + CHECKSUM(16bit)
            $magic = 0x5449; // "TI" in hex
            $checksum = crc32( (string) $owner_id ) & 0xFFFF;
            
            $payload = sprintf( '%016b', $magic ) . 
                      sprintf( '%032b', $owner_id ) . 
                      sprintf( '%016b', $checksum );
            
            // Embed payload using LSB
            $embedded = self::embed_payload_lsb( $image, $payload, $width, $height );
            
            if ( ! $embedded ) {
                imagedestroy( $image );
                return false;
            }
            
            // Create backup of original file
            $backup_file = $file . '.lsb_original';
            if ( ! file_exists( $backup_file ) ) {
                @copy( $file, $backup_file );
            }
            
            // Save modified image
            if ( $mime_type === 'image/png' ) {
                $success = @imagepng( $image, $file, 9 );
            } else {
                $success = @imagejpeg( $image, $file, 95 );
            }
            
            imagedestroy( $image );
            
            if ( ! $success ) {
                return false;
            }
            
            // Store fingerprint metadata
            update_post_meta( $attachment_id, '_timu_lsb_fingerprint', array(
                'owner_id' => $owner_id,
                'embedded_at' => current_time( 'mysql' ),
                'recoverable' => true,
                'payload_bits' => strlen( $payload ),
            ) );
            
            return true;
            
        } catch ( Exception $e ) {
            error_log( 'TIMU LSB Fingerprint Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Embed payload in image using LSB steganography.
     * 
     * @param resource $image GD image resource.
     * @param string   $payload Binary payload string.
     * @param int      $width Image width.
     * @param int      $height Image height.
     * @return bool True on success, false on failure.
     */
    private static function embed_payload_lsb( $image, $payload, $width, $height ) {
        $bit_index = 0;
        $payload_length = strlen( $payload );
        
        // Check if image has enough capacity
        $max_bits = $width * $height * 3; // 3 channels (RGB)
        if ( $payload_length > $max_bits ) {
            return false;
        }
        
        // Embed in top-left corner region (survives cropping from bottom-right)
        for ( $y = 0; $y < $height && $bit_index < $payload_length; $y++ ) {
            for ( $x = 0; $x < $width && $bit_index < $payload_length; $x++ ) {
                // Get pixel color
                $rgb = imagecolorat( $image, $x, $y );
                $r = ( $rgb >> 16 ) & 0xFF;
                $g = ( $rgb >> 8 ) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Embed in R channel
                if ( $bit_index < $payload_length ) {
                    $bit = (int) $payload[ $bit_index++ ];
                    $r = ( $r & ~1 ) | $bit;
                }
                
                // Embed in G channel
                if ( $bit_index < $payload_length ) {
                    $bit = (int) $payload[ $bit_index++ ];
                    $g = ( $g & ~1 ) | $bit;
                }
                
                // Embed in B channel
                if ( $bit_index < $payload_length ) {
                    $bit = (int) $payload[ $bit_index++ ];
                    $b = ( $b & ~1 ) | $bit;
                }
                
                // Set modified pixel
                $new_color = imagecolorallocate( $image, $r, $g, $b );
                imagesetpixel( $image, $x, $y, $new_color );
            }
        }
        
        return $bit_index >= $payload_length;
    }
    
    /**
     * Detect LSB fingerprint from an image file.
     * 
     * @param string $file_path Path to the image file.
     * @return int|null Owner ID if found and valid, null otherwise.
     */
    public static function detect_lsb_fingerprint( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }
        
        // Get image info
        $image_info = @getimagesize( $file_path );
        if ( $image_info === false ) {
            return null;
        }
        
        // Check GD library support
        if ( ! function_exists( 'imagecreatefromjpeg' ) || ! function_exists( 'imagecreatefrompng' ) ) {
            return null;
        }
        
        try {
            // Load image based on type
            if ( $image_info[2] === IMAGETYPE_PNG ) {
                $image = @imagecreatefrompng( $file_path );
            } elseif ( $image_info[2] === IMAGETYPE_JPEG ) {
                $image = @imagecreatefromjpeg( $file_path );
            } else {
                return null;
            }
            
            if ( $image === false ) {
                return null;
            }
            
            // Get image dimensions
            $width = imagesx( $image );
            $height = imagesy( $image );
            
            // Extract payload (64 bits total: 16 magic + 32 owner_id + 16 checksum)
            $payload = self::extract_payload_lsb( $image, $width, $height, 64 );
            imagedestroy( $image );
            
            if ( $payload === null || strlen( $payload ) < 64 ) {
                return null;
            }
            
            // Parse payload
            $magic = bindec( substr( $payload, 0, 16 ) );
            $owner_id = bindec( substr( $payload, 16, 32 ) );
            $checksum = bindec( substr( $payload, 48, 16 ) );
            
            // Verify magic number
            if ( $magic !== 0x5449 ) {
                return null;
            }
            
            // Verify checksum
            $expected_checksum = crc32( (string) $owner_id ) & 0xFFFF;
            if ( $checksum !== $expected_checksum ) {
                return null;
            }
            
            return $owner_id > 0 ? $owner_id : null;
            
        } catch ( Exception $e ) {
            error_log( 'TIMU LSB Detection Error: ' . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Extract payload from image using LSB steganography.
     * 
     * @param resource $image GD image resource.
     * @param int      $width Image width.
     * @param int      $height Image height.
     * @param int      $bits_to_extract Number of bits to extract.
     * @return string|null Binary payload string or null if failed.
     */
    private static function extract_payload_lsb( $image, $width, $height, $bits_to_extract ) {
        $extracted_bits = '';
        $bit_index = 0;
        
        // Extract from top-left corner region
        for ( $y = 0; $y < $height && $bit_index < $bits_to_extract; $y++ ) {
            for ( $x = 0; $x < $width && $bit_index < $bits_to_extract; $x++ ) {
                // Get pixel color
                $rgb = imagecolorat( $image, $x, $y );
                $r = ( $rgb >> 16 ) & 0xFF;
                $g = ( $rgb >> 8 ) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Extract from R channel
                if ( $bit_index < $bits_to_extract ) {
                    $extracted_bits .= ( $r & 1 );
                    $bit_index++;
                }
                
                // Extract from G channel
                if ( $bit_index < $bits_to_extract ) {
                    $extracted_bits .= ( $g & 1 );
                    $bit_index++;
                }
                
                // Extract from B channel
                if ( $bit_index < $bits_to_extract ) {
                    $extracted_bits .= ( $b & 1 );
                    $bit_index++;
                }
            }
        }
        
        return strlen( $extracted_bits ) >= $bits_to_extract ? substr( $extracted_bits, 0, $bits_to_extract ) : null;
    }
    
    /**
     * Verify LSB fingerprint for an attachment.
     * 
     * @param int $attachment_id The attachment ID.
     * @param int $expected_owner_id Expected owner ID.
     * @return bool True if fingerprint matches, false otherwise.
     */
    public static function verify_lsb_fingerprint( $attachment_id, $expected_owner_id ) {
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        $detected_id = self::detect_lsb_fingerprint( $file );
        
        return $detected_id === $expected_owner_id;
    }
}
