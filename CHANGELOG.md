# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-10

### Added
- Multi-focal point support for images
  - Define unlimited focal points per image with x/y coordinates (0-100%)
  - Each focal point can have a descriptive label
  - Store focal points as post meta (`_timu_focal_points`)
  
- Core API functions
  - `timu_set_focal_points()` - Save multiple focal points for an image
  - `timu_get_focal_points()` - Retrieve all focal points
  - `timu_get_focal_point()` - Get a specific focal point by key
  - `timu_add_focal_point()` - Add a single focal point
  - `timu_remove_focal_point()` - Remove a specific focal point
  - `timu_delete_focal_points()` - Delete all focal points
  
- Cropping functions with focal point support
  - `timu_crop_to_focal_point()` - Calculate crop coordinates centered on a focal point
  - `timu_generate_focal_point_crop()` - Generate actual cropped image files
  - `timu_get_recommended_focal_point()` - Context-aware focal point selection
  - `timu_preview_focal_point_crops()` - Preview crops for multiple aspect ratios
  - `timu_get_common_aspect_ratios()` - Get common aspect ratios (1:1, 4:3, 16:9, etc.)
  
- Admin UI components
  - Visual focal point editor with interactive marker placement
  - Click to add focal points directly on images
  - Drag-and-drop to reposition markers
  - Real-time coordinate updates
  - Label input for each focal point
  - Save focal points via AJAX
  - Preview crops for common aspect ratios
  - Integration with WordPress media library
  - Meta box on attachment edit screen
  
- Hooks and filters for extensibility
  - Action: `timu_focal_points_updated` - Triggered when focal points are saved
  - Action: `timu_focal_points_deleted` - Triggered when focal points are deleted
  - Action: `timu_focal_point_crop_generated` - Triggered when a crop is generated
  - Filter: `timu_crop_coordinates` - Modify crop coordinates
  - Filter: `timu_recommended_focal_point` - Customize context recommendations
  - Filter: `timu_common_aspect_ratios` - Add/modify aspect ratios
  
- Assets
  - CSS styling for focal point editor (assets/css/focal-point-editor.css)
  - JavaScript for interactive editing (assets/js/focal-point-editor.js)
  - Responsive design supporting mobile and desktop
  
- Documentation
  - Comprehensive README with API documentation
  - Usage examples for basic operations
  - Integration examples for social media, watermarking, and art direction
  - Code examples in examples/ directory
  
- Security features
  - Input sanitization (sanitize_key, sanitize_text_field)
  - Output escaping (esc_html, esc_attr, esc_url)
  - Nonce verification for AJAX requests
  - Permission checks (current_user_can)
  - Coordinate validation and clamping (0-100%)
  - Boundary checking for crop calculations

### Technical Details
- Minimum PHP version: 7.0
- Minimum WordPress version: 5.0
- Tested up to WordPress: 6.4
- License: GPL-2.0+
- Performance: Minimal data overhead (~1KB per image)
- Storage: Focal points stored as serialized post meta

[1.0.0]: https://github.com/thisismyurl/module-images-support-thisismyurl/releases/tag/v1.0.0
All notable changes to the Module Images Support WordPress plugin will be documented in this file.

## [1.0.0] - 2024-01-10

### Added - Intelligent Compression Engine

#### Core Features
- **Multi-Algorithm Comparison Engine**: Automatically compares MozJPEG, Guetzli, WebP, and AVIF formats
- **SSIM Quality Scoring**: Uses Structural Similarity Index to ensure visual quality preservation
- **Composite Scoring System**: Balances file size savings (40%) and quality preservation (60%)
- **Automatic Format Selection**: Selects the best compression result for each image
- **Quality Threshold Enforcement**: Rejects compressed images below configurable SSIM threshold (default: 0.95)

#### Compression Algorithms
- **MozJPEG Support**: Optimized JPEG encoder with superior compression
- **Guetzli Support**: Perceptual JPEG encoder for high-quality images
- **WebP Support**: Modern format with excellent compression (Chrome 23+, Firefox 65+)
- **AVIF Support**: Next-generation format with best-in-class compression (Chrome 85+, Firefox 93+)

#### WordPress Integration
- **Admin Settings Page**: Settings → Image Compression with full configuration options
- **Auto-Compression on Upload**: Automatically compress images when uploaded to WordPress
- **WordPress Filters**: Hooks into `wp_handle_upload_prefilter` for seamless integration
- **Activation/Deactivation Hooks**: Proper plugin lifecycle management with cleanup

#### API Functions
- `timu_intelligent_compress()`: Main compression function with multi-algorithm comparison
- `timu_batch_compress()`: Batch process multiple images
- `timu_calculate_ssim()`: Calculate quality score between images
- `timu_get_browser_best_format()`: Browser-based format negotiation
- `timu_compress_mozjpeg()`: MozJPEG compression
- `timu_compress_guetzli()`: Guetzli compression
- `timu_compress_webp()`: WebP compression
- `timu_compress_avif()`: AVIF compression

#### Browser Support
- **AVIF**: Chrome 85+, Firefox 93+, Opera 71+
- **WebP**: Chrome 23+, Firefox 65+, Edge 18+, Opera 12.1+, Safari 14+
- **JPEG**: All browsers (universal fallback)

#### Configuration Options
- Enable/disable auto-compression on upload
- Target quality level (1-100)
- Minimum quality score threshold (SSIM 0-1)
- Algorithm selection (choose which formats to compare)
- Scoring weights (balance quality vs. file size)

#### Documentation
- Comprehensive README with installation, usage, and API reference
- Example usage scripts demonstrating all features
- Test suite for validating core functionality
- Inline code documentation with PHPDoc comments

#### Performance
- **Before**: Single compression format (JPG at quality 75-85)
- **After**: Auto-selects optimal format per image
- **Improvement**: 20-40% additional savings vs. single algorithm

#### Security
- All shell commands use `escapeshellarg()` for injection prevention
- File existence validation before operations
- WordPress nonce verification in admin forms
- Input sanitization with `intval()` and `floatval()`
- ABSPATH checks in all PHP files

#### Fallbacks
- GD library fallback for SSIM when ImageMagick not available
- PHP native WebP/AVIF support when binaries unavailable
- Graceful degradation when compression algorithms not installed
- Automatic detection of available algorithms

### Implementation Phases Completed
- ✅ Phase 1: Core intelligent compression engine
- ✅ Phase 1.5: Compare MozJPEG vs. WebP
- ✅ Phase 2: Add AVIF support
- ✅ Phase 3: SSIM quality scoring
- ✅ Phase 4: Batch processing with algorithm selection
- ✅ Phase 5: WordPress admin integration
- ✅ Phase 6: Browser-based format negotiation

### Technical Details
- Minimum PHP Version: 7.4
- Recommended PHP Version: 8.1+ (for native AVIF support)
- Required Extensions: GD library with WebP support
- Optional Binaries: cjpeg, guetzli, avifenc, ImageMagick compare

### Files Added
- `module-images-support.php`: Main plugin file with core compression engine
- `admin-settings.php`: WordPress admin settings page
- `examples.php`: Usage examples and API demonstrations
- `tests.php`: Test suite for validation
- `.gitignore`: Git ignore patterns for temporary files
- `CHANGELOG.md`: This changelog file
- `README.md`: Updated comprehensive documentation

## [Unreleased]

### Planned Features
- Lazy loading integration
- CDN integration for format-specific URLs
- Image optimization statistics dashboard
- Scheduled batch processing via WP-Cron
- REST API endpoints for programmatic access
- Integration with popular page builders
- Advanced SSIM calculation options
- Custom compression profiles
