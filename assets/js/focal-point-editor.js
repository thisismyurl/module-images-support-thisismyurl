/**
 * Focal Point Editor JavaScript
 *
 * @package TIMU
 */

(function($) {
	'use strict';

	/**
	 * Focal Point Editor Object
	 */
	var FocalPointEditor = {
		
		/**
		 * Initialize the editor
		 */
		init: function() {
			this.bindEvents();
			this.renderExistingPoints();
		},
		
		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;
			
			// Click on image to add focal point
			$(document).on('click', '.timu-focal-image', function(e) {
				self.addFocalPointAtPosition(e);
			});
			
			// Add focal point button
			$(document).on('click', '.timu-add-focal-point', function(e) {
				e.preventDefault();
				self.addFocalPoint();
			});
			
			// Remove focal point
			$(document).on('click', '.timu-remove-focal-point', function(e) {
				e.preventDefault();
				$(this).closest('.timu-focal-point-item').remove();
				self.updateOverlay();
			});
			
			// Save focal points
			$(document).on('click', '.timu-save-focal-points', function(e) {
				e.preventDefault();
				self.saveFocalPoints();
			});
			
			// Preview crops
			$(document).on('click', '.timu-preview-crops', function(e) {
				e.preventDefault();
				self.previewCrops();
			});
			
			// Update overlay when values change
			$(document).on('input', '.timu-focal-x, .timu-focal-y', function() {
				self.updateOverlay();
			});
			
			// Highlight item on hover
			$(document).on('mouseenter', '.timu-focal-point-item', function() {
				var key = $(this).data('key');
				$('.timu-focal-point-marker[data-key="' + key + '"]').addClass('active');
				$(this).addClass('active');
			});
			
			$(document).on('mouseleave', '.timu-focal-point-item', function() {
				$('.timu-focal-point-marker').removeClass('active');
				$(this).removeClass('active');
			});
			
			// Make markers draggable
			this.initDraggable();
		},
		
		/**
		 * Render existing focal points on the image
		 */
		renderExistingPoints: function() {
			var self = this;
			$('.timu-focal-point-item').each(function() {
				var $item = $(this);
				var key = $item.data('key');
				var x = $item.find('.timu-focal-x').val();
				var y = $item.find('.timu-focal-y').val();
				
				self.addMarkerToOverlay(key, x, y);
			});
		},
		
		/**
		 * Add focal point at click position
		 */
		addFocalPointAtPosition: function(e) {
			var $image = $(e.currentTarget);
			var offset = $image.offset();
			var x = ((e.pageX - offset.left) / $image.width()) * 100;
			var y = ((e.pageY - offset.top) / $image.height()) * 100;
			
			// Clamp values
			x = Math.max(0, Math.min(100, x));
			y = Math.max(0, Math.min(100, y));
			
			this.addFocalPoint(x, y);
		},
		
		/**
		 * Add a new focal point
		 */
		addFocalPoint: function(x, y) {
			x = x || 50;
			y = y || 50;
			
			var key = 'focal_' + Date.now();
			var label = '';
			
			// Determine label based on count
			var count = $('.timu-focal-point-item').length;
			if (count === 0) {
				label = timuFocalPoint.strings.primary;
				key = 'primary';
			} else if (count === 1) {
				label = timuFocalPoint.strings.secondary;
				key = 'secondary';
			}
			
			var $item = $('<div class="timu-focal-point-item" data-key="' + key + '">' +
				'<div class="timu-focal-point-info">' +
					'<label>' + timuFocalPoint.strings.label + ' Key:' +
						'<input type="text" class="timu-focal-key" value="' + key + '" readonly>' +
					'</label>' +
					'<label>' + timuFocalPoint.strings.label + ':' +
						'<input type="text" class="timu-focal-label" value="' + label + '" placeholder="e.g., Main subject">' +
					'</label>' +
					'<label>X:' +
						'<input type="number" class="timu-focal-x" value="' + x.toFixed(1) + '" min="0" max="100" step="0.1">' +
					'</label>' +
					'<label>Y:' +
						'<input type="number" class="timu-focal-y" value="' + y.toFixed(1) + '" min="0" max="100" step="0.1">' +
					'</label>' +
					'<button type="button" class="button timu-remove-focal-point">' +
						timuFocalPoint.strings.removePoint +
					'</button>' +
				'</div>' +
			'</div>');
			
			$('.timu-focal-points-list').append($item);
			this.addMarkerToOverlay(key, x, y);
		},
		
		/**
		 * Add marker to overlay
		 */
		addMarkerToOverlay: function(key, x, y) {
			var $overlay = $('.timu-focal-points-overlay');
			var $marker = $('<div class="timu-focal-point-marker" data-key="' + key + '" style="left: ' + x + '%; top: ' + y + '%;"></div>');
			$overlay.append($marker);
		},
		
		/**
		 * Update overlay markers
		 */
		updateOverlay: function() {
			$('.timu-focal-points-overlay').empty();
			
			var self = this;
			$('.timu-focal-point-item').each(function() {
				var $item = $(this);
				var key = $item.data('key');
				var x = $item.find('.timu-focal-x').val();
				var y = $item.find('.timu-focal-y').val();
				
				self.addMarkerToOverlay(key, x, y);
			});
		},
		
		/**
		 * Initialize draggable markers
		 */
		initDraggable: function() {
			var self = this;
			var isDragging = false;
			var currentMarker = null;
			
			$(document).on('mousedown', '.timu-focal-point-marker', function(e) {
				e.preventDefault();
				isDragging = true;
				currentMarker = $(this);
				currentMarker.addClass('active');
			});
			
			$(document).on('mousemove', function(e) {
				if (!isDragging || !currentMarker) return;
				
				var $image = $('.timu-focal-image');
				var offset = $image.offset();
				var x = ((e.pageX - offset.left) / $image.width()) * 100;
				var y = ((e.pageY - offset.top) / $image.height()) * 100;
				
				// Clamp values
				x = Math.max(0, Math.min(100, x));
				y = Math.max(0, Math.min(100, y));
				
				// Update marker position
				currentMarker.css({
					left: x + '%',
					top: y + '%'
				});
				
				// Update form fields
				var key = currentMarker.data('key');
				var $item = $('.timu-focal-point-item[data-key="' + key + '"]');
				$item.find('.timu-focal-x').val(x.toFixed(1));
				$item.find('.timu-focal-y').val(y.toFixed(1));
			});
			
			$(document).on('mouseup', function() {
				if (currentMarker) {
					currentMarker.removeClass('active');
				}
				isDragging = false;
				currentMarker = null;
			});
		},
		
		/**
		 * Collect focal points data
		 */
		collectFocalPointsData: function() {
			var focalPoints = {};
			
			$('.timu-focal-point-item').each(function() {
				var $item = $(this);
				var key = $item.data('key');
				var label = $item.find('.timu-focal-label').val();
				var x = parseFloat($item.find('.timu-focal-x').val());
				var y = parseFloat($item.find('.timu-focal-y').val());
				
				focalPoints[key] = {
					x: x,
					y: y,
					label: label
				};
			});
			
			return focalPoints;
		},
		
		/**
		 * Save focal points via AJAX
		 */
		saveFocalPoints: function() {
			var self = this;
			var attachmentId = $('.timu-focal-image').data('attachment-id');
			var focalPoints = this.collectFocalPointsData();
			
			// Show loading state
			$('.timu-focal-point-editor').addClass('loading');
			this.showMessage(timuFocalPoint.strings.savingPoints, 'info');
			
			$.ajax({
				url: timuFocalPoint.ajaxUrl,
				type: 'POST',
				data: {
					action: 'timu_save_focal_points',
					nonce: timuFocalPoint.nonce,
					attachment_id: attachmentId,
					focal_points: JSON.stringify(focalPoints)
				},
				success: function(response) {
					$('.timu-focal-point-editor').removeClass('loading');
					
					if (response.success) {
						self.showMessage(timuFocalPoint.strings.pointsSaved, 'success');
					} else {
						self.showMessage(response.data.message || timuFocalPoint.strings.saveFailed, 'error');
					}
				},
				error: function() {
					$('.timu-focal-point-editor').removeClass('loading');
					self.showMessage(timuFocalPoint.strings.saveFailed, 'error');
				}
			});
		},
		
		/**
		 * Preview crops for all aspect ratios
		 */
		previewCrops: function() {
			var self = this;
			var attachmentId = $('.timu-focal-image').data('attachment-id');
			var focalPoints = this.collectFocalPointsData();
			
			if (Object.keys(focalPoints).length === 0) {
				this.showMessage('Please add at least one focal point first.', 'error');
				return;
			}
			
			// Get first focal point key for preview
			var firstKey = Object.keys(focalPoints)[0];
			
			// Show loading state
			$('.timu-focal-point-editor').addClass('loading');
			
			$.ajax({
				url: timuFocalPoint.ajaxUrl,
				type: 'POST',
				data: {
					action: 'timu_preview_crops',
					nonce: timuFocalPoint.nonce,
					attachment_id: attachmentId,
					focal_point_key: firstKey
				},
				success: function(response) {
					$('.timu-focal-point-editor').removeClass('loading');
					
					if (response.success) {
						self.renderCropPreviews(response.data.previews);
					} else {
						self.showMessage(response.data.message, 'error');
					}
				},
				error: function() {
					$('.timu-focal-point-editor').removeClass('loading');
					self.showMessage('Failed to load crop previews.', 'error');
				}
			});
		},
		
		/**
		 * Render crop previews
		 */
		renderCropPreviews: function(previews) {
			var $container = $('.timu-crop-preview-container');
			var $list = $('.timu-crop-preview-list');
			
			$list.empty();
			
			$.each(previews, function(key, preview) {
				var $item = $('<div class="timu-crop-preview-item">' +
					'<h5>' + preview.label + '</h5>' +
					'<div class="timu-crop-preview-dimensions">' +
						'Crop: ' + preview.crop_data.width + ' × ' + preview.crop_data.height + 'px<br>' +
						'Position: (' + preview.crop_data.x + ', ' + preview.crop_data.y + ')' +
					'</div>' +
				'</div>');
				
				$list.append($item);
			});
			
			$container.show();
		},
		
		/**
		 * Show status message
		 */
		showMessage: function(message, type) {
			var $status = $('.timu-status-message');
			$status.removeClass('success error info').addClass(type);
			$status.text(message);
			
			setTimeout(function() {
				$status.removeClass('success error info').hide();
			}, 5000);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.timu-focal-point-editor').length) {
			FocalPointEditor.init();
		}
	});

})(jQuery);
