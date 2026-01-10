# Quick Start Guide

Get up and running with the Module Images Support plugin in minutes!

## Installation

1. **Upload the Plugin**
   ```
   wp-content/plugins/module-images-support/
   ```

2. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Module Images Support"
   - Click "Activate"

3. **Configure Settings**
   - Go to Settings → Image Compression
   - Enable "Auto-Compression"
   - Select your preferred algorithms
   - Save settings

## Basic Usage

### Automatic Compression (Recommended)

Once enabled, all uploaded images will be automatically compressed:

1. Go to Settings → Image Compression
2. Check "Enable Auto-Compression"
3. Set Target Quality (recommended: 85)
4. Click "Save Settings"
5. Upload images as normal - they'll be automatically optimized!

### Manual Compression

Use the API directly in your theme or plugin:

```php
// Compress a single image
$result = timu_intelligent_compress('/path/to/image.jpg', 85);

if (!is_wp_error($result)) {
    echo "Saved " . $result['savings_pct'] . "% with " . $result['format'];
}
```

## Recommended Settings

### For Maximum Quality (Photography Sites)
```
Target Quality: 90
Min Quality Score: 0.97
Quality Weight: 0.8
Savings Weight: 0.2
Algorithms: MozJPEG, WebP, AVIF
```

### For Balanced Performance (General Sites)
```
Target Quality: 85
Min Quality Score: 0.95
Quality Weight: 0.6
Savings Weight: 0.4
Algorithms: MozJPEG, WebP, AVIF
```

### For Maximum Compression (E-commerce)
```
Target Quality: 80
Min Quality Score: 0.93
Quality Weight: 0.4
Savings Weight: 0.6
Algorithms: WebP, AVIF
```

## Installing Compression Binaries (Optional)

For best results, install these binaries on your server:

### Ubuntu/Debian
```bash
# ImageMagick (for accurate SSIM)
sudo apt-get install imagemagick

# WebP
sudo apt-get install webp

# MozJPEG (manual build required)
# See: https://github.com/mozilla/mozjpeg

# Guetzli (manual build required)
# See: https://github.com/google/guetzli
```

### macOS (Homebrew)
```bash
# ImageMagick
brew install imagemagick

# WebP
brew install webp

# MozJPEG
brew install mozjpeg
```

## Checking What's Available

Visit Settings → Image Compression and scroll to "System Information" to see:
- Which algorithms are available
- Browser format support
- PHP version and extensions

## Troubleshooting

### "No compression algorithms produced acceptable results"
- Lower the "Minimum Quality Score" (try 0.90)
- Increase "Target Quality" (try 90)
- Enable more algorithms

### Images not being compressed
- Check that "Enable Auto-Compression" is checked
- Verify at least one algorithm is available
- Check file permissions on upload directory

### Slow compression
- Disable Guetzli (it's very slow)
- Use only WebP for fastest results
- Consider batch processing during off-peak hours

## Browser Support

The plugin automatically serves the best format per browser:

| Browser | AVIF | WebP | JPEG |
|---------|------|------|------|
| Chrome 85+ | ✓ | ✓ | ✓ |
| Firefox 93+ | ✓ | ✓ | ✓ |
| Firefox 65-92 | - | ✓ | ✓ |
| Safari 14+ | - | ✓ | ✓ |
| Edge 18+ | - | ✓ | ✓ |
| All others | - | - | ✓ |

## Next Steps

1. **Monitor Results**: Check the savings on your first few uploads
2. **Tune Settings**: Adjust quality/savings weights based on results
3. **Batch Process**: Use `timu_batch_compress()` for existing images
4. **Read Full Docs**: See README.md for complete API reference

## Getting Help

- **Documentation**: See README.md
- **Examples**: Check examples.php for code samples
- **Issues**: https://github.com/thisismyurl/module-images-support-thisismyurl/issues

## Pro Tips

1. **Test First**: Try different quality settings on a few images
2. **Monitor Quality**: Check compressed images visually before deploying
3. **Use WebP**: Best balance of compression and compatibility
4. **Consider AVIF**: Best compression but newer browser requirement
5. **Off-Peak Processing**: Run batch compression during low-traffic hours
