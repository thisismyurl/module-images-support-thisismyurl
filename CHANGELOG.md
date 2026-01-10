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
