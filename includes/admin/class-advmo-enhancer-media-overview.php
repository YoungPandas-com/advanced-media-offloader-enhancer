<?php
/**
 * Enhanced Media Overview for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Media_Overview {
    /**
     * Constructor
     */
    public function __construct() {
        // Add hooks to modify the original media overview page
        add_action('advmo_before_media_overview_content', [$this, 'add_enhanced_controls']);
        add_action('advmo_after_bulk_offload_button', [$this, 'add_enhanced_bulk_controls']);
        add_action('admin_footer', [$this, 'add_templates']);
        
        // Register AJAX handlers
        add_action('wp_ajax_advmo_enhancer_start_bulk_offload', [$this, 'ajax_start_bulk_offload']);
        add_action('wp_ajax_advmo_enhancer_check_progress', [$this, 'ajax_check_progress']);
        add_action('wp_ajax_advmo_enhancer_cancel_bulk_offload', [$this, 'ajax_cancel_bulk_offload']);
        
        // Only hook into these actions if our plugin is active
        if (is_admin() && advmo_is_settings_page('media-overview')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_overview_scripts']);
        }
    }
    
    /**
     * Enqueue scripts specific to the media overview page
     */
    public function enqueue_overview_scripts() {
        wp_enqueue_script(
            'advmo-enhancer-media-overview',
            ADVMO_ENHANCER_URL . 'assets/js/media-overview.js',
            ['jquery', 'advmo-enhancer-admin'],
            ADVMO_ENHANCER_VERSION,
            true
        );
        
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        
        wp_localize_script('advmo-enhancer-media-overview', 'advmoEnhancerMedia', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('advmo_enhancer_bulk_offload'),
            'settings' => [
                'autoProcess' => $settings['auto_process_batches'] === 'yes',
                'maxFileSize' => (int)$settings['max_file_size'],
                'autoResume' => $settings['auto_resume_on_error'] === 'yes',
            ],
            'bulkData' => [
                'status' => $bulk_data['status'],
                'autoProcessing' => $bulk_data['auto_processing'],
                'currentBatch' => $bulk_data['current_batch'],
                'totalBatches' => $bulk_data['total_batches'],
                'processedFiles' => $bulk_data['processed_files'],
                'totalFiles' => $bulk_data['total_files'],
                'errorCount' => $bulk_data['error_count'],
                'skippedCount' => $bulk_data['skipped_count'],
                'startTime' => $bulk_data['start_time'],
            ],
            'i18n' => [
                'autoProcessing' => __('Auto-processing is active', 'advanced-media-offloader-enhancer'),
                'startingBatch' => __('Starting batch %d of %d...', 'advanced-media-offloader-enhancer'),
                'pauseAutoProcessing' => __('Pause Auto-Processing', 'advanced-media-offloader-enhancer'),
                'resumeAutoProcessing' => __('Resume Auto-Processing', 'advanced-media-offloader-enhancer'),
                'estimatedTimeRemaining' => __('Estimated time remaining: %s', 'advanced-media-offloader-enhancer'),
                'autoProcessingComplete' => __('Auto-processing complete!', 'advanced-media-offloader-enhancer'),
                'filesProcessed' => __('%d of %d files processed', 'advanced-media-offloader-enhancer'),
                'batchesProcessed' => __('%d of %d batches processed', 'advanced-media-offloader-enhancer'),
            ]
        ]);
    }
    
    /**
     * Add enhanced controls to the top of the media overview page
     */
    public function add_enhanced_controls() {
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        $max_file_size = (int)$settings['max_file_size'];
        
        // Only show enhanced controls if auto-processing is enabled in settings
        if ($settings['auto_process_batches'] !== 'yes') {
            return;
        }
        
        // Display a banner if auto-processing is active
        if ($bulk_data['auto_processing'] && $bulk_data['status'] === 'processing') {
            $percent = $bulk_data['total_files'] > 0 
                ? round(($bulk_data['processed_files'] / $bulk_data['total_files']) * 100) 
                : 0;
            
            $time_remaining = '';
            if ($bulk_data['start_time'] > 0) {
                $estimate = ADVMO_Enhancer_Utils::estimate_time_remaining(
                    $bulk_data['processed_files'],
                    $bulk_data['total_files'],
                    $bulk_data['start_time']
                );
                
                if ($estimate) {
                    $time_remaining = sprintf(
                        __('Estimated time remaining: %s', 'advanced-media-offloader-enhancer'),
                        $estimate
                    );
                }
            }
            
            ?>
            <div class="notice notice-info advmo-enhancer-auto-processing-notice">
                <p>
                    <strong><?php _e('Auto-processing media files', 'advanced-media-offloader-enhancer'); ?></strong>
                    <span class="advmo-enhancer-progress-text">
                        <?php 
                        printf(
                            __('Batch %1$d of %2$d | %3$d of %4$d files processed | %5$d%% complete', 'advanced-media-offloader-enhancer'),
                            $bulk_data['current_batch'],
                            $bulk_data['total_batches'],
                            $bulk_data['processed_files'],
                            $bulk_data['total_files'],
                            $percent
                        ); 
                        ?>
                    </span>
                </p>
                
                <?php if ($time_remaining): ?>
                <p class="advmo-enhancer-time-remaining"><?php echo esc_html($time_remaining); ?></p>
                <?php endif; ?>
                
                <div class="advmo-enhancer-progress-bar-container">
                    <div class="advmo-enhancer-progress-bar" style="width: <?php echo esc_attr($percent); ?>%;"></div>
                </div>
                
                <p>
                    <button type="button" class="button advmo-enhancer-pause-auto-processing">
                        <?php _e('Pause Auto-Processing', 'advanced-media-offloader-enhancer'); ?>
                    </button>
                    
                    <button type="button" class="button button-primary advmo-enhancer-cancel-auto-processing">
                        <?php _e('Cancel Auto-Processing', 'advanced-media-offloader-enhancer'); ?>
                    </button>
                </p>
            </div>
            <?php
        }
        
        // Display a notice about the increased file size limit
        if ($max_file_size > 10) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php 
                    printf(
                        __('Enhanced file size limit: Files up to %d MB can now be offloaded (original limit: 10 MB).', 'advanced-media-offloader-enhancer'),
                        $max_file_size
                    ); 
                    ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add enhanced bulk controls after the standard bulk offload button
     */
    public function add_enhanced_bulk_controls() {
        $settings = ADVMO_Enhancer_Utils::get_settings();
        
        // Only show enhanced controls if auto-processing is enabled in settings
        if ($settings['auto_process_batches'] !== 'yes') {
            return;
        }
        
        ?>
        <div class="advmo-enhancer-controls" style="margin-top: 15px;">
            <label for="advmo-enhancer-auto-process" style="margin-right: 10px;">
                <input type="checkbox" id="advmo-enhancer-auto-process" checked="checked">
                <?php _e('Enable Auto-Processing', 'advanced-media-offloader-enhancer'); ?>
            </label>
            
            <p class="description">
                <?php _e('When enabled, all batches will be processed automatically without needing to click after each batch.', 'advanced-media-offloader-enhancer'); ?>
            </p>
            
            <div id="advmo-enhancer-auto-process-status" style="margin-top: 10px; display: none;">
                <div class="advmo-enhancer-auto-process-progress">
                    <span class="advmo-enhancer-batch-progress"></span>
                    <span class="advmo-enhancer-file-progress"></span>
                </div>
                
                <div class="advmo-enhancer-time-estimate"></div>
                
                <div class="advmo-enhancer-controls-buttons" style="margin-top: 10px;">
                    <button type="button" class="button advmo-enhancer-pause-resume-button">
                        <?php _e('Pause Auto-Processing', 'advanced-media-offloader-enhancer'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary advmo-enhancer-cancel-button">
                        <?php _e('Cancel Auto-Processing', 'advanced-media-offloader-enhancer'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add JavaScript templates to the admin footer
     */
    public function add_templates() {
        // Only add templates on the media overview page
        if (!is_admin() || !advmo_is_settings_page('media-overview')) {
            return;
        }
        
        ?>
        <script type="text/html" id="tmpl-advmo-enhancer-progress">
            <div class="advmo-enhancer-progress-wrap">
                <div class="advmo-enhancer-progress-status">
                    <# if (data.autoProcessing) { #>
                        <span class="advmo-enhancer-auto-processing-indicator">
                            <?php _e('Auto-processing is active', 'advanced-media-offloader-enhancer'); ?>
                        </span>
                    <# } #>
                    
                    <span class="advmo-enhancer-batch-count">
                        <?php _e('Batch {{data.currentBatch}} of {{data.totalBatches}}', 'advanced-media-offloader-enhancer'); ?>
                    </span>
                    
                    <span class="advmo-enhancer-file-count">
                        <?php _e('{{data.processedFiles}} of {{data.totalFiles}} files processed', 'advanced-media-offloader-enhancer'); ?>
                    </span>
                </div>
                
                <# if (data.timeRemaining) { #>
                    <div class="advmo-enhancer-time-remaining">
                        <?php _e('Estimated time remaining: {{data.timeRemaining}}', 'advanced-media-offloader-enhancer'); ?>
                    </div>
                <# } #>
                
                <div class="advmo-enhancer-progress-bar-container">
                    <div class="advmo-enhancer-progress-bar" style="width: {{data.percent}}%;"></div>
                </div>
                
                <# if (data.errorCount > 0) { #>
                    <div class="advmo-enhancer-error-count">
                        <?php _e('{{data.errorCount}} errors encountered.', 'advanced-media-offloader-enhancer'); ?>
                        <a href="<?php echo admin_url('admin.php?page=advmo-enhancer-errors'); ?>">
                            <?php _e('View errors', 'advanced-media-offloader-enhancer'); ?>
                        </a>
                    </div>
                <# } #>
                
                <# if (data.skippedCount > 0) { #>
                    <div class="advmo-enhancer-skipped-count">
                        <?php _e('{{data.skippedCount}} files skipped.', 'advanced-media-offloader-enhancer'); ?>
                    </div>
                <# } #>
            </div>
        </script>
        <?php
    }
    
    /**
     * AJAX handler to start bulk offload with enhanced options
     */
    public function ajax_start_bulk_offload() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_bulk_offload', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Get auto-process setting
        $auto_process = isset($_POST['auto_process']) && $_POST['auto_process'] === 'true';
        
        // Update bulk offload data
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'status' => 'preparing',
            'auto_processing' => $auto_process,
            'start_time' => time(),
            'current_batch' => 0,
            'processed_files' => 0,
            'error_count' => 0,
            'skipped_count' => 0,
        ]);
        
        // Get batch info (we may need to estimate the total number of batches)
        global $wpdb;
        
        // Count total unoffloaded attachments
        $count_query = "
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'advmo_offloaded'
            WHERE p.post_type = 'attachment' 
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ";
        
        $total_files = (int)$wpdb->get_var($count_query);
        $batch_size = 50; // Standard batch size in the original plugin
        $total_batches = ceil($total_files / $batch_size);
        
        // Update with total info
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'total_files' => $total_files,
            'total_batches' => $total_batches,
            'status' => 'ready',
        ]);
        
        // Now trigger the original plugin's bulk offload function
        // We'll do this through an internal AJAX request to the original plugin
        $response = wp_remote_post(
            admin_url('admin-ajax.php'),
            [
                'timeout' => 5,
                'redirection' => 5,
                'blocking' => true,
                'body' => [
                    'action' => 'advmo_start_bulk_offload',
                    'bulk_offload_nonce' => wp_create_nonce('advmo_bulk_offload'),
                ],
                'cookies' => $_COOKIE,
            ]
        );
        
        if (is_wp_error($response)) {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'bulk_start_failed',
                $response->get_error_message(),
                ['total_files' => $total_files]
            );
            
            wp_send_json_error([
                'message' => __('Failed to start bulk offload process.', 'advanced-media-offloader-enhancer'),
                'error' => $response->get_error_message(),
            ]);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            // Update status to processing
            ADVMO_Enhancer_Utils::update_bulk_offload_data([
                'status' => 'processing',
                'current_batch' => 1,
            ]);
            
            wp_send_json_success([
                'message' => __('Bulk offload process started successfully.', 'advanced-media-offloader-enhancer'),
                'auto_processing' => $auto_process,
                'current_batch' => 1,
                'total_batches' => $total_batches,
                'total_files' => $total_files,
            ]);
        } else {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'bulk_start_error',
                isset($data['message']) ? $data['message'] : __('Unknown error', 'advanced-media-offloader-enhancer'),
                ['response' => $data]
            );
            
            wp_send_json_error([
                'message' => __('Error starting bulk offload process.', 'advanced-media-offloader-enhancer'),
                'original_response' => $data,
            ]);
        }
    }
    
    /**
     * AJAX handler to check progress and potentially start the next batch
     */
    public function ajax_check_progress() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_bulk_offload', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Get current bulk offload data
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        
        // Check if auto-processing is enabled
        if (!$bulk_data['auto_processing']) {
            wp_send_json_success([
                'status' => $bulk_data['status'],
                'auto_processing' => false,
                'current_batch' => $bulk_data['current_batch'],
                'total_batches' => $bulk_data['total_batches'],
                'processed_files' => $bulk_data['processed_files'],
                'total_files' => $bulk_data['total_files'],
                'error_count' => $bulk_data['error_count'],
                'skipped_count' => $bulk_data['skipped_count'],
            ]);
        }
        
        // If not in processing state, return current status
        if ($bulk_data['status'] !== 'processing') {
            wp_send_json_success([
                'status' => $bulk_data['status'],
                'auto_processing' => $bulk_data['auto_processing'],
                'current_batch' => $bulk_data['current_batch'],
                'total_batches' => $bulk_data['total_batches'],
                'processed_files' => $bulk_data['processed_files'],
                'total_files' => $bulk_data['total_files'],
                'error_count' => $bulk_data['error_count'],
                'skipped_count' => $bulk_data['skipped_count'],
            ]);
        }
        
        // First check the current batch's progress
        $response = wp_remote_post(
            admin_url('admin-ajax.php'),
            [
                'timeout' => 5,
                'redirection' => 5,
                'blocking' => true,
                'body' => [
                    'action' => 'advmo_check_bulk_offload_progress',
                    'bulk_offload_nonce' => wp_create_nonce('advmo_bulk_offload'),
                ],
                'cookies' => $_COOKIE,
            ]
        );
        
        if (is_wp_error($response)) {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'progress_check_failed',
                $response->get_error_message(),
                ['batch' => $bulk_data['current_batch']]
            );
            
            wp_send_json_error([
                'message' => __('Failed to check progress.', 'advanced-media-offloader-enhancer'),
                'error' => $response->get_error_message(),
            ]);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['success']) || !$data['success']) {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'progress_check_error',
                isset($data['message']) ? $data['message'] : __('Unknown error', 'advanced-media-offloader-enhancer'),
                ['response' => $data]
            );
            
            wp_send_json_error([
                'message' => __('Error checking progress.', 'advanced-media-offloader-enhancer'),
                'original_response' => $data,
            ]);
        }
        
        // Update our progress data based on the response
        $processed = isset($data['data']['processed']) ? (int)$data['data']['processed'] : 0;
        $total = isset($data['data']['total']) ? (int)$data['data']['total'] : 0;
        $status = isset($data['data']['status']) ? $data['data']['status'] : 'processing';
        $errors = isset($data['data']['errors']) ? (int)$data['data']['errors'] : 0;
        $skipped = isset($data['data']['oversized_skipped']) ? (int)$data['data']['oversized_skipped'] : 0;
        
        // Update tracking
        $current_batch = $bulk_data['current_batch'];
        $total_processed = $bulk_data['processed_files'] + ($processed - $bulk_data['last_processed_in_batch']);
        $total_errors = $bulk_data['error_count'] + ($errors - $bulk_data['last_errors_in_batch']);
        $total_skipped = $bulk_data['skipped_count'] + ($skipped - $bulk_data['last_skipped_in_batch']);
        
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'processed_files' => $total_processed,
            'last_processed_in_batch' => $processed,
            'error_count' => $total_errors,
            'last_errors_in_batch' => $errors,
            'skipped_count' => $total_skipped,
            'last_skipped_in_batch' => $skipped,
            'last_update' => time(),
        ]);
        
        // Check if batch is completed
        $batch_complete = ($status === 'completed' || $processed >= $total);
        $time_remaining = ADVMO_Enhancer_Utils::estimate_time_remaining(
            $total_processed,
            $bulk_data['total_files'],
            $bulk_data['start_time']
        );
        
        // If batch is complete and there are more batches to process
        if ($batch_complete && $current_batch < $bulk_data['total_batches']) {
            // Update for next batch
            $next_batch = $current_batch + 1;
            
            ADVMO_Enhancer_Utils::update_bulk_offload_data([
                'current_batch' => $next_batch,
                'last_processed_in_batch' => 0,
                'last_errors_in_batch' => 0,
                'last_skipped_in_batch' => 0,
            ]);
            
            // Start the next batch
            $start_response = wp_remote_post(
                admin_url('admin-ajax.php'),
                [
                    'timeout' => 5,
                    'redirection' => 5,
                    'blocking' => true,
                    'body' => [
                        'action' => 'advmo_start_bulk_offload',
                        'bulk_offload_nonce' => wp_create_nonce('advmo_bulk_offload'),
                    ],
                    'cookies' => $_COOKIE,
                ]
            );
            
            if (is_wp_error($start_response)) {
                ADVMO_Enhancer_Utils::log_error(
                    0,
                    'next_batch_start_failed',
                    $start_response->get_error_message(),
                    ['batch' => $next_batch]
                );
                
                wp_send_json_error([
                    'message' => sprintf(__('Failed to start batch %d.', 'advanced-media-offloader-enhancer'), $next_batch),
                    'error' => $start_response->get_error_message(),
                ]);
            }
            
            $start_body = wp_remote_retrieve_body($start_response);
            $start_data = json_decode($start_body, true);
            
            if (isset($start_data['success']) && $start_data['success']) {
                wp_send_json_success([
                    'status' => 'processing',
                    'auto_processing' => true,
                    'current_batch' => $next_batch,
                    'total_batches' => $bulk_data['total_batches'],
                    'processed_files' => $total_processed,
                    'total_files' => $bulk_data['total_files'],
                    'time_remaining' => $time_remaining,
                    'error_count' => $total_errors,
                    'skipped_count' => $total_skipped,
                    'batch_complete' => true,
                    'all_complete' => false,
                ]);
            } else {
                ADVMO_Enhancer_Utils::log_error(
                    0,
                    'next_batch_start_error',
                    isset($start_data['message']) ? $start_data['message'] : __('Unknown error', 'advanced-media-offloader-enhancer'),
                    ['response' => $start_data]
                );
                
                wp_send_json_error([
                    'message' => sprintf(__('Error starting batch %d.', 'advanced-media-offloader-enhancer'), $next_batch),
                    'original_response' => $start_data,
                ]);
            }
        } 
        // If all batches are complete
        else if ($batch_complete && $current_batch >= $bulk_data['total_batches']) {
            // Update status to completed
            ADVMO_Enhancer_Utils::update_bulk_offload_data([
                'status' => 'completed',
                'auto_processing' => false,
            ]);
            
            wp_send_json_success([
                'status' => 'completed',
                'auto_processing' => false,
                'current_batch' => $current_batch,
                'total_batches' => $bulk_data['total_batches'],
                'processed_files' => $total_processed,
                'total_files' => $bulk_data['total_files'],
                'error_count' => $total_errors,
                'skipped_count' => $total_skipped,
                'batch_complete' => true,
                'all_complete' => true,
            ]);
        }
        // Still processing current batch
        else {
            wp_send_json_success([
                'status' => 'processing',
                'auto_processing' => true,
                'current_batch' => $current_batch,
                'total_batches' => $bulk_data['total_batches'],
                'processed_files' => $total_processed,
                'total_files' => $bulk_data['total_files'],
                'time_remaining' => $time_remaining,
                'batch_percent' => $total > 0 ? round(($processed / $total) * 100) : 0,
                'total_percent' => $bulk_data['total_files'] > 0 ? round(($total_processed / $bulk_data['total_files']) * 100) : 0,
                'error_count' => $total_errors,
                'skipped_count' => $total_skipped,
                'batch_complete' => false,
                'all_complete' => false,
            ]);
        }
    }
    
    /**
     * AJAX handler to cancel bulk offload
     */
    public function ajax_cancel_bulk_offload() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_bulk_offload', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // First cancel the current batch in the original plugin
        $response = wp_remote_post(
            admin_url('admin-ajax.php'),
            [
                'timeout' => 5,
                'redirection' => 5,
                'blocking' => true,
                'body' => [
                    'action' => 'advmo_cancel_bulk_offload',
                    'bulk_offload_nonce' => wp_create_nonce('advmo_bulk_offload'),
                ],
                'cookies' => $_COOKIE,
            ]
        );
        
        // Update our status even if the original plugin call fails
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'status' => 'cancelled',
            'auto_processing' => false,
        ]);
        
        if (is_wp_error($response)) {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'cancel_failed',
                $response->get_error_message(),
                []
            );
            
            // Even if the cancel request failed, we mark it as cancelled in our tracking
            wp_send_json_success([
                'message' => __('Bulk offload process cancelled successfully (with warnings).', 'advanced-media-offloader-enhancer'),
                'warning' => $response->get_error_message(),
            ]);
        }
        
        wp_send_json_success([
            'message' => __('Bulk offload process cancelled successfully.', 'advanced-media-offloader-enhancer'),
        ]);
    }
}