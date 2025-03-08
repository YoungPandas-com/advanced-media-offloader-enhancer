<?php
/**
 * Fired during plugin activation and deactivation
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Activator {
    /**
     * Run activation tasks
     * 
     * @return void
     */
    public static function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename('advanced-media-offloader-enhancer/advanced-media-offloader-enhancer.php'));
            wp_die(
                sprintf(
                    __('Advanced Media Offloader Enhancer requires PHP 8.1 or higher. Your current PHP version is %s. Please upgrade PHP or contact your host.', 'advanced-media-offloader-enhancer'),
                    PHP_VERSION
                ),
                __('Plugin Activation Error', 'advanced-media-offloader-enhancer'),
                ['back_link' => true]
            );
        }
        
        // Check if the original plugin is active
        if (!class_exists('ADVMO')) {
            deactivate_plugins(plugin_basename('advanced-media-offloader-enhancer/advanced-media-offloader-enhancer.php'));
            wp_die(
                __('Advanced Media Offloader Enhancer requires the Advanced Media Offloader plugin to be installed and activated.', 'advanced-media-offloader-enhancer'),
                __('Plugin Activation Error', 'advanced-media-offloader-enhancer'),
                ['back_link' => true]
            );
        }
        
        // Initialize default settings if not exists
        if (!get_option('advmo_enhancer_settings')) {
            $default_settings = [
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
            
            update_option('advmo_enhancer_settings', $default_settings);
        }
        
        // Initialize bulk offload data
        if (!get_option('advmo_enhancer_bulk_offload_data')) {
            $bulk_data = [
                'total_batches' => 0,
                'current_batch' => 0,
                'total_files' => 0,
                'processed_files' => 0,
                'error_count' => 0,
                'skipped_count' => 0,
                'auto_processing' => false,
                'start_time' => 0,
                'last_update' => 0,
                'status' => 'idle',
                'current_batch_ids' => [],
                'batch_history' => [],
                'error_ids' => [],
            ];
            
            update_option('advmo_enhancer_bulk_offload_data', $bulk_data);
        }
        
        // Create database version option for future updates
        update_option('advmo_enhancer_db_version', '1.0.0');
        
        // Set activation timestamp
        update_option('advmo_enhancer_activated', time());
        
        // Schedule cleanup events
        if (!wp_next_scheduled('advmo_enhancer_cleanup')) {
            wp_schedule_event(time(), 'daily', 'advmo_enhancer_cleanup');
        }
        
        // Add capabilities to administrator role
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_advmo_enhancer');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Store original plugin version for compatibility checks
        if (class_exists('ADVMO')) {
            global $advmo;
            $version = isset($advmo->version) ? $advmo->version : '';
            update_option('advmo_enhancer_original_plugin_version', $version);
        }
        
        // Create necessary folders
        $upload_dir = wp_upload_dir();
        $logs_dir = $upload_dir['basedir'] . '/advmo-enhancer-logs';
        
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
            
            // Add an index.php file to prevent directory listing
            $index_file = $logs_dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
        }
        
        // Trigger first run action
        do_action('advmo_enhancer_first_activation');
    }
    
    /**
     * Run deactivation tasks
     * 
     * @return void
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('advmo_enhancer_cleanup');
        wp_clear_scheduled_hook('advmo_enhancer_process_next_batch');
        
        // Remove any transients
        delete_transient('advmo_enhancer_doing_batch');
        
        // Update bulk offload data to mark as inactive
        $bulk_data = get_option('advmo_enhancer_bulk_offload_data', []);
        
        if (isset($bulk_data['status']) && $bulk_data['status'] === 'processing') {
            $bulk_data['status'] = 'paused';
            $bulk_data['auto_processing'] = false;
            update_option('advmo_enhancer_bulk_offload_data', $bulk_data);
        }
        
        // Log deactivation
        $deactivation_log = [
            'timestamp' => time(),
            'version' => ADVMO_ENHANCER_VERSION,
            'had_active_process' => isset($bulk_data['status']) && $bulk_data['status'] === 'processing',
        ];
        
        update_option('advmo_enhancer_deactivation_log', $deactivation_log);
        
        // Trigger action for other components to clean up
        do_action('advmo_enhancer_deactivated');
    }
    
    /**
     * Check if this is a fresh install
     * 
     * @return bool
     */
    public static function is_fresh_install() {
        return !get_option('advmo_enhancer_activated');
    }
    
    /**
     * Get the activation timestamp
     * 
     * @return int|false
     */
    public static function get_activation_time() {
        return get_option('advmo_enhancer_activated');
    }
    
    /**
     * Process plugin updates if needed
     * 
     * @return void
     */
    public static function maybe_update() {
        $current_version = get_option('advmo_enhancer_version', '0.0.0');
        
        if (version_compare($current_version, ADVMO_ENHANCER_VERSION, '<')) {
            // Run update procedures
            self::update($current_version);
            
            // Update stored version
            update_option('advmo_enhancer_version', ADVMO_ENHANCER_VERSION);
        }
    }
    
    /**
     * Update plugin from a previous version
     * 
     * @param string $from_version Previous version
     * @return void
     */
    private static function update($from_version) {
        // Example version comparison and update logic
        if (version_compare($from_version, '1.0.0', '<')) {
            // Upgrade from pre-1.0.0 to 1.0.0
        }
        
        // Log the update
        $update_log = get_option('advmo_enhancer_update_log', []);
        $update_log[] = [
            'timestamp' => time(),
            'from' => $from_version,
            'to' => ADVMO_ENHANCER_VERSION,
        ];
        
        update_option('advmo_enhancer_update_log', array_slice($update_log, -10)); // Keep only the last 10 updates
    }
}