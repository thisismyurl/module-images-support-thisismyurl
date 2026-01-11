# Module Images Support - WordPress Plugin

WordPress image enhancement module with intelligent compression engine, filters, social optimization, text overlays, and branding features.

## Features

### Intelligent Compression Engine (Multi-Algorithm Comparison)

The core feature of this plugin is the **Intelligent Compression Engine** that automatically selects the best compression format and settings for each image by comparing multiple algorithms:

- **MozJPEG**: Optimized JPEG encoder with superior compression
- **Guetzli**: Perceptual JPEG encoder for high-quality images
- **WebP**: Modern format with excellent compression (Chrome 23+, Firefox 65+, Edge 18+)
- **AVIF**: Next-generation format with best-in-class compression (Chrome 85+, Firefox 93+)

#### How It Works

1. **Multi-Algorithm Testing**: For each uploaded image, the engine compresses it using all selected algorithms
2. **Quality Scoring**: Each result is scored using SSIM (Structural Similarity Index) to ensure visual quality
3. **Composite Scoring**: Results are ranked based on a weighted score combining file size savings and quality preservation
4. **Automatic Selection**: The best result is automatically selected and used

#### Performance Impact

- **Before**: Single compression format (JPG at quality 75-85)
- **After**: Auto-selects optimal format per image
- **Improvement**: 20-40% additional savings vs. single algorithm

### Browser Support

The plugin automatically serves the best format supported by the requesting browser:

- **AVIF**: Chrome 85+, Firefox 93+, Opera 71+
- **WebP**: Chrome 23+, Firefox 65+, Edge 18+, Opera 12.1+, Safari 14+
- **JPEG**: All browsers (fallback)

## Installation

1. Upload the plugin files to `/wp-content/plugins/module-images-support/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → Image Compression to configure the plugin

## Configuration

### Settings Page

Navigate to **Settings → Image Compression** to configure:

- **Enable Auto-Compression**: Automatically compress images on upload
- **Target Quality**: Quality level (1-100) for compression algorithms
- **Minimum Quality Score**: SSIM threshold (0-1) to ensure acceptable quality
- **Compression Algorithms**: Select which algorithms to use for comparison
- **Scoring Weights**: Balance between quality preservation and file size reduction

### Default Settings

```php
Target Quality: 85
Minimum Quality Score: 0.95 (95% similarity required)
Quality Weight: 0.6 (60% weight on quality preservation)
Savings Weight: 0.4 (40% weight on file size reduction)
```

## Usage

### Automatic Compression on Upload

Once enabled, all uploaded images will be automatically compressed using the intelligent compression engine.

### Manual Compression

You can also use the compression functions directly in your code:

```php
// Compress a single image
$result = timu_intelligent_compress('/path/to/image.jpg', 85);

if (!is_wp_error($result)) {
    echo "Compressed: " . $result['format'] . "\n";
    echo "Original size: " . $result['original_size'] . " bytes\n";
    echo "Compressed size: " . $result['size'] . " bytes\n";
    echo "Savings: " . $result['savings_pct'] . "%\n";
    echo "Quality score: " . $result['quality_score'] . "\n";
}

// Batch compress multiple images
$results = timu_batch_compress([
    '/path/to/image1.jpg',
    '/path/to/image2.png',
    '/path/to/image3.jpg',
], 85);

echo "Total savings: " . $results['total_savings'] . "%\n";
echo "Successful: " . count($results['success']) . "\n";
echo "Failed: " . count($results['failed']) . "\n";
```

### Format Negotiation

Get the best format for the requesting browser:

```php
$best_format = timu_get_browser_best_format();
// Returns: 'avif', 'webp', or 'jpeg'
```

## Requirements

### Server Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- GD library with WebP support (recommended)
- PHP 8.1+ for native AVIF support (optional)

### Optional Binary Requirements

For optimal compression, install these binaries on your server:

- **MozJPEG**: `cjpeg` binary
- **Guetzli**: `guetzli` binary
- **AVIF**: `avifenc` binary (if not using PHP 8.1+)
- **ImageMagick**: `compare` binary for accurate SSIM calculation

The plugin will automatically detect available binaries and enable corresponding algorithms.

## Implementation Phases

- [x] Phase 1: Core intelligent compression engine
- [x] Phase 1.5: Compare MozJPEG vs. WebP
- [x] Phase 2: Add AVIF support
- [x] Phase 3: SSIM quality scoring
- [x] Phase 4: Batch processing with algorithm selection
- [x] Phase 5: WordPress admin integration
- [x] Phase 6: Browser-based format negotiation

## API Reference

### Main Functions

#### `timu_intelligent_compress($image_path, $target_quality, $options)`

Compress an image using the intelligent compression engine.

**Parameters:**
- `$image_path` (string): Path to the image to compress
- `$target_quality` (int): Target quality level (1-100), default: 85
- `$options` (array): Optional configuration options

**Returns:** Array with compression results or WP_Error on failure

**Example:**
```php
$result = timu_intelligent_compress('/path/to/image.jpg', 85, [
    'algorithms' => ['mozjpeg', 'webp', 'avif'],
    'min_quality_score' => 0.95,
    'quality_weight' => 0.6,
    'savings_weight' => 0.4,
]);
```

#### `timu_batch_compress($image_paths, $target_quality, $options)`

Batch process multiple images with intelligent compression.

**Parameters:**
- `$image_paths` (array): Array of image paths to process
- `$target_quality` (int): Target quality level (1-100), default: 85
- `$options` (array): Optional configuration options

**Returns:** Array with batch processing results

#### `timu_calculate_ssim($original_path, $compressed_path)`

Calculate SSIM (Structural Similarity Index) between two images.

**Parameters:**
- `$original_path` (string): Path to original image
- `$compressed_path` (string): Path to compressed image

**Returns:** Float SSIM score (0-1), where 1 = identical images

#### `timu_get_browser_best_format()`

Get the best image format supported by the requesting browser.

**Returns:** String format ('avif', 'webp', or 'jpeg')

### Algorithm-Specific Functions

- `timu_compress_mozjpeg($image_path, $quality)`: Compress using MozJPEG
- `timu_compress_guetzli($image_path, $quality)`: Compress using Guetzli
- `timu_compress_webp($image_path, $quality)`: Compress to WebP format
- `timu_compress_avif($image_path, $quality)`: Compress to AVIF format

## Technical Details

### Composite Scoring Algorithm

```php
$score = ($savings * savings_weight) + ($quality_score * 100 * quality_weight);
```

Where:
- `$savings`: Percentage of file size reduction
- `$quality_score`: SSIM score (0-1)
- Default weights: 60% quality, 40% savings

### SSIM Quality Scoring

The plugin uses SSIM (Structural Similarity Index) to measure perceptual quality:

1. **ImageMagick Compare**: If available, uses the `compare` binary for accurate SSIM
2. **Fallback Method**: Simplified SSIM calculation using GD library with sampling

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

GPL v2 or later

## Author

[thisismyurl](https://github.com/thisismyurl)

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/thisismyurl/module-images-support-thisismyurl/issues).
