<?php
/**
 * Dashboard Widget for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Dashboard_Widget {
    /**
     * Constructor
     */
    public function __construct() {
        // Register the dashboard widget
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
        
        // Add AJAX handler for widget actions
        add_action('wp_ajax_advmo_enhancer_widget_action', [$this, 'handle_widget_action']);
    }
    
    /**
     * Register the dashboard widget
     */
    public function register_dashboard_widget() {
        // Only add for users who can manage options
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'advmo_enhancer_dashboard_widget',
                __('Media Offload Status', 'advanced-media-offloader-enhancer'),
                [$this, 'render_dashboard_widget'],
                [$this, 'configure_dashboard_widget']
            );
        }
    }
    
    /**
     * Render the dashboard widget
     */
    public function render_dashboard_widget() {
        // Get stats
        $stats = $this->get_offload_stats();
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
        $is_processing = $bulk_data['status'] === 'processing' && $bulk_data['auto_processing'];
        
        // Calculate total and percentage
        $total_attachments = $stats['offloaded'] + $stats['not_offloaded'];
        $offload_percentage = $total_attachments > 0 ? round(($stats['offloaded'] / $total_attachments) * 100) : 0;
        
        ?>
        <div class="advmo-enhancer-widget">
            <div class="advmo-enhancer-widget-stats">
                <div class="advmo-enhancer-widget-stat">
                    <span class="advmo-enhancer-widget-value"><?php echo esc_html(number_format_i18n($stats['offloaded'])); ?></span>
                    <span class="advmo-enhancer-widget-label"><?php _e('Files Offloaded', 'advanced-media-offloader-enhancer'); ?></span>
                </div>
                
                <div class="advmo-enhancer-widget-stat">
                    <span class="advmo-enhancer-widget-value"><?php echo esc_html(number_format_i18n($stats['not_offloaded'])); ?></span>
                    <span class="advmo-enhancer-widget-label"><?php _e('Files Pending', 'advanced-media-offloader-enhancer'); ?></span>
                </div>
                
                <div class="advmo-enhancer-widget-stat">
                    <span class="advmo-enhancer-widget-value"><?php echo esc_html($offload_percentage . '%'); ?></span>
                    <span class="advmo-enhancer-widget-label"><?php _e('Complete', 'advanced-media-offloader-enhancer'); ?></span>
                </div>
            </div>
            
            <?php if ($total_attachments > 0): ?>
                <div class="advmo-enhancer-widget-progress">
                    <div class="advmo-enhancer-widget-progress-bar" style="width: <?php echo esc_attr($offload_percentage); ?>%;"></div>
                </div>
            <?php endif; ?>
            
            <?php if ($stats['errors'] > 0): ?>
                <div class="advmo-enhancer-widget-errors">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <?php 
                        printf(
                            _n(
                                '%s file has errors and needs attention',
                                '%s files have errors and need attention',
                                $stats['errors'],
                                'advanced-media-offloader-enhancer'
                            ),
                            number_format_i18n($stats['errors'])
                        ); 
                        ?>
                    </p>
                    <a href="<?php echo admin_url('admin.php?page=advmo-enhancer-errors'); ?>" class="button button-small">
                        <?php _e('View Errors', 'advanced-media-offloader-enhancer'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($is_processing): ?>
                <div class="advmo-enhancer-widget-active-process">
                    <p>
                        <span class="dashicons dashicons-update advmo-enhancer-spin"></span>
                        <strong><?php _e('Auto-processing active', 'advanced-media-offloader-enhancer'); ?></strong>
                    </p>
                    <p>
                        <?php 
                        printf(
                            __('Batch %1$d of %2$d | %3$d of %4$d files processed', 'advanced-media-offloader-enhancer'),
                            $bulk_data['current_batch'],
                            $bulk_data['total_batches'],
                            $bulk_data['processed_files'],
                            $bulk_data['total_files']
                        ); 
                        ?>
                    </p>
                    <a href="<?php echo admin_url('admin.php?page=advmo_media_overview'); ?>" class="button button-small">
                        <?php _e('View Progress', 'advanced-media-offloader-enhancer'); ?>
                    </a>
                </div>
            <?php elseif ($stats['not_offloaded'] > 0): ?>
                <p class="advmo-enhancer-widget-action">
                    <a href="<?php echo admin_url('admin.php?page=advmo_media_overview'); ?>" class="button">
                        <?php _e('Offload Media Files', 'advanced-media-offloader-enhancer'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p class="advmo-enhancer-widget-complete">
                    <?php _e('All media files have been offloaded to cloud storage!', 'advanced-media-offloader-enhancer'); ?>
                </p>
            <?php endif; ?>
            
            <div class="advmo-enhancer-widget-links">
                <a href="<?php echo admin_url('admin.php?page=advmo'); ?>">
                    <?php _e('Settings', 'advanced-media-offloader-enhancer'); ?>
                </a> | 
                <a href="<?php echo admin_url('admin.php?page=advmo-enhancer-settings'); ?>">
                    <?php _e('Enhancer Settings', 'advanced-media-offloader-enhancer'); ?>
                </a>
                
                <?php if (ADVMO_Enhancer_Utils::get_setting('enable_cloudflare') === 'yes'): ?>
                 | <a href="<?php echo admin_url('admin.php?page=advmo-cloudflare'); ?>">
                        <?php _e('Cloudflare Actions', 'advanced-media-offloader-enhancer'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .advmo-enhancer-widget {
            margin: -12px;
            overflow: hidden;
        }
        
        .advmo-enhancer-widget-stats {
            display: flex;
            justify-content: space-between;
            text-align: center;
            margin-bottom: 15px;
            padding: 15px 12px 0;
        }
        
        .advmo-enhancer-widget-stat {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .advmo-enhancer-widget-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            line-height: 1.2;
        }
        
        .advmo-enhancer-widget-label {
            font-size: 12px;
            color: #555;
        }
        
        .advmo-enhancer-widget-progress {
            height: 8px;
            background-color: #f0f0f0;
            margin: 0 0 15px;
            overflow: hidden;
        }
        
        .advmo-enhancer-widget-progress-bar {
            height: 100%;
            background-color: #0073aa;
            width: 0;
            transition: width 0.5s ease;
        }
        
        .advmo-enhancer-widget-errors {
            background-color: #fff8e5;
            padding: 10px 12px;
            margin: 0 0 15px;
            border-left: 4px solid #ffb900;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .advmo-enhancer-widget-errors p {
            margin: 0;
        }
        
        .advmo-enhancer-widget-errors .dashicons {
            color: #ffb900;
            margin-right: 5px;
        }
        
        .advmo-enhancer-widget-active-process {
            background-color: #f0f6fc;
            padding: 10px 12px;
            margin: 0 0 15px;
            border-left: 4px solid #0073aa;
        }
        
        .advmo-enhancer-widget-active-process p {
            margin: 0 0 5px;
        }
        
        .advmo-enhancer-spin {
            animation: advmo-enhancer-spin 2s infinite linear;
        }
        
        @keyframes advmo-enhancer-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .advmo-enhancer-widget-action {
            padding: 5px 12px 0;
            margin: 0 0 10px;
        }
        
        .advmo-enhancer-widget-complete {
            padding: 5px 12px;
            margin: 0 0 10px;
            color: #46b450;
            font-weight: bold;
        }
        
        .advmo-enhancer-widget-links {
            border-top: 1px solid #eee;
            padding: 10px 12px;
            color: #ccc;
        }
        </style>
        <?php
    }
    
    /**
     * Configure dashboard widget
     */
    public function configure_dashboard_widget() {
        // Get current settings
        $widget_options = get_option('advmo_enhancer_widget_options', [
            'show_errors' => 'yes',
            'show_active_process' => 'yes',
            'refresh_interval' => 60,
        ]);
        
        // Save settings if form is submitted
        if (isset($_POST['advmo_enhancer_widget_options'])) {
            $widget_options = [
                'show_errors' => isset($_POST['show_errors']) ? 'yes' : 'no',
                'show_active_process' => isset($_POST['show_active_process']) ? 'yes' : 'no',
                'refresh_interval' => absint($_POST['refresh_interval']),
            ];
            
            update_option('advmo_enhancer_widget_options', $widget_options);
        }
        
        ?>
        <p>
            <label for="advmo-enhancer-show-errors">
                <input type="checkbox" id="advmo-enhancer-show-errors" name="show_errors" 
                       <?php checked($widget_options['show_errors'], 'yes'); ?>>
                <?php _e('Show error notifications', 'advanced-media-offloader-enhancer'); ?>
            </label>
        </p>
        
        <p>
            <label for="advmo-enhancer-show-active-process">
                <input type="checkbox" id="advmo-enhancer-show-active-process" name="show_active_process" 
                       <?php checked($widget_options['show_active_process'], 'yes'); ?>>
                <?php _e('Show active processes', 'advanced-media-offloader-enhancer'); ?>
            </label>
        </p>
        
        <p>
            <label for="advmo-enhancer-refresh-interval">
                <?php _e('Auto-refresh interval (seconds):', 'advanced-media-offloader-enhancer'); ?>
                <input type="number" id="advmo-enhancer-refresh-interval" name="refresh_interval" 
                       value="<?php echo esc_attr($widget_options['refresh_interval']); ?>" 
                       min="0" max="600" step="10">
                <span class="description">
                    <?php _e('Set to 0 to disable auto-refresh', 'advanced-media-offloader-enhancer'); ?>
                </span>
            </label>
        </p>
        
        <input type="hidden" name="advmo_enhancer_widget_options" value="1">
        <?php
    }
    
    /**
     * Get offload statistics
     * 
     * @return array
     */
    private function get_offload_stats() {
        $offloaded = function_exists('advmo_get_offloaded_media_items_count') 
            ? advmo_get_offloaded_media_items_count() 
            : 0;
        
        $not_offloaded = function_exists('advmo_get_unoffloaded_media_items_count') 
            ? advmo_get_unoffloaded_media_items_count() 
            : 0;
        
        // Get error count
        $errors = get_option('advmo_enhancer_error_log', []);
        $error_count = is_array($errors) ? count($errors) : 0;
        
        return [
            'offloaded' => $offloaded,
            'not_offloaded' => $not_offloaded,
            'errors' => $error_count,
        ];
    }
    
    /**
     * Handle widget AJAX actions
     */
    public function handle_widget_action() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_widget', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Get the action
        $action = isset($_POST['widget_action']) ? sanitize_text_field($_POST['widget_action']) : '';
        
        switch ($action) {
            case 'refresh_stats':
                $stats = $this->get_offload_stats();
                wp_send_json_success(['stats' => $stats]);
                break;
                
            case 'get_process_status':
                $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();
                wp_send_json_success(['bulk_data' => $bulk_data]);
                break;
                
            default:
                wp_send_json_error(['message' => __('Invalid action.', 'advanced-media-offloader-enhancer')]);
                break;
        }
    }
}