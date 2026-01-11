# Architecture Overview

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    WordPress Upload Handler                      │
│                  (wp_handle_upload_prefilter)                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              Intelligent Compression Engine                      │
│            (timu_intelligent_compress)                           │
│                                                                   │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  1. Validate Input (file exists, readable)             │    │
│  └────────────────────────────────────────────────────────┘    │
│                         │                                        │
│                         ▼                                        │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  2. Compress with Each Algorithm                       │    │
│  │     ┌──────────┐ ┌──────────┐ ┌──────┐ ┌──────┐      │    │
│  │     │ MozJPEG  │ │ Guetzli  │ │ WebP │ │ AVIF │      │    │
│  │     └──────────┘ └──────────┘ └──────┘ └──────┘      │    │
│  └────────────────────────────────────────────────────────┘    │
│                         │                                        │
│                         ▼                                        │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  3. Score Each Result                                  │    │
│  │     • Calculate file size savings                      │    │
│  │     • Calculate SSIM quality score                     │    │
│  │     • Compute composite score                          │    │
│  │       score = (savings × 0.4) + (quality × 100 × 0.6)  │    │
│  └────────────────────────────────────────────────────────┘    │
│                         │                                        │
│                         ▼                                        │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  4. Filter Results                                     │    │
│  │     • Reject if savings < threshold                    │    │
│  │     • Reject if quality < threshold (0.95)             │    │
│  └────────────────────────────────────────────────────────┘    │
│                         │                                        │
│                         ▼                                        │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  5. Select Best Result                                 │    │
│  │     • Sort by composite score                          │    │
│  │     • Return highest scoring result                    │    │
│  │     • Clean up non-winning files                       │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│               Browser Format Negotiation                         │
│          (timu_get_browser_best_format)                          │
│                                                                   │
│  Check HTTP Accept Header:                                       │
│  image/avif  → Return AVIF (Chrome 85+, FF 93+)                 │
│  image/webp  → Return WebP (Chrome 23+, FF 65+, Safari 14+)    │
│  default     → Return JPEG (Universal fallback)                  │
└─────────────────────────────────────────────────────────────────┘
```

## Component Interaction

### Algorithm Modules

Each compression algorithm is self-contained:

```
┌─────────────────────────────────────────────────────┐
│  timu_compress_[algorithm]($path, $quality)         │
├─────────────────────────────────────────────────────┤
│  1. Check if algorithm is available                 │
│  2. Prepare input (convert format if needed)        │
│  3. Execute compression                             │
│  4. Return compressed file path or WP_Error         │
└─────────────────────────────────────────────────────┘
```

**Supported Algorithms:**
- `timu_compress_mozjpeg()` - Uses cjpeg binary
- `timu_compress_guetzli()` - Uses guetzli binary
- `timu_compress_webp()` - Uses GD imagewebp()
- `timu_compress_avif()` - Uses imageavif() or avifenc binary

### Quality Scoring (SSIM)

```
┌─────────────────────────────────────────────────────┐
│  timu_calculate_ssim($original, $compressed)        │
├─────────────────────────────────────────────────────┤
│  Method 1: ImageMagick Compare (preferred)          │
│  • Accurate SSIM calculation                        │
│  • Returns 0-1 score                                │
│                                                      │
│  Method 2: GD Library Fallback                      │
│  • Simplified pixel-by-pixel comparison             │
│  • Sample-based for performance                     │
│  • Returns approximate 0-1 score                    │
└─────────────────────────────────────────────────────┘
```

### Admin Interface

```
┌─────────────────────────────────────────────────────┐
│  WordPress Admin: Settings → Image Compression      │
├─────────────────────────────────────────────────────┤
│  Configuration Options:                             │
│  • Enable/disable auto-compression                  │
│  • Target quality (1-100)                           │
│  • Minimum SSIM score (0-1)                         │
│  • Algorithm selection (checkboxes)                 │
│  • Scoring weights (quality vs savings)             │
│                                                      │
│  System Information:                                │
│  • PHP version                                      │
│  • Available extensions (GD, WebP, AVIF)            │
│  • Available binaries (cjpeg, guetzli, etc.)        │
│  • Browser support matrix                           │
└─────────────────────────────────────────────────────┘
```

## Data Flow

### Single Image Compression

```
Input Image (image.jpg)
    │
    ├─→ MozJPEG  → temp_mozjpeg.jpg  → Score: 71.8
    ├─→ Guetzli  → temp_guetzli.jpg  → Score: 68.5
    ├─→ WebP     → temp_webp.webp    → Score: 73.8
    └─→ AVIF     → temp_avif.avif    → Score: 75.8 ✓ WINNER
           │
           └─→ Replace original with AVIF
               Clean up temp_mozjpeg.jpg
               Clean up temp_guetzli.jpg
               Clean up temp_webp.webp
