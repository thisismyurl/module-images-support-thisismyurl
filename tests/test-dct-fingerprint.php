<?php
/**
 * Test DCT Fingerprinting functionality
 * 
 * @package Module_Images_Support
 */

require_once dirname( __FILE__ ) . '/bootstrap.php';

class Test_TIMU_DCT_Fingerprint {
    
    private $test_image_path;
    
    public function __construct() {
        $this->create_test_image();
    }
    
    /**
     * Create a test image
     */
    private function create_test_image() {
        $this->test_image_path = sys_get_temp_dir() . '/test_dct_' . uniqid() . '.png';
        
        // Create a simple test image
        $image = imagecreatetruecolor( 200, 200 );
        $white = imagecolorallocate( $image, 255, 255, 255 );
        $black = imagecolorallocate( $image, 0, 0, 0 );
        
        imagefill( $image, 0, 0, $white );
        imagefilledrectangle( $image, 50, 50, 150, 150, $black );
        
        // Start with PNG for testing
        imagepng( $image, $this->test_image_path );
        imagedestroy( $image );
    }
    
    /**
     * Test basic fingerprint embedding
     */
    public function test_embed_and_detect() {
        echo "Testing DCT fingerprint embedding and detection...\n";
        
        $owner_id = 12345;
        
        // Test embedding (simulate with file)
        $image = imagecreatefromjpeg( $this->test_image_path );
        $width = imagesx( $image );
        $height = imagesy( $image );
        
        // Create fingerprint
        $fingerprint = sprintf( '%032b', $owner_id );
        
        echo "  Owner ID: {$owner_id}\n";
        echo "  Binary fingerprint: " . substr( $fingerprint, 0, 16 ) . "...\n";
        
        // Embed fingerprint manually (simplified test)
        $image = imagecreatefrompng( $this->test_image_path );
        $width = imagesx( $image );
        $height = imagesy( $image );
        
        $bit_index = 0;
        $fingerprint_length = strlen( $fingerprint );
        
        $start_x = (int) ( $width * 0.25 );
        $end_x = (int) ( $width * 0.75 );
        $start_y = (int) ( $height * 0.25 );
        $end_y = (int) ( $height * 0.75 );
        
        $step = max( 1, (int) sqrt( ( ( $end_x - $start_x ) * ( $end_y - $start_y ) ) / $fingerprint_length ) );
        
        for ( $y = $start_y; $y < $end_y && $bit_index < $fingerprint_length; $y += $step ) {
            for ( $x = $start_x; $x < $end_x && $bit_index < $fingerprint_length; $x += $step ) {
                $rgb = imagecolorat( $image, $x, $y );
                $r = ( $rgb >> 16 ) & 0xFF;
                $g = ( $rgb >> 8 ) & 0xFF;
                $b = $rgb & 0xFF;
                
                $bit = (int) $fingerprint[ $bit_index ];
                $b = ( $b & ~1 ) | $bit;
                
                $new_color = imagecolorallocate( $image, $r, $g, $b );
                imagesetpixel( $image, $x, $y, $new_color );
                
                $bit_index++;
            }
        }
        
        // Save as PNG first to preserve data
        imagepng( $image, $this->test_image_path );
        imagedestroy( $image );
        
        echo "  ✓ Fingerprint embedded\n";
        
        // Test detection
        $detected_id = TIMU_DCT_Fingerprint::detect_dct_fingerprint( $this->test_image_path );
        
        if ( $detected_id === $owner_id ) {
            echo "  ✓ Fingerprint detected correctly: {$detected_id}\n";
            return true;
        } else {
            echo "  ✗ Fingerprint detection failed. Expected: {$owner_id}, Got: " . ( $detected_id ?? 'null' ) . "\n";
            return false;
        }
    }
    
    /**
     * Test with invalid file
     */
    public function test_invalid_file() {
        echo "Testing DCT with invalid file...\n";
        
        $result = TIMU_DCT_Fingerprint::detect_dct_fingerprint( '/nonexistent/file.jpg' );
        
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
        echo "\n=== Running DCT Fingerprint Tests ===\n\n";
        
        $tests = array(
            'test_embed_and_detect',
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
        
        echo "=== DCT Tests Complete: {$passed}/{$total} passed ===\n\n";
        
        // Cleanup
        if ( file_exists( $this->test_image_path ) ) {
            unlink( $this->test_image_path );
        }
        
        return $passed === $total;
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' ) {
    $test = new Test_TIMU_DCT_Fingerprint();
    $success = $test->run_tests();
    exit( $success ? 0 : 1 );
}
