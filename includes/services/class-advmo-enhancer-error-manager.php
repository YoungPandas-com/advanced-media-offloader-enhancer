<?php
/**
 * Error Manager for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Error_Manager {
    /**
     * Maximum number of errors to keep in the log
     * 
     * @var int
     */
    private $max_log_entries = 1000;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add hooks to capture errors from the original plugin
        add_action('admin_init', [$this, 'capture_existing_errors']);
        
        // Cleanup old errors periodically
        add_action('admin_init', [$this, 'cleanup_old_errors']);
        
        // Add diagnostic tools
        add_action('wp_ajax_advmo_enhancer_run_diagnostics', [$this, 'ajax_run_diagnostics']);
        
        // Add filter to show enhanced error messages
        add_filter('advmo_error_message_display', [$this, 'enhance_error_messages'], 10, 2);
    }
    
    /**
     * Capture existing errors from the original plugin
     */
    public function capture_existing_errors() {
        // Only run once per day
        $last_capture = get_option('advmo_enhancer_last_error_capture', 0);
        
        if (time() - $last_capture < DAY_IN_SECONDS) {
            return;
        }
        
        // Get all attachments with errors
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
            'advmo_error_log'
        );
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            update_option('advmo_enhancer_last_error_capture', time());
            return;
        }
        
        foreach ($results as $result) {
            $attachment_id = $result->post_id;
            $errors = maybe_unserialize($result->meta_value);
            
            if (empty($errors)) {
                continue;
            }
            
            // Log each error
            $error_messages = is_array($errors) ? $errors : [$errors];
            
            foreach ($error_messages as $message) {
                ADVMO_Enhancer_Utils::log_error(
                    $attachment_id,
                    'imported_error',
                    $message,
                    [
                        'imported' => true,
                        'timestamp' => time(),
                    ]
                );
            }
        }
        
        update_option('advmo_enhancer_last_error_capture', time());
    }
    
    /**
     * Cleanup old errors
     */
    public function cleanup_old_errors() {
        // Get current error log
        $errors = get_option('advmo_enhancer_error_log', []);
        
        if (count($errors) <= $this->max_log_entries) {
            return;
        }
        
        // Sort by timestamp (newest first)
        usort($errors, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Keep only the latest entries
        $errors = array_slice($errors, 0, $this->max_log_entries);
        
        // Update log
        update_option('advmo_enhancer_error_log', $errors);
    }
    
    /**
     * Enhance error messages
     * 
     * @param string $message Original error message
     * @param int $attachment_id Attachment ID
     * @return string Enhanced error message
     */
    public function enhance_error_messages($message, $attachment_id) {
        // Add troubleshooting information based on the error message
        if (strpos($message, 'Failed to upload') !== false) {
            $message .= ' ' . __('This could be due to insufficient permissions, a timeout, or an issue with your cloud storage provider.', 'advanced-media-offloader-enhancer');
            $message .= ' ' . sprintf(__('You can try increasing the max file size in the <a href="%s">enhancer settings</a>.', 'advanced-media-offloader-enhancer'), admin_url('admin.php?page=advmo-enhancer-settings'));
        } elseif (strpos($message, 'does not exist') !== false) {
            $message .= ' ' . __('The file may have been moved or deleted from the server.', 'advanced-media-offloader-enhancer');
            $message .= ' ' . __('Try regenerating the attachment metadata using a plugin like "Regenerate Thumbnails".', 'advanced-media-offloader-enhancer');
        } elseif (strpos($message, 'credentials') !== false) {
            $message .= ' ' . __('Make sure your cloud storage provider credentials are correct and have the necessary permissions.', 'advanced-media-offloader-enhancer');
        }
        
        // Add troubleshooting link
        $message .= ' ' . sprintf(__('<a href="%s">View detailed error logs</a>', 'advanced-media-offloader-enhancer'), admin_url('admin.php?page=advmo-enhancer-errors'));
        
        return $message;
    }
    
    /**
     * Run diagnostics on the system
     * 
     * @return array Diagnostic results
     */
    private function run_diagnostics() {
        $results = [
            'status' => 'ok',
            'tests' => [],
        ];
        
        // Check PHP version
        $php_version = phpversion();
        $php_min_version = '8.0.0';
        $php_status = version_compare($php_version, $php_min_version, '>=') ? 'pass' : 'fail';
        
        $results['tests']['php_version'] = [
            'name' => __('PHP Version', 'advanced-media-offloader-enhancer'),
            'status' => $php_status,
            'value' => $php_version,
            'recommendation' => $php_status === 'fail' ? sprintf(__('PHP %s or higher is recommended.', 'advanced-media-offloader-enhancer'), $php_min_version) : '',
        ];
        
        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        $min_execution_time = 30;
        $execution_time_status = ($max_execution_time >= $min_execution_time || $max_execution_time == 0) ? 'pass' : 'warn';
        
        $results['tests']['max_execution_time'] = [
            'name' => __('Max Execution Time', 'advanced-media-offloader-enhancer'),
            'status' => $execution_time_status,
            'value' => $max_execution_time,
            'recommendation' => $execution_time_status === 'warn' ? sprintf(__('At least %d seconds is recommended for handling large files.', 'advanced-media-offloader-enhancer'), $min_execution_time) : '',
        ];
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
        $min_memory = 256 * MB_IN_BYTES;
        $memory_status = ($memory_limit_bytes >= $min_memory || $memory_limit_bytes == -1) ? 'pass' : 'warn';
        
        $results['tests']['memory_limit'] = [
            'name' => __('Memory Limit', 'advanced-media-offloader-enhancer'),
            'status' => $memory_status,
            'value' => $memory_limit,
            'recommendation' => $memory_status === 'warn' ? sprintf(__('At least %s is recommended for handling large files.', 'advanced-media-offloader-enhancer'), size_format($min_memory)) : '',
        ];
        
        // Check if curl is enabled
        $curl_enabled = function_exists('curl_version');
        $curl_status = $curl_enabled ? 'pass' : 'fail';
        
        $results['tests']['curl'] = [
            'name' => __('cURL Extension', 'advanced-media-offloader-enhancer'),
            'status' => $curl_status,
            'value' => $curl_enabled ? __('Enabled', 'advanced-media-offloader-enhancer') : __('Disabled', 'advanced-media-offloader-enhancer'),
            'recommendation' => $curl_status === 'fail' ? __('cURL is required for communication with cloud storage providers.', 'advanced-media-offloader-enhancer') : '',
        ];
        
        // Check uploads directory permissions
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $is_writable = is_writable($base_dir);
        $writable_status = $is_writable ? 'pass' : 'fail';
        
        $results['tests']['upload_dir'] = [
            'name' => __('Uploads Directory', 'advanced-media-offloader-enhancer'),
            'status' => $writable_status,
            'value' => $is_writable ? __('Writable', 'advanced-media-offloader-enhancer') : __('Not writable', 'advanced-media-offloader-enhancer'),
            'recommendation' => $writable_status === 'fail' ? __('The uploads directory must be writable to process media files.', 'advanced-media-offloader-enhancer') : '',
        ];
        
        // Check if the original plugin is active and configured
        $original_plugin_active = class_exists('ADVMO');
        $original_plugin_status = $original_plugin_active ? 'pass' : 'fail';
        
        $results['tests']['original_plugin'] = [
            'name' => __('Advanced Media Offloader', 'advanced-media-offloader-enhancer'),
            'status' => $original_plugin_status,
            'value' => $original_plugin_active ? __('Active', 'advanced-media-offloader-enhancer') : __('Inactive', 'advanced-media-offloader-enhancer'),
            'recommendation' => $original_plugin_status === 'fail' ? __('The original Advanced Media Offloader plugin must be active.', 'advanced-media-offloader-enhancer') : '',
        ];
        
        // Check cloud provider configuration
        $cloud_provider_key = function_exists('advmo_get_cloud_provider_key') ? advmo_get_cloud_provider_key() : '';
        $cloud_provider_status = !empty($cloud_provider_key) ? 'pass' : 'fail';
        
        $results['tests']['cloud_provider'] = [
            'name' => __('Cloud Provider', 'advanced-media-offloader-enhancer'),
            'status' => $cloud_provider_status,
            'value' => !empty($cloud_provider_key) ? $cloud_provider_key : __('Not configured', 'advanced-media-offloader-enhancer'),
            'recommendation' => $cloud_provider_status === 'fail' ? __('A cloud provider must be configured in the Advanced Media Offloader settings.', 'advanced-media-offloader-enhancer') : '',
        ];
        
        // Set overall status based on test results
        foreach ($results['tests'] as $test) {
            if ($test['status'] === 'fail') {
                $results['status'] = 'fail';
                break;
            } elseif ($test['status'] === 'warn' && $results['status'] !== 'fail') {
                $results['status'] = 'warn';
            }
        }
        
        return $results;
    }
    
    /**
     * AJAX handler for running diagnostics
     */
    public function ajax_run_diagnostics() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_diagnostics', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Run diagnostics
        $results = $this->run_diagnostics();
        
        // Generate HTML output
        $html = '<div class="advmo-enhancer-diagnostics">';
        
        $status_classes = [
            'pass' => 'advmo-enhancer-test-pass',
            'warn' => 'advmo-enhancer-test-warn',
            'fail' => 'advmo-enhancer-test-fail',
        ];
        
        $status_icons = [
            'pass' => '✓',
            'warn' => '⚠',
            'fail' => '✗',
        ];
        
        $html .= '<div class="advmo-enhancer-diagnostics-summary advmo-enhancer-test-' . $results['status'] . '">';
        
        if ($results['status'] === 'ok') {
            $html .= '<h3>' . __('All systems operational!', 'advanced-media-offloader-enhancer') . '</h3>';
            $html .= '<p>' . __('Your system meets all requirements for optimal performance.', 'advanced-media-offloader-enhancer') . '</p>';
        } elseif ($results['status'] === 'warn') {
            $html .= '<h3>' . __('System check completed with warnings', 'advanced-media-offloader-enhancer') . '</h3>';
            $html .= '<p>' . __('Your system may experience performance issues. Review the recommendations below.', 'advanced-media-offloader-enhancer') . '</p>';
        } else {
            $html .= '<h3>' . __('System check failed', 'advanced-media-offloader-enhancer') . '</h3>';
            $html .= '<p>' . __('Your system does not meet all requirements. Some features may not work correctly.', 'advanced-media-offloader-enhancer') . '</p>';
        }
        
        $html .= '</div>';
        
        $html .= '<table class="advmo-enhancer-diagnostics-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Test', 'advanced-media-offloader-enhancer') . '</th>';
        $html .= '<th>' . __('Status', 'advanced-media-offloader-enhancer') . '</th>';
        $html .= '<th>' . __('Value', 'advanced-media-offloader-enhancer') . '</th>';
        $html .= '<th>' . __('Recommendation', 'advanced-media-offloader-enhancer') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        foreach ($results['tests'] as $test) {
            $html .= '<tr class="' . $status_classes[$test['status']] . '">';
            $html .= '<td>' . esc_html($test['name']) . '</td>';
            $html .= '<td><span class="advmo-enhancer-test-icon">' . $status_icons[$test['status']] . '</span></td>';
            $html .= '<td>' . esc_html($test['value']) . '</td>';
            $html .= '<td>' . esc_html($test['recommendation']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        // Add some CSS
        $html .= '<style>
            .advmo-enhancer-diagnostics {
                margin: 20px 0;
            }
            
            .advmo-enhancer-diagnostics-summary {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            
            .advmo-enhancer-test-pass {
                background-color: #ecf7ed;
                border-left: 4px solid #46b450;
            }
            
            .advmo-enhancer-test-warn {
                background-color: #fff8e5;
                border-left: 4px solid #ffb900;
            }
            
            .advmo-enhancer-test-fail {
                background-color: #fbeaea;
                border-left: 4px solid #dc3232;
            }
            
            .advmo-enhancer-diagnostics-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .advmo-enhancer-diagnostics-table th,
            .advmo-enhancer-diagnostics-table td {
                padding: 10px;
                border: 1px solid #e5e5e5;
            }
            
            .advmo-enhancer-diagnostics-table th {
                background-color: #f9f9f9;
                font-weight: bold;
                text-align: left;
            }
            
            .advmo-enhancer-test-icon {
                font-weight: bold;
                font-size: 16px;
            }
            
            .advmo-enhancer-test-pass .advmo-enhancer-test-icon {
                color: #46b450;
            }
            
            .advmo-enhancer-test-warn .advmo-enhancer-test-icon {
                color: #ffb900;
            }
            
            .advmo-enhancer-test-fail .advmo-enhancer-test-icon {
                color: #dc3232;
            }
        </style>';
        
        wp_send_json_success([
            'html' => $html,
            'results' => $results,
        ]);
    }
}