```

### Batch Processing

```
Input: [image1.jpg, image2.png, image3.jpg]
    │
    ├─→ image1.jpg → Intelligent Compress → Result 1
    ├─→ image2.png → Intelligent Compress → Result 2
    └─→ image3.jpg → Intelligent Compress → Result 3
           │
           └─→ Aggregate Results:
               • Total savings: 35%
               • Successful: 3
               • Failed: 0
```

## Scoring Algorithm

```python
# Composite Score Calculation

savings_percentage = ((original_size - compressed_size) / original_size) × 100
ssim_score = calculate_ssim(original, compressed)  # 0-1

# Default weights: 40% savings, 60% quality
composite_score = (savings_percentage × 0.4) + (ssim_score × 100 × 0.6)

# Example:
# MozJPEG: savings=35.5%, ssim=0.96
#   → (35.5 × 0.4) + (0.96 × 100 × 0.6) = 14.2 + 57.6 = 71.8

# WebP: savings=42.0%, ssim=0.95
#   → (42.0 × 0.4) + (0.95 × 100 × 0.6) = 16.8 + 57.0 = 73.8

# AVIF: savings=48.5%, ssim=0.94
#   → (48.5 × 0.4) + (0.94 × 100 × 0.6) = 19.4 + 56.4 = 75.8 ✓ BEST
```

## Error Handling

```
┌──────────────────────────────────────────────────────┐
│  Error Type          │  Handling Strategy            │
├──────────────────────┼───────────────────────────────┤
│  File not found      │  Return WP_Error immediately  │
│  Algorithm N/A       │  Skip, try next algorithm     │
│  Compression failed  │  Skip, try next algorithm     │
│  Quality too low     │  Reject result, clean up file │
│  No savings          │  Reject result, clean up file │
│  No valid results    │  Return WP_Error, keep orig.  │
└──────────────────────────────────────────────────────┘
```

## Performance Characteristics

### Time Complexity

- **MozJPEG**: Fast (1-2 seconds for typical image)
- **Guetzli**: Very Slow (30-60 seconds, not recommended for real-time)
- **WebP**: Fast (1-2 seconds)
- **AVIF**: Moderate (3-5 seconds)

### Space Savings

Typical results for 1MB JPEG at quality 85:

- **Original**: 1000 KB
- **MozJPEG**: 650 KB (35% savings)
- **WebP**: 580 KB (42% savings)
- **AVIF**: 515 KB (48.5% savings) ✓

### Quality Retention (SSIM)

- **MozJPEG**: 0.96-0.98 (Excellent)
- **Guetzli**: 0.97-0.99 (Excellent)
- **WebP**: 0.94-0.96 (Very Good)
- **AVIF**: 0.93-0.95 (Very Good)

## Security Considerations

1. **Input Validation**
   - File existence checks before operations
   - MIME type validation
   - Path traversal prevention

2. **Shell Command Injection Prevention**
   - All paths escaped with `escapeshellarg()`
   - No user input directly in shell commands

3. **WordPress Security**
   - Nonce verification in admin forms
   - Capability checks (manage_options)
   - ABSPATH protection in all files

4. **Resource Management**
   - Temporary file cleanup
   - Memory-efficient image loading
   - Failed compression cleanup

## Extensibility

### Adding New Algorithms

```php
function timu_compress_newformat($image_path, $quality = 85) {
    // 1. Check if algorithm is available
    if (!timu_find_binary('newformat-encoder')) {
        return new WP_Error('not_found', 'Encoder not found');
    }
    
    // 2. Create output path
    $output_path = timu_get_temp_path($image_path, 'newformat.nf');
    
    // 3. Execute compression
    exec("newformat-encoder -q $quality ...");
    
    // 4. Return result
    return $output_path;
}
```

### Custom Scoring

```php
$options = array(
    'quality_weight' => 0.7,  // 70% quality
    'savings_weight' => 0.3,  // 30% savings
);

$result = timu_intelligent_compress($path, 85, $options);
```

## Browser Support Matrix

| Format | Size   | Quality | Chrome | Firefox | Safari | Edge  |
|--------|--------|---------|--------|---------|--------|-------|
| AVIF   | Best   | Good    | 85+    | 93+     | -      | 121+  |
| WebP   | Better | Good    | 23+    | 65+     | 14+    | 18+   |
| JPEG   | Good   | Good    | All    | All     | All    | All   |

The plugin automatically serves the best format the browser supports.
