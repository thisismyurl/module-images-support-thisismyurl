<?php
/**
 * Test LSB Fingerprinting functionality
 * 
 * @package Module_Images_Support
 */

require_once dirname( __FILE__ ) . '/bootstrap.php';

class Test_TIMU_LSB_Fingerprint {
    
    private $test_image_path;
    
    public function __construct() {
        $this->create_test_image();
    }
    
    /**
     * Create a test PNG image (LSB requires lossless format)
     */
    private function create_test_image() {
        $this->test_image_path = sys_get_temp_dir() . '/test_lsb_' . uniqid() . '.png';
        
        // Create a simple test image
        $image = imagecreatetruecolor( 100, 100 );
        $white = imagecolorallocate( $image, 255, 255, 255 );
        $blue = imagecolorallocate( $image, 0, 0, 255 );
        
        imagefill( $image, 0, 0, $white );
        imagefilledrectangle( $image, 20, 20, 80, 80, $blue );
        
        // Use PNG for lossless storage (required for LSB)
        imagepng( $image, $this->test_image_path );
        imagedestroy( $image );
    }
    
    /**
     * Test basic fingerprint embedding and detection
     */
    public function test_embed_and_detect() {
        echo "Testing LSB fingerprint embedding and detection...\n";
        
        $owner_id = 54321;
        
        // Prepare payload
        $magic = 0x5449; // "TI"
        $checksum = crc32( (string) $owner_id ) & 0xFFFF;
        
        $payload = sprintf( '%016b', $magic ) . 
                  sprintf( '%032b', $owner_id ) . 
                  sprintf( '%016b', $checksum );
        
        echo "  Owner ID: {$owner_id}\n";
        echo "  Magic: 0x" . dechex( $magic ) . "\n";
        echo "  Checksum: 0x" . dechex( $checksum ) . "\n";
        echo "  Payload length: " . strlen( $payload ) . " bits\n";
        
        // Embed payload
        $image = imagecreatefrompng( $this->test_image_path );
        $width = imagesx( $image );
        $height = imagesy( $image );
        
        $bit_index = 0;
        $payload_length = strlen( $payload );
        
        for ( $y = 0; $y < $height && $bit_index < $payload_length; $y++ ) {
            for ( $x = 0; $x < $width && $bit_index < $payload_length; $x++ ) {
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
                
                $new_color = imagecolorallocate( $image, $r, $g, $b );
                imagesetpixel( $image, $x, $y, $new_color );
            }
        }
        
        // Save as PNG (lossless)
        imagepng( $image, $this->test_image_path );
        imagedestroy( $image );
        
        echo "  ✓ Payload embedded\n";
        
        // Test detection
        $detected_id = TIMU_LSB_Fingerprint::detect_lsb_fingerprint( $this->test_image_path );
        
        if ( $detected_id === $owner_id ) {
            echo "  ✓ Fingerprint detected correctly: {$detected_id}\n";
            return true;
        } else {
            echo "  ✗ Fingerprint detection failed. Expected: {$owner_id}, Got: " . ( $detected_id ?? 'null' ) . "\n";
            return false;
        }
    }
    
    /**
     * Test with corrupted data
     */
    public function test_corrupted_data() {
        echo "Testing LSB with corrupted data...\n";
        
        // Create image with random data
        $image = imagecreatetruecolor( 50, 50 );
        
        for ( $y = 0; $y < 50; $y++ ) {
            for ( $x = 0; $x < 50; $x++ ) {
                $r = rand( 0, 255 );
                $g = rand( 0, 255 );
                $b = rand( 0, 255 );
                $color = imagecolorallocate( $image, $r, $g, $b );
                imagesetpixel( $image, $x, $y, $color );
            }
        }
        
        $corrupt_path = sys_get_temp_dir() . '/test_corrupt_' . uniqid() . '.png';
        imagepng( $image, $corrupt_path );
        imagedestroy( $image );
        
        // Try to detect (should fail due to wrong magic number)
        $result = TIMU_LSB_Fingerprint::detect_lsb_fingerprint( $corrupt_path );
        
        unlink( $corrupt_path );
        
        if ( $result === null ) {
            echo "  ✓ Correctly rejected corrupted data\n";
            return true;
        } else {
            echo "  ✗ Should reject corrupted data\n";
            return false;
        }
    }
    
    /**
     * Test with invalid file
     */
    public function test_invalid_file() {
        echo "Testing LSB with invalid file...\n";
        
        $result = TIMU_LSB_Fingerprint::detect_lsb_fingerprint( '/nonexistent/file.jpg' );
        
        if ( $result === null ) {
            echo "  ✓ Correctly returned null for invalid file\n";
            return true;
        } else {
            echo "  ✗ Should return null for invalid file\n";
            return false;
        }
    }
    
    /**
     * Run all tests
     */
    public function run_tests() {
        echo "\n=== Running LSB Fingerprint Tests ===\n\n";
        
        $tests = array(
            'test_embed_and_detect',
            'test_corrupted_data',
            'test_invalid_file',
        );
        
        $passed = 0;
        $total = count( $tests );
        
        foreach ( $tests as $test ) {
            if ( $this->$test() ) {
                $passed++;
            }
            echo "\n";
        }
        
        echo "=== LSB Tests Complete: {$passed}/{$total} passed ===\n\n";
        
        // Cleanup
        if ( file_exists( $this->test_image_path ) ) {
            unlink( $this->test_image_path );
        }
        
        return $passed === $total;
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' ) {
    $test = new Test_TIMU_LSB_Fingerprint();
    $success = $test->run_tests();
    exit( $success ? 0 : 1 );
}
