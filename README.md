# Module Images Support - Art Direction Mapper

WordPress image enhancement module with art direction support for device-specific crops and responsive images.

## Overview

The Art Direction Mapper provides a comprehensive solution for creating responsive images with device-specific compositions. Unlike traditional `srcset` which only scales images, this module allows you to define different crops and compositions for different devices, ensuring optimal presentation across all screen sizes.

## Features

- **Device-Specific Crops**: Define unique crops for mobile, tablet, and desktop
- **Aspect Ratio Control**: Specify different aspect ratios per device
- **Focal Point Integration**: Support for primary/secondary focal points
- **Multi-Format Support**: Generate WebP, AVIF, JPEG, and PNG variants
- **DPR Variants**: Automatic generation of 1x, 2x, and 3x resolution variants
- **Picture Element**: Standards-compliant `<picture>` element with media queries
- **Caching**: Efficient caching of generated variants
- **Extensible**: Filter hooks for custom breakpoints and configurations

## Installation

1. Include the module in your WordPress theme or plugin:

```php
require_once 'path/to/module-images-support.php';
```

2. Ensure WordPress image editor functions are available (they are by default).

## Quick Start

### Basic Usage

```php
// Set art direction for an attachment
$attachment_id = 123;

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

// Generate responsive markup
echo timu_get_responsive_image_markup($attachment_id);
```

### Generated HTML

```html
<picture>
  <source media="(max-width: 767px)" srcset="...mobile-1x.webp, ...mobile-2x.webp 2x, ...mobile-3x.webp 3x" type="image/webp">
  <source media="(max-width: 767px)" srcset="...mobile-1x.jpg, ...mobile-2x.jpg 2x, ...mobile-3x.jpg 3x" type="image/jpeg">
  <source media="(min-width: 768px) and (max-width: 1023px)" srcset="...tablet-1x.webp, ...tablet-2x.webp 2x, ...tablet-3x.webp 3x" type="image/webp">
  <source media="(min-width: 768px) and (max-width: 1023px)" srcset="...tablet-1x.jpg, ...tablet-2x.jpg 2x, ...tablet-3x.jpg 3x" type="image/jpeg">
  <source media="(min-width: 1024px)" srcset="...desktop-1x.webp, ...desktop-2x.webp 2x, ...desktop-3x.webp 3x" type="image/webp">
  <source media="(min-width: 1024px)" srcset="...desktop-1x.jpg, ...desktop-2x.jpg 2x, ...desktop-3x.jpg 3x" type="image/jpeg">
  <img src="...fallback.jpg" alt="Image description" loading="lazy" />
</picture>
```

## API Reference

### Core Functions

#### `timu_set_art_direction($attachment_id, $directions)`

Set art direction metadata for an attachment.

**Parameters:**
- `$attachment_id` (int): WordPress attachment post ID
- `$directions` (array): Array of device-specific configurations

**Returns:** bool - True on success, false on failure

**Example:**
```php
timu_set_art_direction(123, [
    'mobile' => [
        'crop' => ['x' => 0, 'y' => 100, 'width' => 600, 'height' => 900],
        'focal_point' => 'primary',
        'aspect_ratio' => '2:3',
    ],
]);
```

#### `timu_get_art_direction($attachment_id)`

Retrieve art direction metadata for an attachment.

**Parameters:**
- `$attachment_id` (int): WordPress attachment post ID

**Returns:** array - Art direction configuration or empty array

#### `timu_get_responsive_image_markup($attachment_id, $sizes, $formats, $args)`

Generate responsive image markup with art direction.

**Parameters:**
- `$attachment_id` (int): WordPress attachment post ID
- `$sizes` (array): Device sizes to include (default: ['mobile', 'tablet', 'desktop'])
- `$formats` (array): Image formats to generate (default: ['webp', 'image/jpeg'])
- `$args` (array): Additional arguments:
  - `alt` (string): Alt text for the image
  - `class` (string): CSS class for the img element
  - `loading` (string): Loading attribute (lazy, eager, auto)
  - `dpr_levels` (array): DPR levels to generate (default: [1, 2, 3])

**Returns:** string - HTML markup for the responsive image

**Example:**
```php
echo timu_get_responsive_image_markup(
    123,
    ['mobile', 'tablet', 'desktop'],
    ['webp', 'image/jpeg'],
    [
        'alt' => 'Product image',
        'class' => 'product-thumbnail',
        'loading' => 'eager',
    ]
);
```

#### `timu_clear_art_direction_cache($attachment_id)`

Clear cached art-directed images for regeneration.

**Parameters:**
- `$attachment_id` (int): WordPress attachment post ID

**Returns:** bool - True on success, false on failure

### Helper Functions

#### `timu_get_media_query_for_size($size)`

Get media query string for a device size.

**Parameters:**
- `$size` (string): Device size identifier

**Returns:** string - Media query string

#### `timu_parse_aspect_ratio($aspect_ratio)`

Parse aspect ratio string into width and height values.

**Parameters:**
- `$aspect_ratio` (string): Aspect ratio string (e.g., '16:9', '4:3')

