<?php
/**
 * TIMU Metadata Handler
 * 
 * Handles EXIF/IPTC metadata embedding for copyright information.
 * 
 * @package Module_Images_Support
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class TIMU_Metadata {
    
    /**
     * Embed copyright metadata into an image file.
     * 
     * @param int   $attachment_id The attachment ID.
     * @param array $copyright_info Copyright information array.
     * @return bool True on success, false on failure.
     */
    public static function embed_copyright_metadata( $attachment_id, $copyright_info = array() ) {
        $file = get_attached_file( $attachment_id );
        
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        // Validate image type
        $mime_type = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/jpg', 'image/png' ) ) ) {
            return false;
        }
        
        // Default copyright info
        $defaults = array(
            'copyright' => '',
            'creator' => '',
            'rights_usage' => 'All Rights Reserved',
            'contact_url' => '',
            'license_url' => '',
            'usage_terms' => '',
        );
        
        $copyright_info = wp_parse_args( $copyright_info, $defaults );
        
        // Write EXIF/IPTC data using exiftool or WordPress metadata functions
        $success = self::write_image_metadata( $file, array(
            'Copyright' => $copyright_info['copyright'],
            'Creator' => $copyright_info['creator'],
            'UsageTerms' => $copyright_info['usage_terms'],
            'CopyrightNotice' => $copyright_info['rights_usage'],
            'AttributionURL' => $copyright_info['contact_url'],
            'LicenseURL' => $copyright_info['license_url'],
        ) );
        
        // Store in postmeta for quick access
        if ( $success ) {
            update_post_meta( $attachment_id, '_timu_copyright_info', $copyright_info );
            update_post_meta( $attachment_id, '_timu_copyright_embedded_at', current_time( 'mysql' ) );
        }
        
        return $success;
    }
    
    /**
     * Write image metadata to file.
     * 
     * @param string $file_path Path to the image file.
     * @param array  $metadata Metadata array to write.
     * @return bool True on success, false on failure.
     */
    private static function write_image_metadata( $file_path, $metadata ) {
        // Check if exiftool is available
        if ( self::is_exiftool_available() ) {
            return self::write_metadata_with_exiftool( $file_path, $metadata );
        }
        
        // Fallback to IPTC for JPEG
        if ( preg_match( '/\.jpe?g$/i', $file_path ) ) {
            return self::write_iptc_metadata( $file_path, $metadata );
        }
        
        return false;
    }
    
    /**
     * Check if exiftool is available on the system.
     * 
     * @return bool True if available, false otherwise.
     */
    private static function is_exiftool_available() {
        static $available = null;
        
        if ( $available === null ) {
            $output = array();
            $return_var = 0;
            @exec( 'exiftool -ver 2>&1', $output, $return_var );
            $available = ( $return_var === 0 );
        }
        
        return $available;
    }
    
    /**
     * Write metadata using exiftool.
     * 
     * @param string $file_path Path to the image file.
     * @param array  $metadata Metadata array to write.
     * @return bool True on success, false on failure.
     */
    private static function write_metadata_with_exiftool( $file_path, $metadata ) {
        $commands = array();
        
        foreach ( $metadata as $key => $value ) {
            if ( ! empty( $value ) ) {
                $escaped_value = escapeshellarg( $value );
                $commands[] = "-{$key}={$escaped_value}";
            }
        }
        
        if ( empty( $commands ) ) {
            return false;
        }
        
        $escaped_file = escapeshellarg( $file_path );
        $command = 'exiftool -overwrite_original ' . implode( ' ', $commands ) . ' ' . $escaped_file . ' 2>&1';
        
        $output = array();
        $return_var = 0;
        @exec( $command, $output, $return_var );
        
        return $return_var === 0;
    }
    
    /**
     * Write IPTC metadata to JPEG file.
     * 
     * @param string $file_path Path to the JPEG file.
     * @param array  $metadata Metadata array to write.
     * @return bool True on success, false on failure.
     */
    private static function write_iptc_metadata( $file_path, $metadata ) {
        $iptc_data = array();
        
        // Map metadata keys to IPTC tags
        $mapping = array(
            'Copyright' => '2#116',      // Copyright Notice
            'Creator' => '2#080',        // By-line (Author)
            'CopyrightNotice' => '2#116',
            'UsageTerms' => '2#055',     // Rights Usage Terms
        );
        
        foreach ( $mapping as $key => $iptc_tag ) {
            if ( ! empty( $metadata[ $key ] ) ) {
                $iptc_data[ $iptc_tag ] = $metadata[ $key ];
            }
        }
        
        if ( empty( $iptc_data ) ) {
            return false;
        }
        
        // Read existing image data
        $image_data = file_get_contents( $file_path );
        if ( $image_data === false ) {
            return false;
        }
        
        // Get existing IPTC data
        getimagesize( $file_path, $info );
        
        // Build IPTC data string
        $iptc_string = '';
        foreach ( $iptc_data as $tag => $value ) {
            $tag_parts = explode( '#', $tag );
            $iptc_string .= self::iptc_make_tag( $tag_parts[0], $tag_parts[1], $value );
        }
        
        // Embed IPTC data
        $content = iptcembed( $iptc_string, $file_path );
        
        if ( $content === false ) {
            return false;
        }
        
        // Write back to file
        $success = file_put_contents( $file_path, $content );
        
        return $success !== false;
    }
    
    /**
     * Create IPTC tag data.
     * 
     * @param int    $rec Record number.
     * @param int    $data_set Dataset number.
     * @param string $value Value to set.
     * @return string IPTC tag data.
     */
    private static function iptc_make_tag( $rec, $data_set, $value ) {
        $length = strlen( $value );
        $retval = chr( 0x1C ) . chr( $rec ) . chr( $data_set );
        
        if ( $length < 0x8000 ) {
            $retval .= chr( $length >> 8 ) . chr( $length & 0xFF );
        } else {
            $retval .= chr( 0x80 ) . 
                      chr( 0x04 ) . 
                      chr( ( $length >> 24 ) & 0xFF ) . 
                      chr( ( $length >> 16 ) & 0xFF ) . 
                      chr( ( $length >> 8 ) & 0xFF ) . 
                      chr( $length & 0xFF );
        }
        
        return $retval . $value;
    }
    
    /**
     * Read copyright metadata from an image file.
     * 
     * @param string $file_path Path to the image file.
     * @return array|null Copyright metadata array or null if not found.
     */
    public static function read_copyright_metadata( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }
        
        // Try reading with exiftool first
        if ( self::is_exiftool_available() ) {
            return self::read_metadata_with_exiftool( $file_path );
        }
        
        // Fallback to EXIF functions
        return self::read_metadata_with_exif( $file_path );
    }
    
    /**
     * Read metadata using exiftool.
     * 
     * @param string $file_path Path to the image file.
     * @return array|null Metadata array or null if not found.
     */
    private static function read_metadata_with_exiftool( $file_path ) {
        $escaped_file = escapeshellarg( $file_path );
        $command = "exiftool -Copyright -Creator -UsageTerms -CopyrightNotice -j {$escaped_file} 2>&1";
        
        $output = array();
        $return_var = 0;
        @exec( $command, $output, $return_var );
        
        if ( $return_var === 0 && ! empty( $output ) ) {
            $json = implode( '', $output );
            $data = json_decode( $json, true );
            
            if ( ! empty( $data[0] ) ) {
                return array(
                    'copyright' => isset( $data[0]['Copyright'] ) ? $data[0]['Copyright'] : '',
                    'creator' => isset( $data[0]['Creator'] ) ? $data[0]['Creator'] : '',
                    'usage_terms' => isset( $data[0]['UsageTerms'] ) ? $data[0]['UsageTerms'] : '',
                    'rights_usage' => isset( $data[0]['CopyrightNotice'] ) ? $data[0]['CopyrightNotice'] : '',
                );
            }
        }
        
        return null;
    }
    
    /**
     * Read metadata using PHP EXIF functions.
     * 
     * @param string $file_path Path to the image file.
     * @return array|null Metadata array or null if not found.
     */
    private static function read_metadata_with_exif( $file_path ) {
        if ( ! function_exists( 'exif_read_data' ) ) {
            return null;
        }
        
        $exif = @exif_read_data( $file_path );
        
        if ( $exif === false ) {
            return null;
        }
        
        $metadata = array();
        
        if ( ! empty( $exif['Copyright'] ) ) {
            $metadata['copyright'] = $exif['Copyright'];
        }
        
        if ( ! empty( $exif['Artist'] ) ) {
            $metadata['creator'] = $exif['Artist'];
        }
        
        return ! empty( $metadata ) ? $metadata : null;
    }
}
