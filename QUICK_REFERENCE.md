# Art Direction Mapper - Quick Reference

## Quick Start

### 1. Basic Setup

```php
// Include the module
require_once 'module-images-support.php';

// Set art direction for an image
timu_set_art_direction($attachment_id, [
    'mobile' => [
        'crop' => ['x' => 0, 'y' => 100, 'width' => 600, 'height' => 900],
        'focal_point' => 'primary',
        'aspect_ratio' => '2:3',
    ],
    'tablet' => [
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1024, 'height' => 768],
        'focal_point' => 'secondary',
        'aspect_ratio' => '4:3',
    ],
    'desktop' => [
        'crop' => null, // Use full image
        'focal_point' => 'primary',
        'aspect_ratio' => '16:9',
    ],
]);

// Generate markup
echo timu_get_responsive_image_markup($attachment_id);
```

## Common Scenarios

### Hero Image (News/Blog)

```php
timu_set_art_direction($hero_id, [
    'mobile' => [
        'crop' => ['x' => 300, 'y' => 0, 'width' => 600, 'height' => 800],
        'aspect_ratio' => '3:4', // Portrait for mobile
    ],
    'desktop' => [
        'crop' => ['x' => 0, 'y' => 300, 'width' => 2000, 'height' => 900],
        'aspect_ratio' => '21:9', // Cinematic for desktop
    ],
]);
```

### Product Image (E-commerce)

```php
timu_set_art_direction($product_id, [
    'mobile' => [
        'crop' => ['x' => 200, 'y' => 150, 'width' => 800, 'height' => 1200],
        'aspect_ratio' => '2:3', // Close-up
    ],
    'desktop' => [
        'crop' => null, // Full product with lifestyle
        'aspect_ratio' => '16:9',
    ],
]);
```

### Portrait Photo (Portfolio)

```php
timu_set_art_direction($portrait_id, [
    'mobile' => [
        'crop' => ['x' => 400, 'y' => 0, 'width' => 800, 'height' => 1200],
        'aspect_ratio' => '2:3',
    ],
    'tablet' => [
        'crop' => ['x' => 200, 'y' => 200, 'width' => 1200, 'height' => 1200],
        'aspect_ratio' => '1:1', // Square for gallery
    ],
]);
```

### Banner Image (Full Width)

```php
timu_set_art_direction($banner_id, [
    'mobile' => [
        'crop' => ['x' => 0, 'y' => 400, 'width' => 800, 'height' => 600],
        'aspect_ratio' => '4:3',
    ],
    'desktop' => [
        'crop' => ['x' => 0, 'y' => 200, 'width' => 2400, 'height' => 800],
        'aspect_ratio' => '3:1', // Wide banner
    ],
]);
```

## Template Usage

### In WordPress Theme

```php
// In single.php
if (has_post_thumbnail()) {
    echo timu_get_responsive_image_markup(
        get_post_thumbnail_id(),
        ['mobile', 'tablet', 'desktop'],
        ['webp', 'image/jpeg'],
        [
            'alt' => get_the_title(),
            'class' => 'featured-image',
            'loading' => 'eager',
        ]
    );
}
```

### As Shortcode

```
[art_direction id="123" sizes="mobile,tablet,desktop" formats="webp,jpeg" alt="My Image" class="my-class"]
```

### In WooCommerce

```php
// Product gallery
$attachment_ids = $product->get_gallery_image_ids();
foreach ($attachment_ids as $attachment_id) {
    echo timu_get_responsive_image_markup($attachment_id);
}
```

## Advanced Options

### Custom Breakpoints

```php
add_filter('timu_media_queries', function($queries) {
    return [
        'mobile' => '(max-width: 639px)',
        'tablet' => '(min-width: 640px) and (max-width: 1279px)',
        'desktop' => '(min-width: 1280px)',
    ];
});
```

### Format Options

```php
// WebP with JPEG fallback
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['webp', 'image/jpeg']);

// AVIF with WebP and JPEG fallback
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['avif', 'webp', 'image/jpeg']);

// PNG with alpha channel
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['image/png']);
```

### DPR Variants

```php
// Standard DPR (1x, 2x, 3x)
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['webp'], [
    'dpr_levels' => [1, 2, 3]
]);

// Limited DPR for large images
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['webp'], [
    'dpr_levels' => [1, 2]
]);
```

