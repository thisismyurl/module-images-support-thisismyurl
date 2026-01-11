<?php
/**
 * TIMU DMCA Evidence Handler
 * 
 * Generates proof-of-ownership evidence packages for DMCA claims.
 * 
 * @package Module_Images_Support
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class TIMU_DMCA {
    
    /**
     * Generate DMCA evidence package.
     * 
     * @param int    $attachment_id The attachment ID.
     * @param string $infringing_url URL of the infringing image.
     * @return array Evidence package data.
     */
    public static function generate_dmca_evidence( $attachment_id, $infringing_url = '' ) {
        $post = get_post( $attachment_id );
        
        if ( ! $post ) {
            return array( 'error' => 'Invalid attachment ID' );
        }
        
        $file = get_attached_file( $attachment_id );
        $file_hash = '';
        
        if ( file_exists( $file ) ) {
            $file_hash = md5_file( $file );
        }
        
        // Get ownership verification
        $verification = TIMU_Ownership::verify_attachment_ownership( $attachment_id );
        
        // Get all metadata
        $copyright_info = get_post_meta( $attachment_id, '_timu_copyright_info', true );
        $ownership_layers = get_post_meta( $attachment_id, '_timu_ownership_layers', true );
        $owner_id = get_post_meta( $attachment_id, '_timu_owner_id', true );
        
        // Get user info
        $user = get_userdata( $owner_id );
        
        $evidence = array(
            'generated_at' => current_time( 'mysql' ),
            'attachment_id' => $attachment_id,
            'original_file' => array(
                'filename' => basename( $file ),
                'url' => wp_get_attachment_url( $attachment_id ),
                'hash' => $file_hash,
                'size' => file_exists( $file ) ? filesize( $file ) : 0,
                'uploaded_at' => $post->post_date,
            ),
            'ownership' => array(
                'owner_id' => $owner_id,
                'owner_name' => $user ? $user->display_name : '',
                'owner_email' => $user ? $user->user_email : '',
                'upload_user' => $post->post_author,
            ),
            'copyright_metadata' => $copyright_info,
            'embedded_layers' => $ownership_layers,
            'verification' => $verification,
            'infringing_url' => $infringing_url,
            'site_info' => array(
                'site_name' => get_bloginfo( 'name' ),
                'site_url' => home_url(),
                'admin_email' => get_bloginfo( 'admin_email' ),
            ),
        );
        
        // Store evidence for future reference
        $evidence_id = self::store_evidence( $attachment_id, $evidence );
        $evidence['evidence_id'] = $evidence_id;
        
        return $evidence;
    }
    
    /**
     * Store evidence in the database.
     * 
     * @param int   $attachment_id The attachment ID.
     * @param array $evidence Evidence data.
     * @return string Evidence ID.
     */
    private static function store_evidence( $attachment_id, $evidence ) {
        $evidence_id = uniqid( 'dmca_', true );
        
        $stored_evidence = get_post_meta( $attachment_id, '_timu_dmca_evidence', true );
        
        if ( ! is_array( $stored_evidence ) ) {
            $stored_evidence = array();
        }
        
        $stored_evidence[ $evidence_id ] = $evidence;
        
        update_post_meta( $attachment_id, '_timu_dmca_evidence', $stored_evidence );
        
        return $evidence_id;
    }
    
    /**
     * Generate evidence report in HTML format.
     * 
     * @param int    $attachment_id The attachment ID.
     * @param string $infringing_url URL of the infringing image.
     * @return string HTML report.
     */
    public static function generate_evidence_report_html( $attachment_id, $infringing_url = '' ) {
        $evidence = self::generate_dmca_evidence( $attachment_id, $infringing_url );
        
        if ( isset( $evidence['error'] ) ) {
            return '<p>Error: ' . esc_html( $evidence['error'] ) . '</p>';
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>DMCA Evidence Package - <?php echo esc_html( $evidence['original_file']['filename'] ); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                h1, h2, h3 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .success { color: green; font-weight: bold; }
                .failure { color: red; font-weight: bold; }
                .warning { color: orange; font-weight: bold; }
                .section { margin: 30px 0; }
                img { max-width: 100%; height: auto; }
            </style>
        </head>
        <body>
            <h1>DMCA Copyright Infringement Evidence Package</h1>
            
            <div class="section">
                <h2>Evidence Package Information</h2>
                <table>
                    <tr>
                        <th>Evidence ID</th>
                        <td><?php echo esc_html( $evidence['evidence_id'] ); ?></td>
                    </tr>
                    <tr>
                        <th>Generated On</th>
                        <td><?php echo esc_html( $evidence['generated_at'] ); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h2>Original Image Information</h2>
                <table>
                    <tr>
                        <th>Filename</th>
                        <td><?php echo esc_html( $evidence['original_file']['filename'] ); ?></td>
                    </tr>
                    <tr>
                        <th>Original URL</th>
                        <td><a href="<?php echo esc_url( $evidence['original_file']['url'] ); ?>"><?php echo esc_html( $evidence['original_file']['url'] ); ?></a></td>
                    </tr>
                    <tr>
                        <th>File Hash (MD5)</th>
                        <td><code><?php echo esc_html( $evidence['original_file']['hash'] ); ?></code></td>
                    </tr>
                    <tr>
                        <th>File Size</th>
                        <td><?php echo esc_html( size_format( $evidence['original_file']['size'] ) ); ?></td>
                    </tr>
                    <tr>
                        <th>Uploaded On</th>
                        <td><?php echo esc_html( $evidence['original_file']['uploaded_at'] ); ?></td>
                    </tr>
                </table>
                
                <h3>Original Image Preview</h3>
                <img src="<?php echo esc_url( $evidence['original_file']['url'] ); ?>" alt="Original Image">
            </div>
            
            <div class="section">
                <h2>Copyright Ownership Information</h2>
                <table>
                    <tr>
                        <th>Owner Name</th>
                        <td><?php echo esc_html( $evidence['ownership']['owner_name'] ); ?></td>
                    </tr>
                    <tr>
                        <th>Owner ID</th>
                        <td><?php echo esc_html( $evidence['ownership']['owner_id'] ); ?></td>
                    </tr>
                    <tr>
                        <th>Copyright Notice</th>
                        <td><?php echo esc_html( $evidence['copyright_metadata']['copyright'] ?? 'N/A' ); ?></td>
                    </tr>
                    <tr>
                        <th>Creator</th>
                        <td><?php echo esc_html( $evidence['copyright_metadata']['creator'] ?? 'N/A' ); ?></td>
                    </tr>
                    <tr>
                        <th>Rights Usage</th>
                        <td><?php echo esc_html( $evidence['copyright_metadata']['rights_usage'] ?? 'All Rights Reserved' ); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h2>Embedded Protection Layers</h2>
                <table>
                    <tr>
                        <th>Protection Layer</th>
                        <th>Status</th>
                    </tr>
                    <tr>
                        <td>EXIF/IPTC Metadata</td>
                        <td class="<?php echo ! empty( $evidence['embedded_layers']['metadata'] ) ? 'success' : 'failure'; ?>">
                            <?php echo ! empty( $evidence['embedded_layers']['metadata'] ) ? '✓ Embedded' : '✗ Not Embedded'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>DCT Frequency-Domain Fingerprint</td>
                        <td class="<?php echo ! empty( $evidence['embedded_layers']['dct_fingerprint'] ) ? 'success' : 'failure'; ?>">
                            <?php echo ! empty( $evidence['embedded_layers']['dct_fingerprint'] ) ? '✓ Embedded' : '✗ Not Embedded'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>LSB Steganography Fingerprint</td>
                        <td class="<?php echo ! empty( $evidence['embedded_layers']['lsb_fingerprint'] ) ? 'success' : 'failure'; ?>">
                            <?php echo ! empty( $evidence['embedded_layers']['lsb_fingerprint'] ) ? '✓ Embedded' : '✗ Not Embedded'; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h2>Verification Results</h2>
                <table>
                    <tr>
                        <th>Verification Status</th>
                        <td class="<?php echo $evidence['verification']['verified'] ? 'success' : 'failure'; ?>">
                            <?php echo $evidence['verification']['verified'] ? '✓ VERIFIED' : '✗ NOT VERIFIED'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Confidence Level</th>
                        <td><?php echo esc_html( round( $evidence['verification']['confidence'] * 100 ) . '%' ); ?></td>
                    </tr>
                    <tr>
                        <th>Detected Layers</th>
                        <td><?php echo esc_html( $evidence['verification']['detected_layers'] ); ?> / 3</td>
                    </tr>
                </table>
            </div>
            
            <?php if ( ! empty( $infringing_url ) ) : ?>
            <div class="section">
                <h2>Infringement Information</h2>
                <table>
                    <tr>
                        <th>Infringing URL</th>
                        <td><a href="<?php echo esc_url( $infringing_url ); ?>"><?php echo esc_html( $infringing_url ); ?></a></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2>Site Information</h2>
                <table>
                    <tr>
                        <th>Site Name</th>
                        <td><?php echo esc_html( $evidence['site_info']['site_name'] ); ?></td>
                    </tr>
                    <tr>
                        <th>Site URL</th>
                        <td><?php echo esc_html( $evidence['site_info']['site_url'] ); ?></td>
                    </tr>
                    <tr>
                        <th>Contact Email</th>
                        <td><?php echo esc_html( $evidence['site_info']['admin_email'] ); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <p><em>This evidence package was automatically generated by Module Images Support plugin on <?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?> UTC.</em></p>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get list of evidence packages for an attachment.
     * 
     * @param int $attachment_id The attachment ID.
     * @return array List of evidence packages.
     */
    public static function get_evidence_list( $attachment_id ) {
        $stored_evidence = get_post_meta( $attachment_id, '_timu_dmca_evidence', true );
        
        if ( ! is_array( $stored_evidence ) ) {
            return array();
        }
        
        return $stored_evidence;
    }
}
