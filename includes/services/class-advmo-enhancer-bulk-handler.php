<?php
/**
 * Enhanced Bulk Handler for Advanced Media Offloader
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Bulk_Handler {
    /**
     * Constructor
     */
    public function __construct() {
        // Override file size limit check in original plugin
        add_filter('advmo_max_file_size_mb', [$this, 'modify_max_file_size']);
        
        // Hook into the bulk process to add our enhanced tracking
        add_action('advmo_before_upload_to_cloud', [$this, 'track_file_start'], 10, 1);
        add_action('advmo_after_upload_to_cloud', [$this, 'track_file_complete'], 10, 1);
        
        // Add hooks for persistent auto-processing
        add_action('wp_ajax_advmo_enhancer_resume_autoprocessing', [$this, 'ajax_resume_autoprocessing']);
        add_action('wp_ajax_advmo_enhancer_pause_autoprocessing', [$this, 'ajax_pause_autoprocessing']);
        
        // Detect when batches are completed and handle continuation
        add_action('admin_init', [$this, 'check_auto_processing_status']);
        
        // Add specialized auto-recovery functionality for failed uploads
        add_action('admin_init', [$this, 'auto_recovery_check']);
    }
    
    /**
     * Modify max file size limit from original plugin
     * 
     * @param int $size Original size limit in MB
     * @return int New size limit in MB
     */
    public function modify_max_file_size($size) {
        $enhanced_limit = ADVMO_Enhancer_Utils::get_max_file_size();
        return $enhanced_limit;
    }
    
    /**
     * Track when a file starts uploading
     * 
     * @param int $attachment_id Attachment ID
     */
    public function track_file_start($attachment_id) {
        // Get bulk data
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        
        // Only track if we're in auto-processing mode
        if (!$bulk_data['auto_processing']) {
            return;
        }
        
        // Update current file being processed
        $current_files = isset($bulk_data['current_processing']) ? $bulk_data['current_processing'] : [];
        $current_files[$attachment_id] = [
            'start_time' => time(),
            'status' => 'processing',
        ];
        
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'current_processing' => $current_files,
        ]);
    }
    
    /**
     * Track when a file completes uploading
     * 
     * @param int $attachment_id Attachment ID
     */
    public function track_file_complete($attachment_id) {
        // Get bulk data
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        
        // Only track if we're in auto-processing mode
        if (!$bulk_data['auto_processing']) {
            return;
        }
        
        // Update file status
        $current_files = isset($bulk_data['current_processing']) ? $bulk_data['current_processing'] : [];
        
        if (isset($current_files[$attachment_id])) {
            // Remove this file from current processing
            unset($current_files[$attachment_id]);
            
            // Add to batch history
            $batch_history = isset($bulk_data['batch_history']) ? $bulk_data['batch_history'] : [];
            $batch_history[] = [
                'attachment_id' => $attachment_id,
                'batch' => $bulk_data['current_batch'],
                'status' => 'complete',
                'timestamp' => time(),
            ];
            
            // Check if this attachment has errors
            $has_errors = get_post_meta($attachment_id, 'advmo_error_log', true);
            
            // Update counts
            $processed_files = $bulk_data['processed_files'] + 1;
            $error_count = $bulk_data['error_count'];
            
            if (!empty($has_errors)) {
                $error_count++;
                
                // Add to error IDs
                $error_ids = isset($bulk_data['error_ids']) ? $bulk_data['error_ids'] : [];
                $error_ids[] = $attachment_id;
                
                ADVMO_Enhancer_Utils::update_bulk_offload_data([
                    'error_ids' => $error_ids,
                ]);
                
                // Log the error in our enhanced error log
                $error_messages = is_array($has_errors) ? $has_errors : [$has_errors];
                
                foreach ($error_messages as $message) {
                    ADVMO_Enhancer_Utils::log_error(
                        $attachment_id,
                        'upload_failed',
                        $message,
                        [
                            'batch' => $bulk_data['current_batch'],
                            'file' => get_attached_file($attachment_id),
                        ]
                    );
                }
            }
            
            // Update bulk data
            ADVMO_Enhancer_Utils::update_bulk_offload_data([
                'current_processing' => $current_files,
                'batch_history' => $batch_history,
                'processed_files' => $processed_files,
                'error_count' => $error_count,
            ]);
        }
    }
    
    /**
     * Check auto-processing status on page load
     */
    public function check_auto_processing_status() {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Don't run on AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Get bulk data
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        
        // If auto-processing is not active, don't do anything
        if (!$bulk_data['auto_processing'] || $bulk_data['status'] !== 'processing') {
            return;
        }
        
        // Check if we need to resume a running process
        $current_batch = $bulk_data['current_batch'];
        $total_batches = $bulk_data['total_batches'];
        
        // If current batch is complete, but we have more batches to process, schedule the next batch
        if ($current_batch < $total_batches) {
            // Check if current batch is idle
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
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['success']) && $data['success']) {
                    // Check if the current batch is complete or empty
                    $status = isset($data['data']['status']) ? $data['data']['status'] : '';
                    $processed = isset($data['data']['processed']) ? (int)$data['data']['processed'] : 0;
                    $total = isset($data['data']['total']) ? (int)$data['data']['total'] : 0;
                    
                    $is_complete = ($status === 'completed' || $processed >= $total);
                    
                    if ($is_complete && $current_batch < $total_batches) {
                        // If batch is complete, start the next one
                        $next_batch = $current_batch + 1;
                        
                        ADVMO_Enhancer_Utils::update_bulk_offload_data([
                            'current_batch' => $next_batch,
                            'last_processed_in_batch' => 0,
                            'last_errors_in_batch' => 0,
                            'last_skipped_in_batch' => 0,
                        ]);
                        
                        // Start the next batch via AJAX
                        $this->schedule_next_batch_processing();
                    }
                }
            }
        }
    }
    
    /**
     * Schedule processing of the next batch
     */
    private function schedule_next_batch_processing() {
        // Add an admin notice that auto-processing is continuing
        add_action('admin_notices', function() {
            $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
            ?>
            <div class="notice notice-info">
                <p>
                    <?php 
                    printf(
                        __('Auto-processing media files: Batch %1$d of %2$d | %3$d of %4$d files processed', 'advanced-media-offloader-enhancer'),
                        $bulk_data['current_batch'],
                        $bulk_data['total_batches'],
                        $bulk_data['processed_files'],
                        $bulk_data['total_files']
                    ); 
                    ?>
                    
                    <a href="<?php echo admin_url('admin.php?page=advmo_media_overview'); ?>" class="button button-small">
                        <?php _e('View Progress', 'advanced-media-offloader-enhancer'); ?>
                    </a>
                </p>
            </div>
            <?php
        });
        
        // Schedule an immediate cron event to start the next batch
        if (!wp_next_scheduled('advmo_enhancer_process_next_batch')) {
            wp_schedule_single_event(time(), 'advmo_enhancer_process_next_batch');
        }
        
        // Add the cron action
        add_action('advmo_enhancer_process_next_batch', function() {
            $response = wp_remote_post(
                admin_url('admin-ajax.php'),
                [
                    'timeout' => 30,
                    'redirection' => 5,
                    'blocking' => true,
                    'body' => [
                        'action' => 'advmo_start_bulk_offload',
                        'bulk_offload_nonce' => wp_create_nonce('advmo_bulk_offload'),
                    ],
                    'cookies' => $_COOKIE,
                ]
            );
            
            // Log any errors
            if (is_wp_error($response)) {
                ADVMO_Enhancer_Utils::log_error(
                    0,
                    'next_batch_cron_failed',
                    $response->get_error_message(),
                    []
                );
            }
        });
    }
    
    /**
     * AJAX handler to resume auto-processing
     */
    public function ajax_resume_autoprocessing() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_bulk_offload', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Update auto-processing flag
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'auto_processing' => true,
            'status' => 'processing',
        ]);
        
        // Get the current batch status
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        $current_batch = $bulk_data['current_batch'];
        $total_batches = $bulk_data['total_batches'];
        
        // Check if we need to resume the current batch or start a new one
        if ($current_batch <= $total_batches) {
            // Resume via AJAX to the original plugin
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
                wp_send_json_error([
                    'message' => __('Failed to resume auto-processing.', 'advanced-media-offloader-enhancer'),
                    'error' => $response->get_error_message(),
                ]);
            }
            
            wp_send_json_success([
                'message' => __('Auto-processing resumed.', 'advanced-media-offloader-enhancer'),
                'current_batch' => $current_batch,
                'total_batches' => $total_batches,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No more batches to process.', 'advanced-media-offloader-enhancer'),
            ]);
        }
    }
    
    /**
     * AJAX handler to pause auto-processing
     */
    public function ajax_pause_autoprocessing() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_bulk_offload', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Update auto-processing flag
        ADVMO_Enhancer_Utils::update_bulk_offload_data([
            'auto_processing' => false,
            'status' => 'paused',
        ]);
        
        wp_send_json_success([
            'message' => __('Auto-processing paused. You can resume it later.', 'advanced-media-offloader-enhancer'),
        ]);
    }
    
    /**
     * Check for and recover from failed uploads
     */
    public function auto_recovery_check() {
        // Only run on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Don't run on AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Get settings
        $auto_resume = ADVMO_Enhancer_Utils::get_setting('auto_resume_on_error') === 'yes';
        
        // If auto-resume is disabled, don't do anything
        if (!$auto_resume) {
            return;
        }
        
        // Get bulk data
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        
        // Only run recovery if we're in processing mode
        if ($bulk_data['status'] !== 'processing') {
            return;
        }
        
        // Check for stalled uploads (files that started processing but never completed)
        $current_processing = isset($bulk_data['current_processing']) ? $bulk_data['current_processing'] : [];
        $retry_attempts = (int)ADVMO_Enhancer_Utils::get_setting('retry_attempts', 3);
        
        foreach ($current_processing as $attachment_id => $info) {
            // Skip if start time is recent (less than 5 minutes ago)
            if (time() - $info['start_time'] < 300) {
                continue;
            }
            
            // Check if we've exceeded retry attempts
            $attempts = isset($info['attempts']) ? $info['attempts'] : 0;
            
            if ($attempts >= $retry_attempts) {
                // Log the error and remove from current processing
                ADVMO_Enhancer_Utils::log_error(
                    $attachment_id,
                    'max_retries_exceeded',
                    sprintf(__('Failed to process attachment after %d attempts.', 'advanced-media-offloader-enhancer'), $attempts),
                    ['start_time' => $info['start_time']]
                );
                
                unset($current_processing[$attachment_id]);
                
                // Update error counts
                $error_ids = isset($bulk_data['error_ids']) ? $bulk_data['error_ids'] : [];
                $error_ids[] = $attachment_id;
                
                ADVMO_Enhancer_Utils::update_bulk_offload_data([
                    'current_processing' => $current_processing,
                    'error_count' => $bulk_data['error_count'] + 1,
                    'error_ids' => $error_ids,
                ]);
                
                continue;
            }
            
            // Try to retry the upload
            try {
                // Get cloud provider from the global ADVMO instance
                global $advmo;
                
                if (!isset($advmo->offloader) || !$advmo->offloader) {
                    continue; // Can't retry without the offloader
                }
                
                $cloud_provider = $advmo->container->get('cloud_provider');
                
                if (!$cloud_provider) {
                    continue; // Can't retry without the cloud provider
                }
                
                // Create uploader instance
                $uploader = new \Advanced_Media_Offloader\Services\CloudAttachmentUploader($cloud_provider);
                
                // Update retry count
                $current_processing[$attachment_id]['attempts'] = $attempts + 1;
                $current_processing[$attachment_id]['status'] = 'retrying';
                $current_processing[$attachment_id]['retry_time'] = time();
                
                ADVMO_Enhancer_Utils::update_bulk_offload_data([
                    'current_processing' => $current_processing,
                ]);
                
                // Attempt to upload
                $uploader->uploadAttachment($attachment_id);
                
                // Note: We don't need to handle success here, as it will be tracked by the track_file_complete method
            } catch (\Exception $e) {
                // Log error and continue
                ADVMO_Enhancer_Utils::log_error(
                    $attachment_id,
                    'retry_failed',
                    $e->getMessage(),
                    [
                        'attempt' => $attempts + 1,
                        'max_attempts' => $retry_attempts,
                    ]
                );
            }
        }
    }
}