### Lazy Loading

```php
// Lazy load (default)
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['webp'], [
    'loading' => 'lazy'
]);

// Eager load (above the fold)
echo timu_get_responsive_image_markup($id, ['mobile', 'desktop'], ['webp'], [
    'loading' => 'eager'
]);
```

## Maintenance

### Clear Cache

```php
// Clear cache for a specific image
timu_clear_art_direction_cache($attachment_id);

// Clear cache for all images with art direction
$attachments = get_posts([
    'post_type' => 'attachment',
    'meta_key' => '_timu_art_direction',
    'posts_per_page' => -1,
]);

foreach ($attachments as $attachment) {
    timu_clear_art_direction_cache($attachment->ID);
}
```

### Update Existing Configuration

```php
// Get existing configuration
$directions = timu_get_art_direction($attachment_id);

// Modify specific device
$directions['mobile']['crop'] = ['x' => 100, 'y' => 200, 'width' => 700, 'height' => 1000];

// Save updated configuration
timu_set_art_direction($attachment_id, $directions);
```

### Remove Art Direction

```php
// Remove all art direction
delete_post_meta($attachment_id, '_timu_art_direction');

// Or set to empty array
timu_set_art_direction($attachment_id, []);
```

## Troubleshooting

### Image Not Cropping

1. Check crop coordinates are within image bounds
2. Verify attachment ID is correct
3. Check file permissions on uploads directory

```php
// Validate crop
$valid = timu_validate_crop_config([
    'x' => 0,
    'y' => 0,
    'width' => 600,
    'height' => 800
]);

if (!$valid) {
    echo "Invalid crop configuration";
}
```

### No Output

1. Check if art direction is set
2. Verify attachment exists
3. Check WordPress functions are available

```php
// Debug
$directions = timu_get_art_direction($attachment_id);
if (empty($directions)) {
    echo "No art direction configured";
}
```

### Performance Issues

1. Limit DPR levels: `['dpr_levels' => [1, 2]]`
2. Use fewer formats: `['webp', 'image/jpeg']`
3. Generate variants during off-peak hours
4. Enable CDN caching

```php
// Off-peak generation
add_action('wp_scheduled_event', function() {
    // Generate variants in background
    $attachments = get_posts([
        'post_type' => 'attachment',
        'meta_key' => '_timu_art_direction',
        'posts_per_page' => 50,
    ]);
    
    foreach ($attachments as $attachment) {
        timu_get_responsive_image_markup($attachment->ID);
    }
});
```

## API Reference

### Main Functions

| Function | Purpose |
|----------|---------|
| `timu_set_art_direction()` | Set device-specific crops |
| `timu_get_art_direction()` | Retrieve configuration |
| `timu_get_responsive_image_markup()` | Generate HTML markup |
| `timu_clear_art_direction_cache()` | Clear cached variants |

### Helper Functions

| Function | Purpose |
|----------|---------|
| `timu_validate_crop_config()` | Validate crop coordinates |
| `timu_parse_aspect_ratio()` | Parse aspect ratio string |
| `timu_get_media_query_for_size()` | Get media query for device |
| `timu_get_mime_type_for_format()` | Get MIME type for format |

### Filters

| Filter | Purpose |
|--------|---------|
| `timu_media_queries` | Customize breakpoints |

## Best Practices

1. **Always provide fallback**: Use `crop => null` for desktop to show full image
2. **Test on real devices**: Emulators don't always match real behavior
3. **Optimize crop coordinates**: Focus on subject of interest
4. **Use appropriate formats**: WebP for modern browsers, JPEG fallback
5. **Consider bandwidth**: Limit DPR variants on large images
6. **Cache aggressively**: Generated variants are expensive to create
7. **Document crops**: Add comments explaining why specific crops were chosen
8. **Test accessibility**: Ensure alt text is descriptive

## Performance Tips

1. Generate variants during image upload
2. Use CDN for image delivery
3. Enable browser caching
4. Compress source images before upload
5. Use appropriate image dimensions
6. Consider lazy loading for below-the-fold images
7. Monitor cache hit rates
8. Use image optimization plugins

## Browser Support

- Chrome 38+
- Firefox 38+
- Safari 9.1+
- Edge 13+
- Opera 25+

For older browsers, the fallback `<img>` element is used automatically.
