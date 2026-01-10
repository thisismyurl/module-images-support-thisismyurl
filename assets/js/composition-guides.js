/**
 * Composition Guides JavaScript
 * Handles grid overlay display and controls
 */

(function($) {
    'use strict';
    
    const CompositionGuides = {
        currentGridType: 'rule-of-thirds',
        gridOpacity: 0.8,
        gridEnabled: false,
        
        /**
         * Initialize composition guides
         */
        init: function() {
            this.createControls();
            this.bindEvents();
        },
        
        /**
         * Create grid control UI
         */
        createControls: function() {
            // Create control panel
            const controlsHTML = `
                <div id="timu-composition-controls" class="timu-controls-panel">
                    <h3>Composition Guides</h3>
                    <div class="timu-control-group">
                        <label>
                            <input type="checkbox" id="timu-grid-toggle" />
                            Enable Grid Overlay
                        </label>
                    </div>
                    <div class="timu-control-group">
                        <label for="timu-grid-type">Grid Type:</label>
                        <select id="timu-grid-type">
                            <option value="rule-of-thirds">Rule of Thirds</option>
                            <option value="golden-ratio">Golden Ratio</option>
                        </select>
                    </div>
                    <div class="timu-control-group">
                        <label for="timu-grid-opacity">Opacity:</label>
                        <input type="range" id="timu-grid-opacity" min="0" max="100" value="80" />
                        <span id="timu-opacity-value">80%</span>
                    </div>
                </div>
            `;
            
            // Add controls to media modal when it opens
            $(document).on('DOMNodeInserted', function(e) {
                if ($(e.target).hasClass('media-modal')) {
                    setTimeout(function() {
                        if ($('.media-frame-content').length && !$('#timu-composition-controls').length) {
                            $('.media-frame-content').append(controlsHTML);
                        }
                    }, 500);
                }
            });
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Toggle grid
            $(document).on('change', '#timu-grid-toggle', function() {
                self.gridEnabled = $(this).is(':checked');
                self.updateGridDisplay();
            });
            
            // Change grid type
            $(document).on('change', '#timu-grid-type', function() {
                self.currentGridType = $(this).val();
                if (self.gridEnabled) {
                    self.updateGridDisplay();
                }
            });
            
            // Change opacity
            $(document).on('input', '#timu-grid-opacity', function() {
                self.gridOpacity = $(this).val() / 100;
                $('#timu-opacity-value').text($(this).val() + '%');
                self.updateGridOpacity();
            });
            
            // Listen for crop editor events
            $(document).on('timu_crop_editor_opened', function(e) {
                const detail = e.detail || e.originalEvent.detail;
                if (detail && detail.grid_type) {
                    self.currentGridType = detail.grid_type;
                    $('#timu-grid-type').val(detail.grid_type);
                }
                self.updateGridDisplay();
            });
        },
        
        /**
         * Update grid display
         */
        updateGridDisplay: function() {
            if (!this.gridEnabled) {
                this.removeGrid();
                return;
            }
            
            this.generateAndShowGrid();
        },
        
        /**
         * Generate and display grid overlay
         */
        generateAndShowGrid: function() {
            const self = this;
            
            // Find image element in media modal
            const $imageContainer = $('.media-frame-content .attachment-display-settings, .media-frame-content .attachment-details, .media-frame-content img').first().closest('div');
            const $image = $imageContainer.find('img').first();
            
            if (!$image.length) {
                console.warn('TIMU: No image found for grid overlay');
                return;
            }
            
            const imageWidth = $image.width() || 800;
            const imageHeight = $image.height() || 600;
            
            // Generate grid via AJAX
            $.ajax({
                url: timuData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'timu_generate_grid',
                    nonce: timuData.nonce,
                    grid_type: this.currentGridType,
                    width: imageWidth,
                    height: imageHeight
                },
                success: function(response) {
                    if (response.success) {
                        self.displayGrid(response.data.grid_data, imageWidth, imageHeight);
                    } else {
                        console.error('TIMU: Failed to generate grid:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TIMU: AJAX error:', error);
                }
            });
        },
        
        /**
         * Display grid overlay on canvas
         */
        displayGrid: function(gridData, width, height) {
            // Remove existing grid
            this.removeGrid();
            
            // Create canvas element
            const canvas = document.createElement('canvas');
            canvas.id = 'timu-crop-grid';
            canvas.width = width;
            canvas.height = height;
            canvas.style.position = 'absolute';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.pointerEvents = 'none';
            canvas.style.zIndex = '9999';
            canvas.style.opacity = this.gridOpacity;
            
            // Get canvas context
            const ctx = canvas.getContext('2d');
            
            // Load and draw grid image
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0, width, height);
            };
            img.src = gridData;
            
            // Find container and append canvas
            const $imageContainer = $('.media-frame-content img').first().parent();
            if ($imageContainer.length) {
                $imageContainer.css('position', 'relative');
                $imageContainer.append(canvas);
            }
        },
        
        /**
         * Update grid opacity
         */
        updateGridOpacity: function() {
            const $grid = $('#timu-crop-grid');
            if ($grid.length) {
                $grid.css('opacity', this.gridOpacity);
            }
        },
        
        /**
         * Remove grid overlay
         */
        removeGrid: function() {
            $('#timu-crop-grid').remove();
        },
        
        /**
         * Draw Rule of Thirds grid on canvas
         */
        drawRuleOfThirds: function(ctx, width, height) {
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
            ctx.lineWidth = 2;
            
            // Vertical lines
            const x1 = width / 3;
            const x2 = 2 * width / 3;
            
            ctx.beginPath();
            ctx.moveTo(x1, 0);
            ctx.lineTo(x1, height);
            ctx.stroke();
            
            ctx.beginPath();
            ctx.moveTo(x2, 0);
            ctx.lineTo(x2, height);
            ctx.stroke();
            
            // Horizontal lines
            const y1 = height / 3;
            const y2 = 2 * height / 3;
            
            ctx.beginPath();
            ctx.moveTo(0, y1);
            ctx.lineTo(width, y1);
            ctx.stroke();
            
            ctx.beginPath();
            ctx.moveTo(0, y2);
            ctx.lineTo(width, y2);
            ctx.stroke();
        },
        
        /**
         * Draw Golden Ratio spiral on canvas
         */
        drawGoldenRatio: function(ctx, width, height) {
            ctx.strokeStyle = 'rgba(255, 200, 0, 0.8)';
            ctx.lineWidth = 2;
            
            const centerX = width / 2;
            const centerY = height / 2;
            
            ctx.beginPath();
            for (let i = 0; i < 100; i++) {
                const angle = i * 0.1;
                const radius = (width / 4) * Math.exp(angle / (2 * Math.PI));
                const x = centerX + radius * Math.cos(angle);
                const y = centerY + radius * Math.sin(angle);
                
                if (i === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            }
            ctx.stroke();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        CompositionGuides.init();
    });
    
})(jQuery);
