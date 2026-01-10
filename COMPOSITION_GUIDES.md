# Composition Guides Feature

This feature adds visual composition guides (Rule of Thirds and Golden Ratio) to assist users during manual image cropping in WordPress.

## Features

### Grid Types

1. **Rule of Thirds**: Divides the image into thirds both horizontally and vertically, creating 9 equal sections with 4 intersection points
2. **Golden Ratio**: Displays a Fibonacci spiral overlay based on the golden ratio (φ ≈ 1.618)

### Controls

- **Toggle On/Off**: Enable or disable grid overlay
- **Grid Type Selector**: Switch between Rule of Thirds and Golden Ratio
- **Opacity Slider**: Adjust transparency of the overlay (0-100%)

## Implementation

### PHP Functions

Located in `includes/composition-guides.php`:

- `timu_render_rule_of_thirds_grid($width, $height)` - Generates Rule of Thirds grid
- `timu_render_golden_ratio_spiral($width, $height)` - Generates Golden Ratio spiral
- `timu_generate_grid_overlay($grid_type, $width, $height)` - Returns base64-encoded PNG

### JavaScript

Located in `assets/js/composition-guides.js`:

- Creates control panel UI in WordPress media modal
- Handles user interactions (toggle, grid type selection, opacity adjustment)
- Generates grid overlays via AJAX
- Displays canvas overlays on images

### CSS

Located in `assets/css/composition-guides.css`:

- Styles for control panel
- Positioning for grid overlays
- Responsive design adjustments

### AJAX Handler

Located in `includes/admin-ajax.php`:

- `timu_ajax_generate_grid()` - Handles AJAX requests for grid generation
- Validates input parameters
- Returns base64-encoded grid images

## Usage

1. Open WordPress Media Library
2. Select an image for editing
3. The Composition Guides control panel appears in the top-right corner
4. Enable grid overlay by checking the checkbox
5. Select desired grid type (Rule of Thirds or Golden Ratio)
6. Adjust opacity as needed
7. Use the grid as a visual guide for cropping

## Technical Details

### Grid Generation

- **Rule of Thirds**: Uses GD library to draw 4 lines (2 vertical, 2 horizontal) at 1/3 and 2/3 positions
- **Golden Ratio**: Calculates 100 points along a logarithmic spiral using φ and exponential growth
- Both grids are rendered on transparent PNG canvases with anti-aliasing

### Color Scheme

- Rule of Thirds: White lines with 50% transparency (`rgba(255, 255, 255, 50)`)
- Golden Ratio: Gold lines with 80% transparency (`rgba(255, 200, 0, 80)`)

### Performance

- Grids are generated on-demand via AJAX
- Maximum supported dimensions: 5000x5000 pixels
- PNG output is optimized for small file sizes

## Browser Compatibility

- Requires HTML5 Canvas support
- Works in all modern browsers (Chrome, Firefox, Safari, Edge)
- Requires WordPress 5.0+

## Dependencies

- PHP GD Library (for server-side image generation)
- jQuery (bundled with WordPress)
- WordPress Media Library

## Security

- AJAX requests are protected with WordPress nonces
- Input validation for grid types and dimensions
- Sanitization of user inputs
