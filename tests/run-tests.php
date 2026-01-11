#!/usr/bin/env php
<?php
/**
 * Test Runner
 * 
 * Runs all tests for the Module Images Support plugin.
 * 
 * @package Module_Images_Support
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Module Images Support - Dual-Layer Copyright Mapping Tests  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$test_dir = dirname( __FILE__ );
$all_passed = true;

// Find all test files
$test_files = glob( $test_dir . '/test-*.php' );

if ( empty( $test_files ) ) {
    echo "No test files found.\n";
    exit( 1 );
}

foreach ( $test_files as $test_file ) {
    echo "Running: " . basename( $test_file ) . "\n";
    echo str_repeat( '─', 80 ) . "\n";
    
    // Execute test file
    $output = array();
    $return_var = 0;
    
    exec( 'php ' . escapeshellarg( $test_file ) . ' 2>&1', $output, $return_var );
    
    echo implode( "\n", $output ) . "\n";
    
    if ( $return_var !== 0 ) {
        $all_passed = false;
    }
    
    echo "\n";
}

echo str_repeat( '═', 80 ) . "\n";

if ( $all_passed ) {
    echo "✓ ALL TESTS PASSED\n";
    exit( 0 );
} else {
    echo "✗ SOME TESTS FAILED\n";
    exit( 1 );
}
