/**
 * TIMU Admin JavaScript
 * 
 * Handles admin UI interactions for copyright protection.
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Verify ownership button
        $('.timu-verify-ownership').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $button.closest('.timu-copyright-info').find('.timu-result');
            var attachmentId = $button.data('attachment-id');
            
            $button.prop('disabled', true).text('Verifying...');
            $result.html('<p>Verifying ownership...</p>');
            
            $.ajax({
                url: timuAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'timu_verify_ownership',
                    nonce: timuAdmin.nonce,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        var verification = response.data;
                        var html = '<div class="notice notice-' + (verification.verified ? 'success' : 'warning') + ' inline">';
                        html += '<p><strong>Verification Result:</strong></p>';
                        html += '<ul>';
                        html += '<li>Status: ' + (verification.verified ? '✓ VERIFIED' : '✗ NOT VERIFIED') + '</li>';
                        html += '<li>Confidence: ' + Math.round(verification.confidence * 100) + '%</li>';
                        html += '<li>Detected Layers: ' + verification.detected_layers + ' / 3</li>';
                        
                        if (verification.layers) {
                            html += '<li>Metadata: ' + (verification.layers.metadata ? '✓ Found' : '✗ Not found') + '</li>';
                            html += '<li>DCT Fingerprint: ' + (verification.layers.dct_fingerprint ? '✓ ID: ' + verification.layers.dct_fingerprint : '✗ Not found') + '</li>';
                            html += '<li>LSB Fingerprint: ' + (verification.layers.lsb_fingerprint ? '✓ ID: ' + verification.layers.lsb_fingerprint : '✗ Not found') + '</li>';
                        }
                        
                        html += '</ul></div>';
                        $result.html(html);
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Error verifying ownership.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Verify Ownership');
                }
            });
        });
        
        // Embed ownership button
        $('.timu-embed-ownership').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $button.closest('.timu-copyright-info').find('.timu-result');
            var attachmentId = $button.data('attachment-id');
            
            if (!confirm('This will embed/re-embed ownership layers in the image. Continue?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Embedding...');
            $result.html('<p>Embedding ownership layers...</p>');
            
            $.ajax({
                url: timuAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'timu_embed_ownership',
                    nonce: timuAdmin.nonce,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.data.results;
                        var html = '<div class="notice notice-success inline">';
                        html += '<p><strong>Embedding Complete:</strong></p>';
                        html += '<ul>';
                        html += '<li>Metadata: ' + (results.metadata ? '✓ Embedded' : '✗ Failed') + '</li>';
                        html += '<li>DCT Fingerprint: ' + (results.dct_fingerprint ? '✓ Embedded' : '✗ Failed') + '</li>';
                        html += '<li>LSB Fingerprint: ' + (results.lsb_fingerprint ? '✓ Embedded' : '✗ Failed') + '</li>';
                        html += '</ul>';
                        html += '<p><em>Refresh the page to see updated status.</em></p>';
                        html += '</div>';
                        $result.html(html);
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Error embedding ownership.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Embed/Re-embed Layers');
                }
            });
        });
        
        // Generate DMCA evidence button
        $('.timu-generate-dmca').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $button.closest('.timu-copyright-info').find('.timu-result');
            var attachmentId = $button.data('attachment-id');
            
            var infringingUrl = prompt('Enter the URL of the infringing image (optional):');
            
            $button.prop('disabled', true).text('Generating...');
            $result.html('<p>Generating DMCA evidence package...</p>');
            
            $.ajax({
                url: timuAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'timu_generate_dmca',
                    nonce: timuAdmin.nonce,
                    attachment_id: attachmentId,
                    infringing_url: infringingUrl || ''
                },
                success: function(response) {
                    if (response.success) {
                        // Open evidence report in new window
                        var win = window.open('', '_blank');
                        win.document.write(response.data.html);
                        win.document.close();
                        
                        $result.html('<div class="notice notice-success inline"><p>DMCA evidence package generated and opened in new window.</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Error generating DMCA evidence.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate DMCA Evidence');
                }
            });
        });
    });
    
})(jQuery);
