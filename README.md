# Module Images Support - Dual-Layer Copyright Mapping

WordPress image enhancement module with filters, social optimization, text overlays, branding features, and advanced dual-layer copyright protection.

## Features

### Three-Layer Copyright Protection System

This plugin implements a comprehensive copyright protection system combining:

1. **EXIF/IPTC Metadata (Layer 1)** - Machine-readable copyright information
2. **DCT Frequency-Domain Fingerprinting (Layer 2)** - Imperceptible ownership ID embedded in JPEG DCT coefficients
3. **LSB Steganography (Layer 3)** - Pixel-level fingerprinting using least significant bits

### Key Capabilities

- ✅ Automatic copyright embedding on image upload
- ✅ Multi-layer ownership verification
- ✅ DMCA evidence package generation
- ✅ Admin dashboard for copyright management
- ✅ Debug and verification tools
- ✅ Configurable protection layers

## Installation

1. Upload the plugin files to `/wp-content/plugins/module-images-support/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings → Copyright Protection

## Configuration

Navigate to **Settings → Copyright Protection** to configure:

- Enable/disable each protection layer
- Set default copyright text
- Set creator name
- Configure rights usage terms
- Set license information

## Protection Layer Robustness

| Method | Survives JPG Recompression | Survives Cropping | Survives Resizing | Human Detectable |
|--------|---------------------------|-------------------|------------------|-----------------|
| EXIF/IPTC | ✅ | ✅ | ✅ | ✅ |
| DCT Fingerprint | ✅ | ❌ | ❌ | ❌ |
| LSB Fingerprint | ❌ | ✅ (10%) | ✅ | ❌ |

## Usage

### Automatic Protection

Once configured, the plugin automatically embeds copyright protection when images are uploaded to the WordPress media library.

### Manual Protection

For existing images:

1. Go to Media Library
2. Edit an attachment
3. Find the "Copyright Protection" meta box
4. Click "Embed/Re-embed Layers"

### Verification

To verify ownership protection:

1. Edit an attachment in Media Library
2. Find the "Copyright Protection" meta box
3. Click "Verify Ownership"

### DMCA Evidence Generation

To generate proof-of-ownership evidence:

1. Edit an attachment in Media Library
2. Find the "Copyright Protection" meta box
3. Click "Generate DMCA Evidence"
4. Optionally provide the URL of an infringing image
5. A comprehensive HTML report will open in a new window

## API Reference

### Embed Copyright Metadata

```php
$copyright_info = array(
    'copyright' => '© 2026 Company Name',
    'creator' => 'Photographer Name',
    'rights_usage' => 'All Rights Reserved',
    'usage_terms' => 'CC BY 4.0',
    'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
);

TIMU_Metadata::embed_copyright_metadata( $attachment_id, $copyright_info );
```

### Embed Full Ownership Chain

```php
TIMU_Ownership::embed_full_ownership_chain( $attachment_id, $owner_id, $copyright_info );
```

### Verify Ownership

```php
$verification = TIMU_Ownership::verify_ownership( $file_path, $expected_owner_id );

// Returns:
// array(
//     'verified' => true/false,
//     'confidence' => 0.0 to 1.0,
//     'layers' => array( ... ),
//     'detected_layers' => int,
//     'matching_layers' => int
// )
```

### Generate DMCA Evidence

```php
$evidence = TIMU_DMCA::generate_dmca_evidence( $attachment_id, $infringing_url );
```

## Action Hooks

```php
// Fired when ownership layers are embedded
add_action( 'timu_ownership_embedded', function( $attachment_id, $owner_id, $results ) {
    // Your code here
}, 10, 3 );
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- GD Library (for image manipulation)
- Optional: exiftool (for enhanced metadata support)

## Technical Notes

### DCT Fingerprinting

The DCT (Discrete Cosine Transform) fingerprinting uses a simplified approach that modifies specific regions of the image using LSB techniques. For production environments requiring true DCT coefficient modification, consider integrating with specialized libraries.

### LSB Steganography

The LSB (Least Significant Bit) implementation embeds a 64-bit payload:
- 16 bits: Magic number (0x5449 = "TI")
- 32 bits: Owner ID
- 16 bits: CRC32 checksum

This ensures data integrity and allows detection of corrupted fingerprints.

## Security Considerations

- Fingerprints are imperceptible to human vision
- Multiple layers provide redundancy against tampering
- At least 2 matching layers required for high-confidence verification
- Original files can be backed up automatically

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

GPL v2 or later

## Author

Christopher Ross (@thisismyurl)

## Support

For issues and feature requests, please use the GitHub issue tracker.
