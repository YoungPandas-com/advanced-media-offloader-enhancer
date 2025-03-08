/**
 * Advanced Media Offloader Enhancer - Admin JS
 */
(function($) {
    'use strict';

    // Main admin script
    const ADVMOEnhancer = {
        init: function() {
            this.setupEventListeners();
            this.checkForActiveProcess();
        },

        setupEventListeners: function() {
            // Diagnostic tools
            $(document).on('click', '#run-advmo-diagnostics', this.runDiagnostics);
            
            // Cloudflare actions
            $(document).on('click', '#advmo-purge-media-cache', this.purgeMediaCache);

            // Error dashboard actions
            $(document).on('click', '.advmo-enhancer-retry-all-errors', this.retryAllErrors);
        },

        checkForActiveProcess: function() {
            // Check if there's an active auto-processing task on page load
            if (typeof advmoEnhancer !== 'undefined' && advmoEnhancer.bulkData && advmoEnhancer.bulkData.autoProcessing) {
                // Show notification or initialize progress tracking
                this.showActiveProcessNotification();
            }
        },

        showActiveProcessNotification: function() {
            const {bulkData} = advmoEnhancer;
            
            // Only show if we're not on the media overview page
            if (window.location.href.indexOf('page=advmo_media_overview') === -1) {
                const message = `
                    <div class="notice notice-info is-dismissible advmo-active-process-notice">
                        <p>
                            <strong>${advmoEnhancer.i18n.processing}</strong> 
                            ${bulkData.processedFiles} of ${bulkData.totalFiles} files processed 
                            (Batch ${bulkData.currentBatch} of ${bulkData.totalBatches})
                        </p>
                        <p>
                            <a href="${window.location.origin}/wp-admin/admin.php?page=advmo_media_overview" class="button">
                                View Progress
                            </a>
                        </p>
                    </div>
                `;
                
                $(message).insertAfter('.wrap h1').first();
            }
        },

        runDiagnostics: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $results = $('#advmo-diagnostics-results');
            
            $button.prop('disabled', true).text('Running diagnostics...');
            $results.html('<p>Running system diagnostics...</p>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'advmo_enhancer_run_diagnostics',
                    nonce: advmoEnhancer.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Run Diagnostics');
                    
                    if (response.success) {
                        $results.html(response.data.html);
                    } else {
                        $results.html(`<div class="notice notice-error"><p>${response.data.message || 'Error running diagnostics'}</p></div>`);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Run Diagnostics');
                    $results.html('<div class="notice notice-error"><p>Error running diagnostics. Please try again.</p></div>');
                }
            });
        },

        purgeMediaCache: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $status = $('#advmo-purge-media-status');
            
            if (!confirm('Are you sure you want to purge all media files from the CDN cache?')) {
                return;
            }
            
            $button.prop('disabled', true);
            $status.text('Purging cache...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'advmo_enhancer_purge_media_cache',
                    nonce: advmoEnhancer.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        $status.text(response.data.message);
                        setTimeout(() => {
                            $status.text('');
                        }, 5000);
                    } else {
                        $status.text(response.data.message || 'Error purging cache');
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $status.text('Error purging cache. Please try again.');
                }
            });
        },

        retryAllErrors: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            
            if (!confirm('Are you sure you want to retry all failed offloads? This may take some time.')) {
                return;
            }
            
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'advmo_enhancer_retry_all_errors',
                    nonce: advmoEnhancer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error retrying offloads');
                        $button.prop('disabled', false).text('Retry All Failed Offloads');
                    }
                },
                error: function() {
                    alert('Error retrying offloads. Please try again.');
                    $button.prop('disabled', false).text('Retry All Failed Offloads');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ADVMOEnhancer.init();
    });

})(jQuery);