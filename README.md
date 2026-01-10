# Module Images Support - Multi-Focal Point Feature

WordPress image enhancement module with filters, social optimization, text overlays, branding features, and **multi-focal point support** for context-aware cropping.

## Features

### Multi-Focal Point Support (Context-Aware Cropping)

Define multiple focal points per image for contextual cropping:

- **Unlimited focal points per image** - Define as many areas of interest as needed
- **Smart cropping around each point** - Automatically calculate optimal crop coordinates
- **Context-aware recommendations** - Different focal points for mobile vs. desktop
- **Interactive visual editor** - Click to place points directly on the image
- **Preview crops** - See how different focal points affect common aspect ratios (1:1, 16:9, 4:3, etc.)
- **Label each point** - Organize points with descriptive labels like "Main subject", "Background", "Face"

## Installation

1. Upload the plugin files to the `/wp-content/plugins/module-images-support` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Edit any image attachment to access the Focal Point Editor

## Usage

### Basic Usage

#### Setting Focal Points

```php
// Define multiple focal points for an image
$focal_points = array(
    'primary' => array(
        'x' => 50,  // X coordinate (0-100%)
        'y' => 40,  // Y coordinate (0-100%)
        'label' => 'Main subject'
    ),
    'secondary' => array(
        'x' => 80,
        'y' => 20,
        'label' => 'Background element'
    ),
);

timu_set_focal_points( $attachment_id, $focal_points );
```

#### Getting Focal Points

```php
// Get all focal points for an image
$focal_points = timu_get_focal_points( $attachment_id );

// Get a specific focal point
$primary = timu_get_focal_point( $attachment_id, 'primary' );
```

#### Adding a Single Focal Point

```php
// Add a focal point
timu_add_focal_point( $attachment_id, 'face', 65, 35, 'Person\'s face' );
```

#### Removing a Focal Point

```php
// Remove a specific focal point
timu_remove_focal_point( $attachment_id, 'secondary' );

// Delete all focal points
timu_delete_focal_points( $attachment_id );
```

### Cropping with Focal Points

#### Calculate Crop Coordinates

```php
// Get crop coordinates centered on a focal point
$crop_data = timu_crop_to_focal_point( 
    $attachment_id, 
    'primary',      // Focal point key
    800,            // Target width
    600             // Target height
);

// Returns array with: ['x' => ..., 'y' => ..., 'width' => ..., 'height' => ...]
```

#### Generate Cropped Image

```php
// Generate a new cropped image file
$cropped_path = timu_generate_focal_point_crop(
    $attachment_id,
    'primary',
    1080,  // Instagram post size
    1080,
    'instagram-post'  // Optional filename suffix
);

if ( ! is_wp_error( $cropped_path ) ) {
    echo "Cropped image saved to: " . $cropped_path;
}
```

#### Context-Aware Cropping

```php
// Get recommended focal point for a context
$focal_key = timu_get_recommended_focal_point( $attachment_id, 'instagram' );

// Use it for cropping
$crop_data = timu_crop_to_focal_point( $attachment_id, $focal_key, 1080, 1080 );
```

#### Preview Multiple Crops

```php
// Preview how a focal point works with common aspect ratios
$previews = timu_preview_focal_point_crops( $attachment_id, 'primary', 800 );

foreach ( $previews as $ratio => $preview ) {
    echo $preview['label'] . ": ";
    echo "Crop box: {$preview['crop_data']['width']} × {$preview['crop_data']['height']}px\n";
}
```

## API Reference

### Core Functions

#### `timu_set_focal_points( $attachment_id, $focal_points )`
Save multiple focal points for an image.

**Parameters:**
- `$attachment_id` (int) - The attachment post ID
- `$focal_points` (array) - Array of focal points with keys and data

**Returns:** (bool) True on success, false on failure

---

#### `timu_get_focal_points( $attachment_id )`
Get all focal points for an image.

**Parameters:**
- `$attachment_id` (int) - The attachment post ID

**Returns:** (array) Array of focal points or empty array

---

#### `timu_crop_to_focal_point( $attachment_id, $focal_point_key, $target_width, $target_height )`
Calculate crop coordinates centered on a focal point.

**Parameters:**
- `$attachment_id` (int) - The attachment post ID
- `$focal_point_key` (string) - The key of the focal point to use
- `$target_width` (int) - The desired crop width
- `$target_height` (int) - The desired crop height

**Returns:** (array|WP_Error) Crop coordinates array or WP_Error on failure

---

#### `timu_generate_focal_point_crop( $attachment_id, $focal_point_key, $target_width, $target_height, $suffix )`
Generate a new cropped image file using a focal point.

**Parameters:**
- `$attachment_id` (int) - The attachment post ID
- `$focal_point_key` (string) - The key of the focal point to use
- `$target_width` (int) - The desired crop width
- `$target_height` (int) - The desired crop height
- `$suffix` (string) - Optional suffix for the filename

**Returns:** (string|WP_Error) Path to the cropped image or WP_Error

---

### Helper Functions

#### `timu_add_focal_point( $attachment_id, $focal_point_key, $x, $y, $label )`
Add a single focal point to an image.

#### `timu_remove_focal_point( $attachment_id, $focal_point_key )`
Remove a single focal point from an image.

#### `timu_get_focal_point( $attachment_id, $focal_point_key )`
Get a specific focal point by key.