**Returns:** array|false - Array with 'width' and 'height' keys, or false on failure

## Configuration

### Default Breakpoints

The module uses these default breakpoints:

- **Mobile**: `(max-width: 767px)`
- **Tablet**: `(min-width: 768px) and (max-width: 1023px)`
- **Desktop**: `(min-width: 1024px)`

### Custom Breakpoints

Override default breakpoints using the `timu_media_queries` filter:

```php
add_filter('timu_media_queries', function($queries) {
    return [
        'mobile' => '(max-width: 639px)',
        'tablet' => '(min-width: 640px) and (max-width: 1279px)',
        'desktop' => '(min-width: 1280px)',
    ];
});
```

### Supported Image Formats

- **WebP**: Modern format with excellent compression
- **AVIF**: Next-gen format with superior compression
- **JPEG**: Universal fallback format
- **PNG**: For images requiring transparency

## Use Cases

### 1. E-commerce Product Images

Show detailed close-ups on mobile, wider context on desktop:

```php
timu_set_art_direction($product_id, [
    'mobile' => [
        'crop' => ['x' => 200, 'y' => 150, 'width' => 800, 'height' => 1200],
        'aspect_ratio' => '2:3',
    ],
    'desktop' => [
        'crop' => null, // Full product with lifestyle
        'aspect_ratio' => '16:9',
    ],
]);
```

### 2. Editorial/News Sites

Different story focus per device:

```php
timu_set_art_direction($article_hero_id, [
    'mobile' => [
        'crop' => ['x' => 300, 'y' => 0, 'width' => 600, 'height' => 800],
        'aspect_ratio' => '3:4',
    ],
    'desktop' => [
        'crop' => ['x' => 0, 'y' => 300, 'width' => 2000, 'height' => 900],
        'aspect_ratio' => '21:9',
    ],
]);
```

### 3. Portfolio Galleries

Artistic composition choices:

```php
timu_set_art_direction($portfolio_id, [
    'mobile' => [
        'crop' => ['x' => 400, 'y' => 0, 'width' => 800, 'height' => 1200],
        'aspect_ratio' => '2:3',
    ],
    'tablet' => [
        'crop' => ['x' => 200, 'y' => 200, 'width' => 1200, 'height' => 1200],
        'aspect_ratio' => '1:1',
    ],
]);
```

## Performance Considerations

### Caching

Generated image variants are cached on disk. Regenerate when needed:

```php
// Clear cache and regenerate
timu_clear_art_direction_cache($attachment_id);
timu_get_responsive_image_markup($attachment_id);
```

### Off-Peak Generation

For large sites, consider generating variants during off-peak hours:

```php
// Hook into WordPress cron
add_action('timu_generate_variants', function() {
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

### CDN Integration

Generated URLs work seamlessly with CDNs:

```php
add_filter('wp_upload_dir', function($uploads) {
    $uploads['baseurl'] = 'https://cdn.example.com/wp-content/uploads';
    return $uploads;
});
```

## Integration with Other Features

### Focal Points

Use focal point metadata to guide automatic crops:

```php
// Focal points can be retrieved from metadata
$focal_point = get_post_meta($attachment_id, '_focal_point', true);

// Use in art direction
timu_set_art_direction($attachment_id, [
    'mobile' => [
        'focal_point' => $focal_point,
        'aspect_ratio' => '2:3',
    ],
]);
```

### Smart Cropping

Integrate with entropy-based or AI-powered cropping:

```php
// Use smart cropping to determine optimal crops
$smart_crop = my_smart_crop_function($attachment_id, '2:3');

timu_set_art_direction($attachment_id, [
    'mobile' => [
        'crop' => $smart_crop,
        'aspect_ratio' => '2:3',
    ],
]);
```

## Examples

See `examples.php` for comprehensive usage examples including:

1. Basic art direction setup
2. E-commerce product galleries
3. Editorial hero images
4. Portfolio presentations
5. Dynamic user-defined crops
6. Programmatic crop generation
7. WordPress hook integration
8. Cache management
9. Custom breakpoints
10. Configuration updates

## Troubleshooting

### Images Not Generating

1. Check file permissions on the uploads directory
2. Verify the attachment ID is valid
3. Ensure the source image exists and is readable
4. Check PHP memory limits for large images

### Invalid Crop Coordinates

Crops must have valid numeric values and fit within the source image dimensions.

### Performance Issues

1. Enable PHP OpCache
2. Use a CDN for image delivery
3. Generate variants during off-peak hours
4. Consider limiting DPR levels to [1, 2] on large sites

## Browser Support

The `<picture>` element is supported in:
- Chrome 38+
- Firefox 38+
- Safari 9.1+
- Edge 13+
- Opera 25+

For older browsers, the fallback `<img>` element is used.

## License

This module is provided as-is for use in WordPress projects.

## Contributing

For bug reports and feature requests, please open an issue on GitHub.

## Version History

### 1.0.0
- Initial release
- Core art direction functionality
- Device-specific crops
- Multi-format support
- DPR variant generation
- Picture element markup generation
