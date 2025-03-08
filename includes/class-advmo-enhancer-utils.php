<?php
/**
 * Utility functions for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Utils {
    /**
     * Get plugin settings with defaults
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = [
            'auto_process_batches' => 'yes',
            'max_file_size' => 50,
            'enable_cloudflare' => 'no',
            'cloudflare_api_token' => '',
            'cloudflare_zone_id' => '',
            'cloudflare_auto_purge' => 'yes',
            'detailed_error_logging' => 'yes',
            'delete_local_after_offload' => 'no',
            'auto_resume_on_error' => 'yes',
            'retry_attempts' => 3,
        ];

        $settings = get_option('advmo_enhancer_settings', []);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Get a specific setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get_setting($key, $default = null) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update a specific setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public static function update_setting($key, $value) {
        $settings = self::get_settings();
        $settings[$key] = $value;
        return update_option('advmo_enhancer_settings', $settings);
    }

    /**
     * Get the enhanced bulk offload data
     *
     * @return array
     */
    public static function get_bulk_offload_data() {
        $defaults = [
            'total_batches' => 0,
            'current_batch' => 0,
            'total_files' => 0,
            'processed_files' => 0,
            'error_count' => 0,
            'skipped_count' => 0,
            'auto_processing' => false,
            'start_time' => 0,
            'last_update' => 0,
            'status' => 'idle', // idle, processing, paused, complete, error
            'current_batch_ids' => [],
            'batch_history' => [],
            'error_ids' => [],
        ];

        $data = get_option('advmo_enhancer_bulk_offload_data', []);
        return wp_parse_args($data, $defaults);
    }

    /**
     * Update the enhanced bulk offload data
     *
     * @param array $data New data to merge
     * @return bool
     */
    public static function update_bulk_offload_data($data) {
        $current_data = self::get_bulk_offload_data();
        $updated_data = wp_parse_args($data, $current_data);
        $updated_data['last_update'] = time();
        
        return update_option('advmo_enhancer_bulk_offload_data', $updated_data);
    }

    /**
     * Clear the enhanced bulk offload data
     *
     * @return bool
     */
    public static function clear_bulk_offload_data() {
        return delete_option('advmo_enhancer_bulk_offload_data');
    }

    /**
     * Log an error
     *
     * @param int $attachment_id Attachment ID
     * @param string $error_code Error code
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public static function log_error($attachment_id, $error_code, $message, $context = []) {
        if (self::get_setting('detailed_error_logging') !== 'yes') {
            return;
        }

        $errors = get_option('advmo_enhancer_error_log', []);
        $errors[] = [
            'id' => uniqid('err_'),
            'timestamp' => time(),
            'attachment_id' => $attachment_id,
            'code' => $error_code,
            'message' => $message,
            'context' => $context,
        ];

        // Limit the number of errors to 1000
        if (count($errors) > 1000) {
            $errors = array_slice($errors, -1000);
        }

        update_option('advmo_enhancer_error_log', $errors);

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[ADVMO Enhancer] Error %s for attachment %d: %s | Context: %s',
                $error_code,
                $attachment_id,
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Get logged errors
     *
     * @param array $filters Optional filters
     * @return array
     */
    public static function get_errors($filters = []) {
        $errors = get_option('advmo_enhancer_error_log', []);
        
        // Apply filters if provided
        if (!empty($filters)) {
            foreach ($errors as $key => $error) {
                foreach ($filters as $filter_key => $filter_value) {
                    if (isset($error[$filter_key]) && $error[$filter_key] != $filter_value) {
                        unset($errors[$key]);
                        break;
                    }
                }
            }
            $errors = array_values($errors);
        }
        
        return $errors;
    }

    /**
     * Check if auto-processing is enabled
     *
     * @return bool
     */
    public static function is_auto_processing_enabled() {
        return self::get_setting('auto_process_batches') === 'yes';
    }

    /**
     * Get the max file size in MB
     *
     * @return int
     */
    public static function get_max_file_size() {
        $max_size = intval(self::get_setting('max_file_size', 50));
        return max(10, min($max_size, 200)); // Between 10MB and 200MB
    }

    /**
     * Format file size to human readable format
     *
     * @param int $size Size in bytes
     * @return string
     */
    public static function format_file_size($size) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = max(0, $size);
        $pow = floor(($size ? log($size) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $size /= pow(1024, $pow);
        
        return round($size, 2) . ' ' . $units[$pow];
    }

    /**
     * Format time to human readable format
     *
     * @param int $seconds Time in seconds
     * @return string
     */
    public static function format_time($seconds) {
        if ($seconds < 60) {
            return sprintf(_n('%d second', '%d seconds', $seconds, 'advanced-media-offloader-enhancer'), $seconds);
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;
            return sprintf(
                _n('%d minute', '%d minutes', $minutes, 'advanced-media-offloader-enhancer') . ($seconds ? ' ' . _n('%d second', '%d seconds', $seconds, 'advanced-media-offloader-enhancer') : ''),
                $minutes, $seconds
            );
        } else {
            $hours = floor($seconds / 3600);
            $seconds = $seconds % 3600;
            $minutes = floor($seconds / 60);
            
            return sprintf(
                _n('%d hour', '%d hours', $hours, 'advanced-media-offloader-enhancer') . ($minutes ? ' ' . _n('%d minute', '%d minutes', $minutes, 'advanced-media-offloader-enhancer') : ''),
                $hours, $minutes
            );
        }
    }

    /**
     * Calculate estimated time remaining
     *
     * @param int $processed Number of processed items
     * @param int $total Total number of items
     * @param int $start_time Start time timestamp
     * @return string|null
     */
    public static function estimate_time_remaining($processed, $total, $start_time) {
        if ($processed <= 0 || $total <= 0 || $start_time <= 0) {
            return null;
        }
        
        $elapsed = time() - $start_time;
        if ($elapsed <= 0) {
            return null;
        }
        
        $rate = $processed / $elapsed;
        if ($rate <= 0) {
            return null;
        }
        
        $remaining_items = $total - $processed;
        $remaining_time = ceil($remaining_items / $rate);
        
        return self::format_time($remaining_time);
    }
}