#### `timu_delete_focal_points( $attachment_id )`
Delete all focal points for an image.

#### `timu_get_recommended_focal_point( $attachment_id, $context )`
Get recommended focal point for a given context.

#### `timu_get_common_aspect_ratios()`
Get array of common aspect ratios with labels.

#### `timu_preview_focal_point_crops( $attachment_id, $focal_point_key, $preview_size )`
Preview crop bounds across multiple aspect ratios.

## Hooks

### Actions

#### `timu_focal_points_updated`
Triggered when focal points are updated.

```php
add_action( 'timu_focal_points_updated', function( $attachment_id, $focal_points ) {
    // Your custom logic
}, 10, 2 );
```

#### `timu_focal_points_deleted`
Triggered when focal points are deleted.

```php
add_action( 'timu_focal_points_deleted', function( $attachment_id ) {
    // Your custom logic
}, 10, 1 );
```

#### `timu_focal_point_crop_generated`
Triggered when a cropped image is generated.

```php
add_action( 'timu_focal_point_crop_generated', function( $attachment_id, $focal_point_key, $saved_path ) {
    // Your custom logic
}, 10, 3 );
```

### Filters

#### `timu_crop_coordinates`
Filter crop coordinates before returning.

```php
add_filter( 'timu_crop_coordinates', function( $crop_data, $attachment_id, $focal_point_key, $target_width, $target_height ) {
    // Modify crop data
    return $crop_data;
}, 10, 5 );
```

#### `timu_recommended_focal_point`
Filter recommended focal point for a context.

```php
add_filter( 'timu_recommended_focal_point', function( $recommended, $attachment_id, $context, $focal_points ) {
    if ( $context === 'instagram' && isset( $focal_points['social'] ) ) {
        return 'social';
    }
    return $recommended;
}, 10, 4 );
```

#### `timu_common_aspect_ratios`
Filter the list of common aspect ratios.

```php
add_filter( 'timu_common_aspect_ratios', function( $ratios ) {
    $ratios['21:9'] = array(
        'label' => 'Ultra Wide (21:9)',
        'width' => 21,
        'height' => 9,
    );
    return $ratios;
} );
```

## Admin Interface

### Focal Point Editor

When editing an image attachment in WordPress admin:

1. Navigate to Media Library
2. Click on an image to edit
3. Find the "Focal Points" meta box
4. Click on the image to add focal points
5. Or use the "Add Focal Point" button
6. Drag markers to adjust positions
7. Click "Save Focal Points" to persist changes
8. Click "Preview Crops" to see how crops will look

### Features of the Visual Editor

- **Interactive placement** - Click anywhere on the image to add a focal point
- **Drag and drop** - Move markers by dragging them
- **Real-time preview** - See marker positions update as you move them
- **Multiple points** - Add unlimited focal points per image
- **Labels** - Add descriptive labels to organize your focal points
- **Validation** - Coordinates are automatically clamped to valid ranges (0-100%)

## Integration Examples

### With Art Direction (Responsive Images)

```php
add_filter( 'wp_get_attachment_image_src', function( $image, $attachment_id, $size ) {
    if ( wp_is_mobile() ) {
        $focal_key = 'mobile';
    } else {
        $focal_key = 'desktop';
    }
    
    // Use context-specific focal point
    $cropped = timu_generate_focal_point_crop( $attachment_id, $focal_key, 800, 600 );
    
    if ( ! is_wp_error( $cropped ) ) {
        // Return cropped image info
    }
    
    return $image;
}, 10, 3 );
```

### With Watermarking

```php
add_filter( 'watermark_position', function( $position, $attachment_id ) {
    $focal_points = timu_get_focal_points( $attachment_id );
    
    // Avoid placing watermark on primary focal point
    if ( isset( $focal_points['primary'] ) ) {
        $focal = $focal_points['primary'];
        
        // Place watermark opposite to focal point
        if ( $focal['x'] < 50 ) {
            $position = 'bottom-right';
        } else {
            $position = 'bottom-left';
        }
    }
    
    return $position;
}, 10, 2 );
```

### Context-Aware Social Sharing

```php
function get_social_image_crop( $attachment_id, $network ) {
    $dimensions = array(
        'facebook'  => array( 1200, 630 ),
        'twitter'   => array( 1200, 675 ),
        'instagram' => array( 1080, 1080 ),
        'pinterest' => array( 1000, 1500 ),
    );
    
    if ( ! isset( $dimensions[ $network ] ) ) {
        return false;
    }
    
    list( $width, $height ) = $dimensions[ $network ];
    
    // Get recommended focal point for this network
    $focal_key = timu_get_recommended_focal_point( $attachment_id, $network );
    
    // Generate cropped image
    return timu_generate_focal_point_crop( 
        $attachment_id, 
        $focal_key, 
        $width, 
        $height,
        $network
    );
}
```

## Performance

- **Minimal data overhead** - Focal point data is ~1KB per image
- **On-demand calculations** - Crop coordinates calculated only when needed
- **No automatic generation** - Cropped images created only on request
- **Efficient storage** - Focal points stored as post meta in WordPress database

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- GD or Imagick image library

## License

GPL-2.0+

## Author

thisismyurl - https://github.com/thisismyurl

## Support

For issues and feature requests, please use the GitHub issue tracker:
https://github.com/thisismyurl/module-images-support-thisismyurl/issues
