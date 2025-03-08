<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This script performs cleanup when the plugin is removed:
 * - Removes plugin options
 * - Clears scheduled hooks
 * - Removes custom capabilities
 *
 * NOTE: It's intentionally NOT including the main plugin file
 * to avoid accidentally calling hooks that shouldn't run during uninstall.
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants for use during uninstall
define('ADVMO_ENHANCER_COMPLETE_REMOVAL', true);

// Get the settings to check if we should remove all data
$settings = get_option('advmo_enhancer_settings');
$remove_all_data = isset($settings['remove_all_data_on_uninstall']) && $settings['remove_all_data_on_uninstall'] === 'yes';

// Function to completely clean up all plugin data
function advmo_enhancer_complete_uninstall() {
    global $wpdb;
    
    // Delete all plugin options
    $options = [
        'advmo_enhancer_settings',
        'advmo_enhancer_bulk_offload_data',
        'advmo_enhancer_error_log',
        'advmo_enhancer_db_version',
        'advmo_enhancer_activated',
        'advmo_enhancer_version',
        'advmo_enhancer_original_plugin_version',
        'advmo_enhancer_update_log',
        'advmo_enhancer_deactivation_log',
        'advmo_enhancer_last_error_capture',
    ];
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Clear any scheduled hooks
    wp_clear_scheduled_hook('advmo_enhancer_cleanup');
    wp_clear_scheduled_hook('advmo_enhancer_process_next_batch');
    
    // Remove transients
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%advmo_enhancer_%' AND option_name LIKE '_transient_%'");
    
    // Remove any user meta
    $wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '%advmo_enhancer_%'");
    
    // Remove custom capabilities from roles
    $roles = wp_roles();
    if (!empty($roles)) {
        foreach ($roles->role_objects as $role) {
            $role->remove_cap('manage_advmo_enhancer');
        }
    }
    
    // Delete log directory if it exists
    $upload_dir = wp_upload_dir();
    $logs_dir = $upload_dir['basedir'] . '/advmo-enhancer-logs';
    
    if (file_exists($logs_dir) && is_dir($logs_dir)) {
        advmo_enhancer_recursive_rmdir($logs_dir);
    }
}

// Helper function to recursively delete a directory
function advmo_enhancer_recursive_rmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        
        foreach ($objects as $object) {
            if ($object !== '.' && $object !== '..') {
                if (is_dir($dir . '/' . $object)) {
                    advmo_enhancer_recursive_rmdir($dir . '/' . $object);
                } else {
                    unlink($dir . '/' . $object);
                }
            }
        }
        
        rmdir($dir);
    }
}

// Check if we should do a minimal or complete uninstall
if ($remove_all_data || defined('ADVMO_ENHANCER_COMPLETE_REMOVAL')) {
    advmo_enhancer_complete_uninstall();
} else {
    // Minimal uninstall - just remove settings and bulk data
    delete_option('advmo_enhancer_settings');
    delete_option('advmo_enhancer_bulk_offload_data');
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('advmo_enhancer_cleanup');
    wp_clear_scheduled_hook('advmo_enhancer_process_next_batch');
}