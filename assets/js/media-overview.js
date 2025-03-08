/**
 * Advanced Media Offloader Enhancer - Media Overview JS
 */
(function($) {
    'use strict';

    // Media Overview script
    const ADVMOMediaOverview = {
        // Settings and state
        settings: {
            checkInterval: 3000, // 3 seconds
            maxConsecutiveErrors: 5
        },
        
        state: {
            isAutoProcessing: false,
            isPaused: false,
            intervalId: null,
            errorCount: 0,
            startTime: 0,
            currentBatch: 0,
            totalBatches: 0,
            processedFiles: 0,
            totalFiles: 0
        },

        // Elements
        elements: {
            originalButton: null,
            autoProcessCheckbox: null,
            statusContainer: null,
            progressContainer: null,
            progressBar: null,
            batchProgress: null,
            fileProgress: null,
            timeEstimate: null,
            pauseButton: null,
            cancelButton: null
        },

        /**
         * Initialize the module
         */
        init: function() {
            this.cacheElements();
            this.loadInitialState();
            this.setupEventListeners();
            
            // Check if we need to auto-resume
            if (this.state.isAutoProcessing && !this.state.isPaused) {
                this.startProgressTracking();
            }
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.originalButton = $('#bulk-offload-button');
            this.elements.autoProcessCheckbox = $('#advmo-enhancer-auto-process');
            this.elements.statusContainer = $('#advmo-enhancer-auto-process-status');
            this.elements.progressContainer = $('.advmo-enhancer-auto-processing-notice');
            this.elements.progressBar = $('.advmo-enhancer-progress-bar');
            this.elements.batchProgress = $('.advmo-enhancer-batch-progress');
            this.elements.fileProgress = $('.advmo-enhancer-file-progress');
            this.elements.timeEstimate = $('.advmo-enhancer-time-estimate');
            this.elements.pauseButton = $('.advmo-enhancer-pause-auto-processing');
            this.elements.cancelButton = $('.advmo-enhancer-cancel-auto-processing');
        },

        /**
         * Load initial state from the data provided by the server
         */
        loadInitialState: function() {
            const {bulkData} = advmoEnhancerMedia;
            
            this.state.isAutoProcessing = bulkData.autoProcessing;
            this.state.isPaused = bulkData.status === 'paused';
            this.state.startTime = bulkData.startTime;
            this.state.currentBatch = bulkData.currentBatch;
            this.state.totalBatches = bulkData.totalBatches;
            this.state.processedFiles = bulkData.processedFiles;
            this.state.totalFiles = bulkData.totalFiles;
            
            // Update UI to reflect current state
            this.updateUI();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            // Replace the original bulk offload button click handler
            if (this.elements.originalButton.length) {
                this.elements.originalButton.off('click').on('click', this.handleOffloadButtonClick.bind(this));
            }
            
            // Auto-process checkbox
            this.elements.autoProcessCheckbox.on('change', this.toggleAutoProcess.bind(this));
            
            // Pause button
            this.elements.pauseButton.on('click', this.togglePause.bind(this));
            
            // Cancel button
            this.elements.cancelButton.on('click', this.cancelAutoProcessing.bind(this));
        },

        /**
         * Handle the original offload button click
         */
        handleOffloadButtonClick: function(e) {
            e.preventDefault();
            
            const isAutoProcess = this.elements.autoProcessCheckbox.is(':checked');
            
            // Start the offload process with auto-processing if enabled
            this.startOffloadProcess(isAutoProcess);
        },

        /**
         * Toggle auto-processing feature
         */
        toggleAutoProcess: function() {
            const isEnabled = this.elements.autoProcessCheckbox.is(':checked');
            
            if (isEnabled) {
                this.elements.statusContainer.slideDown();
            } else {
                this.elements.statusContainer.slideUp();
            }
        },

        /**
         * Start the offload process
         */
        startOffloadProcess: function(isAutoProcess) {
            // Show loading state
            this.elements.originalButton.prop('disabled', true).text('Starting...');
            
            // Reset state
            this.state.errorCount = 0;
            this.state.startTime = Math.floor(Date.now() / 1000);
            
            // Make AJAX request to start the process
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'advmo_enhancer_start_bulk_offload',
                    nonce: advmoEnhancerMedia.nonce,
                    auto_process: isAutoProcess
                },
                success: (response) => {
                    this.elements.originalButton.hide();
                    
                    if (response.success) {
                        this.state.isAutoProcessing = isAutoProcess;
                        this.state.currentBatch = response.data.current_batch;
                        this.state.totalBatches = response.data.total_batches;
                        this.state.totalFiles = response.data.total_files;
                        
                        // Start tracking progress
                        if (isAutoProcess) {
                            this.displayProgressUI();
                            this.startProgressTracking();
                        }
                    } else {
                        alert(response.data.message || advmoEnhancerMedia.i18n.errorOccurred);
                        this.elements.originalButton.prop('disabled', false).text('Offload Now').show();
                    }
                },
                error: () => {
                    alert(advmoEnhancerMedia.i18n.errorOccurred);
                    this.elements.originalButton.prop('disabled', false).text('Offload Now').show();
                }
            });
        },

        /**
         * Start tracking progress
         */
        startProgressTracking: function() {
            // Clear any existing interval
            if (this.state.intervalId) {
                clearInterval(this.state.intervalId);
            }
            
            // Set up interval to check progress
            this.state.intervalId = setInterval(() => {
                this.checkProgress();
            }, this.settings.checkInterval);
            
            // Do an immediate check
            this.checkProgress();
        },

        /**
         * Check the current progress
         */
        checkProgress: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'advmo_enhancer_check_progress',
                    nonce: advmoEnhancerMedia.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.state.errorCount = 0;
                        this.processProgressUpdate(response.data);
                    } else {
                        this.handleProgressError();
                    }
                },
                error: () => {
                    this.handleProgressError();
                }
            });
        },

        /**
         * Process progress update data
         */
        processProgressUpdate: function(data) {
            // Update state
            this.state.currentBatch = data.current_batch;
            this.state.totalBatches = data.total_batches;
            this.state.processedFiles = data.processed_files;
            this.state.totalFiles = data.total_files;
            
            // Check if status has changed
            if (data.status !== 'processing') {
                if (data.status === 'completed') {
                    this.handleCompletion();
                } else if (data.status === 'paused') {
                    this.state.isPaused = true;
                    this.updateUI();
                    
                    // Stop the interval
                    if (this.state.intervalId) {
                        clearInterval(this.state.intervalId);
                        this.state.intervalId = null;
                    }
                } else if (data.status === 'cancelled') {
                    this.handleCancellation();
                }
            }
            
            // Check if a batch has completed
            if (data.batch_complete && !data.all_complete) {
                // Batch is complete, starting next batch
                this.showBatchCompleteNotification(data.current_batch);
            }
            
            // Update the UI
            this.updateUI();
        },

        /**
         * Handle error in progress checking
         */
        handleProgressError: function() {
            this.state.errorCount++;
            
            // If too many consecutive errors, stop tracking
            if (this.state.errorCount >= this.settings.maxConsecutiveErrors) {
                if (this.state.intervalId) {
                    clearInterval(this.state.intervalId);
                    this.state.intervalId = null;
                }
                
                alert('Error tracking progress. Please reload the page and check the status.');
            }
        },

        /**
         * Handle completion of all batches
         */
        handleCompletion: function() {
            // Stop tracking
            if (this.state.intervalId) {
                clearInterval(this.state.intervalId);
                this.state.intervalId = null;
            }
            
            // Update state
            this.state.isAutoProcessing = false;
            
            // Show completion message
            this.showCompletionMessage();
            
            // Update UI
            this.updateUI();
        },

        /**
         * Handle cancellation
         */
        handleCancellation: function() {
            // Stop tracking
            if (this.state.intervalId) {
                clearInterval(this.state.intervalId);
                this.state.intervalId = null;
            }
            
            // Update state
            this.state.isAutoProcessing = false;
            
            // Show cancellation message
            alert('The offload process has been cancelled.');
            
            // Reload page to reset UI
            location.reload();
        },

        /**
         * Toggle pause state
         */
        togglePause: function() {
            const action = this.state.isPaused ? 'resume' : 'pause';
            
            // Make AJAX request to pause/resume
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action === 'pause' ? 'advmo_enhancer_pause_autoprocessing' : 'advmo_enhancer_resume_autoprocessing',
                    nonce: advmoEnhancerMedia.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.state.isPaused = action === 'pause';
                        
                        // Update UI
                        this.updateUI();
                        
                        // Start/stop tracking
                        if (action === 'resume') {
                            this.startProgressTracking();
                        } else {
                            if (this.state.intervalId) {
                                clearInterval(this.state.intervalId);
                                this.state.intervalId = null;
                            }
                        }
                    } else {
                        alert(response.data.message || 'Error changing process state.');
                    }
                },
                error: () => {
                    alert('Error changing process state. Please try again.');
                }
            });
        },

        /**
         * Cancel auto-processing
         */
        cancelAutoProcessing: function() {
            if (!confirm(advmoEnhancerMedia.i18n.confirmCancel)) {
                return;
            }
            
            // Make AJAX request to cancel
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'advmo_enhancer_cancel_bulk_offload',
                    nonce: advmoEnhancerMedia.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Stop tracking
                        if (this.state.intervalId) {
                            clearInterval(this.state.intervalId);
                            this.state.intervalId = null;
                        }
                        
                        // Reload page to reset UI
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error cancelling process.');
                    }
                },
                error: () => {
                    alert('Error cancelling process. Please try again.');
                }
            });
        },

        /**
         * Display the progress UI
         */
        displayProgressUI: function() {
            if (this.elements.progressContainer.length === 0) {
                // Create progress container if it doesn't exist
                const progressHTML = wp.template('advmo-enhancer-progress')({
                    autoProcessing: this.state.isAutoProcessing,
                    currentBatch: this.state.currentBatch,
                    totalBatches: this.state.totalBatches,
                    processedFiles: this.state.processedFiles,
                    totalFiles: this.state.totalFiles,
                    percent: 0,
                    timeRemaining: null,
                    errorCount: 0,
                    skippedCount: 0
                });
                
                // Insert the progress UI
                $('.advmo-section').first().before(progressHTML);
                
                // Re-cache elements
                this.cacheElements();
            } else {
                // Show the existing progress container
                this.elements.progressContainer.show();
            }
            
            // Show status container
            this.elements.statusContainer.show();
        },

        /**
         * Update the UI based on current state
         */
        updateUI: function() {
            // Calculate progress percentage
            const percent = this.state.totalFiles > 0 
                ? Math.round((this.state.processedFiles / this.state.totalFiles) * 100) 
                : 0;
            
            // Update progress bar
            if (this.elements.progressBar.length) {
                this.elements.progressBar.css('width', percent + '%');
            }
            
            // Update batch progress text
            if (this.elements.batchProgress.length) {
                this.elements.batchProgress.text(
                    advmoEnhancerMedia.i18n.batchesProcessed
                        .replace('%d', this.state.currentBatch)
                        .replace('%d', this.state.totalBatches)
                );
            }
            
            // Update file progress text
            if (this.elements.fileProgress.length) {
                this.elements.fileProgress.text(
                    advmoEnhancerMedia.i18n.filesProcessed
                        .replace('%d', this.state.processedFiles)
                        .replace('%d', this.state.totalFiles)
                );
            }
            
            // Update time estimate
            if (this.elements.timeEstimate.length && this.state.startTime > 0) {
                const elapsedTime = Math.floor(Date.now() / 1000) - this.state.startTime;
                
                if (elapsedTime > 0 && this.state.processedFiles > 0) {
                    const filesPerSecond = this.state.processedFiles / elapsedTime;
                    const remainingFiles = this.state.totalFiles - this.state.processedFiles;
                    const estimatedSecondsRemaining = Math.round(remainingFiles / filesPerSecond);
                    
                    if (estimatedSecondsRemaining > 0) {
                        let timeText = this.formatTime(estimatedSecondsRemaining);
                        this.elements.timeEstimate.text(
                            advmoEnhancerMedia.i18n.estimatedTimeRemaining.replace('%s', timeText)
                        );
                    }
                }
            }
            
            // Update pause/resume button
            if (this.elements.pauseButton.length) {
                this.elements.pauseButton.text(
                    this.state.isPaused 
                        ? advmoEnhancerMedia.i18n.resumeAutoProcessing 
                        : advmoEnhancerMedia.i18n.pauseAutoProcessing
                );
            }
        },

        /**
         * Show batch complete notification
         */
        showBatchCompleteNotification: function(batchNumber) {
            // Create notification if not exists
            if ($('.advmo-enhancer-batch-notification').length === 0) {
                $('body').append('<div class="advmo-enhancer-batch-notification"></div>');
            }
            
            // Show notification
            $('.advmo-enhancer-batch-notification')
                .text(advmoEnhancerMedia.i18n.startingBatch.replace('%d', batchNumber + 1).replace('%d', this.state.totalBatches))
                .fadeIn()
                .delay(3000)
                .fadeOut();
        },

        /**
         * Show completion message
         */
        showCompletionMessage: function() {
            // Show alert
            alert(advmoEnhancerMedia.i18n.autoProcessingComplete);
            
            // Add completion message to the page
            $('.advmo-section').first().before(`
                <div class="notice notice-success">
                    <p>
                        <strong>${advmoEnhancerMedia.i18n.autoProcessingComplete}</strong> 
                        ${this.state.processedFiles} files processed.
                    </p>
                </div>
            `);
        },

        /**
         * Format time in seconds to human-readable format
         */
        formatTime: function(seconds) {
            if (seconds < 60) {
                return seconds + ' seconds';
            } else if (seconds < 3600) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                return minutes + ' minute' + (minutes !== 1 ? 's' : '') + 
                       (remainingSeconds > 0 ? ' ' + remainingSeconds + ' second' + (remainingSeconds !== 1 ? 's' : '') : '');
            } else {
                const hours = Math.floor(seconds / 3600);
                const remainingMinutes = Math.floor((seconds % 3600) / 60);
                return hours + ' hour' + (hours !== 1 ? 's' : '') + 
                       (remainingMinutes > 0 ? ' ' + remainingMinutes + ' minute' + (remainingMinutes !== 1 ? 's' : '') : '');
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ADVMOMediaOverview.init();
    });

})(jQuery);