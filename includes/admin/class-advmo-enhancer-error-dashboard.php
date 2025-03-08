<?php
/**
 * Error Dashboard for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Error_Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_error_dashboard']);
        add_action('admin_init', [$this, 'handle_error_actions']);
        add_action('wp_ajax_advmo_enhancer_get_error_details', [$this, 'ajax_get_error_details']);
        add_action('wp_ajax_advmo_enhancer_clear_errors', [$this, 'ajax_clear_errors']);
        add_action('wp_ajax_advmo_enhancer_retry_offload', [$this, 'ajax_retry_offload']);
        add_action('wp_ajax_advmo_enhancer_export_errors', [$this, 'ajax_export_errors']);
    }

    /**
     * Add error dashboard page
     */
    public function add_error_dashboard() {
        add_submenu_page(
            'advmo',
            __('Error Log', 'advanced-media-offloader-enhancer'),
            __('Error Log', 'advanced-media-offloader-enhancer'),
            'manage_options',
            'advmo-enhancer-errors',
            [$this, 'render_error_dashboard']
        );
    }

    /**
     * Handle error actions
     */
    public function handle_error_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'advmo-enhancer-errors') {
            return;
        }

        // Handle export action
        if (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'advmo_enhancer_export_errors')) {
                $this->export_errors();
            }
        }

        // Handle clear action
        if (isset($_GET['action']) && $_GET['action'] === 'clear' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'advmo_enhancer_clear_errors')) {
                delete_option('advmo_enhancer_error_log');
                wp_redirect(add_query_arg(['page' => 'advmo-enhancer-errors', 'cleared' => '1'], admin_url('admin.php')));
                exit;
            }
        }
    }

    /**
     * Render error dashboard
     */
    public function render_error_dashboard() {
        $errors = get_option('advmo_enhancer_error_log', []);
        $error_count = count($errors);
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        $total_pages = ceil($error_count / $per_page);
        $displayed_errors = array_slice($errors, $offset, $per_page);

        // Sort errors by timestamp (newest first)
        usort($displayed_errors, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        // Get unique error codes for filtering
        $error_codes = [];
        foreach ($errors as $error) {
            if (!in_array($error['code'], $error_codes)) {
                $error_codes[] = $error['code'];
            }
        }
        sort($error_codes);

        // Handle cleared notice
        if (isset($_GET['cleared']) && $_GET['cleared'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                esc_html__('Error log cleared successfully.', 'advanced-media-offloader-enhancer') . 
                '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Advanced Media Offloader Error Log', 'advanced-media-offloader-enhancer'); ?></h1>
            
            <div class="advmo-enhancer-error-controls">
                <div class="advmo-enhancer-error-count">
                    <?php printf(
                        _n('%s error logged', '%s errors logged', $error_count, 'advanced-media-offloader-enhancer'),
                        '<strong>' . number_format_i18n($error_count) . '</strong>'
                    ); ?>
                </div>
                
                <div class="advmo-enhancer-error-actions">
                    <?php if ($error_count > 0): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(
                            add_query_arg(['page' => 'advmo-enhancer-errors', 'action' => 'export'], admin_url('admin.php')),
                            'advmo_enhancer_export_errors'
                        )); ?>" class="button"><?php echo esc_html__('Export to CSV', 'advanced-media-offloader-enhancer'); ?></a>
                        
                        <a href="<?php echo esc_url(wp_nonce_url(
                            add_query_arg(['page' => 'advmo-enhancer-errors', 'action' => 'clear'], admin_url('admin.php')),
                            'advmo_enhancer_clear_errors'
                        )); ?>" class="button" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all error logs?', 'advanced-media-offloader-enhancer')); ?>');"><?php echo esc_html__('Clear Error Log', 'advanced-media-offloader-enhancer'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($errors)): ?>
                <div class="advmo-enhancer-no-errors">
                    <p><?php echo esc_html__('No errors have been logged yet.', 'advanced-media-offloader-enhancer'); ?></p>
                </div>
            <?php else: ?>
                <div class="advmo-enhancer-error-filters">
                    <select id="advmo-enhancer-error-code-filter">
                        <option value=""><?php echo esc_html__('All Error Types', 'advanced-media-offloader-enhancer'); ?></option>
                        <?php foreach ($error_codes as $code): ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($code); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="text" id="advmo-enhancer-error-search" placeholder="<?php echo esc_attr__('Search errors...', 'advanced-media-offloader-enhancer'); ?>">
                </div>
                
                <table class="wp-list-table widefat fixed striped advmo-enhancer-error-table">
                    <thead>
                        <tr>
                            <th class="advmo-enhancer-error-time"><?php echo esc_html__('Time', 'advanced-media-offloader-enhancer'); ?></th>
                            <th class="advmo-enhancer-error-code"><?php echo esc_html__('Error Code', 'advanced-media-offloader-enhancer'); ?></th>
                            <th class="advmo-enhancer-error-message"><?php echo esc_html__('Message', 'advanced-media-offloader-enhancer'); ?></th>
                            <th class="advmo-enhancer-error-attachment"><?php echo esc_html__('Attachment', 'advanced-media-offloader-enhancer'); ?></th>
                            <th class="advmo-enhancer-error-actions"><?php echo esc_html__('Actions', 'advanced-media-offloader-enhancer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayed_errors as $error): ?>
                            <tr class="advmo-enhancer-error-row" data-error-id="<?php echo esc_attr($error['id']); ?>" data-error-code="<?php echo esc_attr($error['code']); ?>">
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $error['timestamp'])); ?></td>
                                <td><?php echo esc_html($error['code']); ?></td>
                                <td><?php echo esc_html($error['message']); ?></td>
                                <td>
                                    <?php if ($error['attachment_id'] > 0): 
                                        $attachment = get_post($error['attachment_id']);
                                        if ($attachment): ?>
                                            <a href="<?php echo esc_url(get_edit_post_link($error['attachment_id'])); ?>">
                                                <?php echo esc_html($attachment->post_title); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo esc_html(sprintf(__('ID: %d (deleted)', 'advanced-media-offloader-enhancer'), $error['attachment_id'])); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo esc_html__('N/A', 'advanced-media-offloader-enhancer'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small advmo-enhancer-view-details" data-error-id="<?php echo esc_attr($error['id']); ?>"><?php echo esc_html__('Details', 'advanced-media-offloader-enhancer'); ?></button>
                                    
                                    <?php if ($error['attachment_id'] > 0): ?>
                                        <button type="button" class="button button-small advmo-enhancer-retry-offload" data-attachment-id="<?php echo esc_attr($error['attachment_id']); ?>"><?php echo esc_html__('Retry Offload', 'advanced-media-offloader-enhancer'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php 
                // Pagination
                if ($total_pages > 1): 
                    $pagination_args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ];
                    
                    echo '<div class="advmo-enhancer-pagination">';
                    echo paginate_links($pagination_args);
                    echo '</div>';
                endif;
                ?>
                
                <!-- Error details modal -->
                <div id="advmo-enhancer-error-modal" class="advmo-enhancer-modal" style="display: none;">
                    <div class="advmo-enhancer-modal-content">
                        <span class="advmo-enhancer-modal-close">&times;</span>
                        <h2><?php echo esc_html__('Error Details', 'advanced-media-offloader-enhancer'); ?></h2>
                        <div id="advmo-enhancer-error-details-content"></div>
                    </div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // View error details
                    $('.advmo-enhancer-view-details').on('click', function() {
                        var errorId = $(this).data('error-id');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'advmo_enhancer_get_error_details',
                                nonce: '<?php echo wp_create_nonce('advmo_enhancer_error_details'); ?>',
                                error_id: errorId
                            },
                            beforeSend: function() {
                                $('#advmo-enhancer-error-details-content').html('<p><?php echo esc_js(__('Loading...', 'advanced-media-offloader-enhancer')); ?></p>');
                                $('#advmo-enhancer-error-modal').show();
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#advmo-enhancer-error-details-content').html(response.data.html);
                                } else {
                                    $('#advmo-enhancer-error-details-content').html('<p class="error">' + response.data.message + '</p>');
                                }
                            },
                            error: function() {
                                $('#advmo-enhancer-error-details-content').html('<p class="error"><?php echo esc_js(__('Error loading details.', 'advanced-media-offloader-enhancer')); ?></p>');
                            }
                        });
                    });
                    
                    // Close modal
                    $('.advmo-enhancer-modal-close').on('click', function() {
                        $('#advmo-enhancer-error-modal').hide();
                    });
                    
                    // Close modal when clicking outside
                    $(window).on('click', function(event) {
                        if ($(event.target).is('#advmo-enhancer-error-modal')) {
                            $('#advmo-enhancer-error-modal').hide();
                        }
                    });
                    
                    // Retry offload
                    $('.advmo-enhancer-retry-offload').on('click', function() {
                        var attachmentId = $(this).data('attachment-id');
                        var $button = $(this);
                        
                        if (confirm('<?php echo esc_js(__('Are you sure you want to retry offloading this file?', 'advanced-media-offloader-enhancer')); ?>')) {
                            $button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'advanced-media-offloader-enhancer')); ?>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'advmo_enhancer_retry_offload',
                                    nonce: '<?php echo wp_create_nonce('advmo_enhancer_retry_offload'); ?>',
                                    attachment_id: attachmentId
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert(response.data.message);
                                        location.reload();
                                    } else {
                                        alert(response.data.message);
                                        $button.prop('disabled', false).text('<?php echo esc_js(__('Retry Offload', 'advanced-media-offloader-enhancer')); ?>');
                                    }
                                },
                                error: function() {
                                    alert('<?php echo esc_js(__('Error retrying offload.', 'advanced-media-offloader-enhancer')); ?>');
                                    $button.prop('disabled', false).text('<?php echo esc_js(__('Retry Offload', 'advanced-media-offloader-enhancer')); ?>');
                                }
                            });
                        }
                    });
                    
                    // Filter by error code
                    $('#advmo-enhancer-error-code-filter').on('change', function() {
                        var code = $(this).val();
                        
                        if (code) {
                            $('.advmo-enhancer-error-row').hide();
                            $('.advmo-enhancer-error-row[data-error-code="' + code + '"]').show();
                        } else {
                            $('.advmo-enhancer-error-row').show();
                        }
                    });
                    
                    // Search errors
                    $('#advmo-enhancer-error-search').on('keyup', function() {
                        var search = $(this).val().toLowerCase();
                        
                        $('.advmo-enhancer-error-row').each(function() {
                            var $row = $(this);
                            var text = $row.text().toLowerCase();
                            
                            if (text.indexOf(search) > -1) {
                                $row.show();
                            } else {
                                $row.hide();
                            }
                        });
                    });
                });
                </script>
                
                <style>
                /* Modal styles */
                .advmo-enhancer-modal {
                    display: none;
                    position: fixed;
                    z-index: 100000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0,0,0,0.4);
                }
                
                .advmo-enhancer-modal-content {
                    background-color: #fefefe;
                    margin: 10% auto;
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%;
                    max-width: 800px;
                    position: relative;
                }
                
                .advmo-enhancer-modal-close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                
                .advmo-enhancer-modal-close:hover,
                .advmo-enhancer-modal-close:focus {
                    color: black;
                    text-decoration: none;
                }
                
                /* Table styles */
                .advmo-enhancer-error-table {
                    margin-top: 15px;
                }
                
                .advmo-enhancer-error-time {
                    width: 15%;
                }
                
                .advmo-enhancer-error-code {
                    width: 15%;
                }
                
                .advmo-enhancer-error-message {
                    width: 35%;
                }
                
                .advmo-enhancer-error-attachment {
                    width: 15%;
                }
                
                .advmo-enhancer-error-actions {
                    width: 20%;
                }
                
                /* Filter styles */
                .advmo-enhancer-error-filters {
                    margin: 15px 0;
                    display: flex;
                    gap: 10px;
                }
                
                /* Pagination styles */
                .advmo-enhancer-pagination {
                    margin: 20px 0;
                    text-align: center;
                }
                
                /* Control styles */
                .advmo-enhancer-error-controls {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin: 15px 0;
                }
                
                .advmo-enhancer-error-actions {
                    display: flex;
                    gap: 10px;
                }
                </style>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Export errors to CSV
     */
    private function export_errors() {
        $errors = get_option('advmo_enhancer_error_log', []);
        
        if (empty($errors)) {
            wp_redirect(add_query_arg(['page' => 'advmo-enhancer-errors', 'error' => 'no-errors'], admin_url('admin.php')));
            exit;
        }
        
        // Sort errors by timestamp (newest first)
        usort($errors, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=advmo-error-log-' . date('Y-m-d') . '.csv');
        
        // Create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        
        // Output the column headings
        fputcsv($output, [
            __('Time', 'advanced-media-offloader-enhancer'),
            __('Error Code', 'advanced-media-offloader-enhancer'),
            __('Message', 'advanced-media-offloader-enhancer'),
            __('Attachment ID', 'advanced-media-offloader-enhancer'),
            __('Attachment Name', 'advanced-media-offloader-enhancer'),
            __('Context', 'advanced-media-offloader-enhancer'),
        ]);
        
        // Output each error as a row
        foreach ($errors as $error) {
            $attachment_name = '';
            if ($error['attachment_id'] > 0) {
                $attachment = get_post($error['attachment_id']);
                if ($attachment) {
                    $attachment_name = $attachment->post_title;
                } else {
                    $attachment_name = sprintf(__('ID: %d (deleted)', 'advanced-media-offloader-enhancer'), $error['attachment_id']);
                }
            }
            
            fputcsv($output, [
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $error['timestamp']),
                $error['code'],
                $error['message'],
                $error['attachment_id'],
                $attachment_name,
                is_array($error['context']) ? json_encode($error['context']) : $error['context'],
            ]);
        }
        
        exit;
    }
    
    /**
     * AJAX handler to get error details
     */
    public function ajax_get_error_details() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_error_details', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        $error_id = isset($_POST['error_id']) ? sanitize_text_field($_POST['error_id']) : '';
        
        if (empty($error_id)) {
            wp_send_json_error(['message' => __('Invalid error ID.', 'advanced-media-offloader-enhancer')]);
        }
        
        $errors = get_option('advmo_enhancer_error_log', []);
        $error = null;
        
        foreach ($errors as $err) {
            if ($err['id'] === $error_id) {
                $error = $err;
                break;
            }
        }
        
        if (!$error) {
            wp_send_json_error(['message' => __('Error not found.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Generate HTML for error details
        $html = '<div class="advmo-enhancer-error-details">';
        
        $html .= '<div class="advmo-enhancer-error-detail-row">';
        $html .= '<div class="advmo-enhancer-error-detail-label">' . __('Time:', 'advanced-media-offloader-enhancer') . '</div>';
        $html .= '<div class="advmo-enhancer-error-detail-value">' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $error['timestamp']) . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="advmo-enhancer-error-detail-row">';
        $html .= '<div class="advmo-enhancer-error-detail-label">' . __('Error Code:', 'advanced-media-offloader-enhancer') . '</div>';
        $html .= '<div class="advmo-enhancer-error-detail-value">' . esc_html($error['code']) . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="advmo-enhancer-error-detail-row">';
        $html .= '<div class="advmo-enhancer-error-detail-label">' . __('Message:', 'advanced-media-offloader-enhancer') . '</div>';
        $html .= '<div class="advmo-enhancer-error-detail-value">' . esc_html($error['message']) . '</div>';
        $html .= '</div>';
        
        if ($error['attachment_id'] > 0) {
            $attachment = get_post($error['attachment_id']);
            $attachment_name = $attachment ? $attachment->post_title : sprintf(__('ID: %d (deleted)', 'advanced-media-offloader-enhancer'), $error['attachment_id']);
            
            $html .= '<div class="advmo-enhancer-error-detail-row">';
            $html .= '<div class="advmo-enhancer-error-detail-label">' . __('Attachment:', 'advanced-media-offloader-enhancer') . '</div>';
            $html .= '<div class="advmo-enhancer-error-detail-value">';
            
            if ($attachment) {
                $html .= '<a href="' . get_edit_post_link($error['attachment_id']) . '" target="_blank">';
                $html .= esc_html($attachment_name);
                $html .= '</a>';
            } else {
                $html .= esc_html($attachment_name);
            }
            
            $html .= '</div>';
            $html .= '</div>';
            
            // Add attachment details if available
            if ($attachment) {
                $file_path = get_attached_file($error['attachment_id']);
                $file_size = file_exists($file_path) ? size_format(filesize($file_path)) : __('Unknown', 'advanced-media-offloader-enhancer');
                $file_type = get_post_mime_type($error['attachment_id']);
                
                $html .= '<div class="advmo-enhancer-error-detail-row">';
                $html .= '<div class="advmo-enhancer-error-detail-label">' . __('File Path:', 'advanced-media-offloader-enhancer') . '</div>';
                $html .= '<div class="advmo-enhancer-error-detail-value">' . esc_html($file_path) . '</div>';
                $html .= '</div>';
                
                $html .= '<div class="advmo-enhancer-error-detail-row">';
                $html .= '<div class="advmo-enhancer-error-detail-label">' . __('File Size:', 'advanced-media-offloader-enhancer') . '</div>';
                $html .= '<div class="advmo-enhancer-error-detail-value">' . esc_html($file_size) . '</div>';
                $html .= '</div>';
                
                $html .= '<div class="advmo-enhancer-error-detail-row">';
                $html .= '<div class="advmo-enhancer-error-detail-label">' . __('File Type:', 'advanced-media-offloader-enhancer') . '</div>';
                $html .= '<div class="advmo-enhancer-error-detail-value">' . esc_html($file_type) . '</div>';
                $html .= '</div>';
            }
        }
        
        // Display context if available
        if (!empty($error['context'])) {
            $html .= '<div class="advmo-enhancer-error-detail-row">';
            $html .= '<div class="advmo-enhancer-error-detail-label">' . __('Context:', 'advanced-media-offloader-enhancer') . '</div>';
            $html .= '<div class="advmo-enhancer-error-detail-value">';
            
            if (is_array($error['context'])) {
                $html .= '<pre>' . esc_html(json_encode($error['context'], JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                $html .= esc_html($error['context']);
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Add some CSS for the error details
        $html .= '<style>
            .advmo-enhancer-error-details {
                margin-top: 15px;
            }
            
            .advmo-enhancer-error-detail-row {
                margin-bottom: 10px;
                display: flex;
            }
            
            .advmo-enhancer-error-detail-label {
                font-weight: bold;
                width: 120px;
                flex-shrink: 0;
            }
            
            .advmo-enhancer-error-detail-value {
                flex-grow: 1;
            }
            
            .advmo-enhancer-error-detail-value pre {
                margin: 0;
                padding: 10px;
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                overflow: auto;
            }
        </style>';
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX handler to clear errors
     */
    public function ajax_clear_errors() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_clear_errors', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        delete_option('advmo_enhancer_error_log');
        
        wp_send_json_success(['message' => __('Error log cleared successfully.', 'advanced-media-offloader-enhancer')]);
    }
    
    /**
     * AJAX handler to retry offload
     */
    public function ajax_retry_offload() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_retry_offload', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if ($attachment_id <= 0) {
            wp_send_json_error(['message' => __('Invalid attachment ID.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check if attachment exists
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(['message' => __('Attachment not found.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Clear existing errors for this attachment
        delete_post_meta($attachment_id, 'advmo_error_log');
        
        // Try to offload the attachment
        global $advmo;
        
        if (!isset($advmo->offloader) || !$advmo->offloader) {
            wp_send_json_error(['message' => __('Offloader not available.', 'advanced-media-offloader-enhancer')]);
        }
        
        try {
            // Get cloud provider from the container
            $cloud_provider = $advmo->container->get('cloud_provider');
            
            if (!$cloud_provider) {
                wp_send_json_error(['message' => __('Cloud provider not configured.', 'advanced-media-offloader-enhancer')]);
            }
            
            // Create uploader instance
            $uploader = new \Advanced_Media_Offloader\Services\CloudAttachmentUploader($cloud_provider);
            
            // Attempt to upload
            $result = $uploader->uploadAttachment($attachment_id);
            
            if ($result) {
                // Remove this attachment from the error log
                $errors = get_option('advmo_enhancer_error_log', []);
                $updated_errors = [];
                
                foreach ($errors as $error) {
                    if ($error['attachment_id'] != $attachment_id) {
                        $updated_errors[] = $error;
                    }
                }
                
                update_option('advmo_enhancer_error_log', $updated_errors);
                
                wp_send_json_success([
                    'message' => __('Attachment offloaded successfully.', 'advanced-media-offloader-enhancer'),
                    'attachment_id' => $attachment_id,
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to offload attachment. Check the attachment error log for details.', 'advanced-media-offloader-enhancer'),
                    'attachment_id' => $attachment_id,
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf(__('Error: %s', 'advanced-media-offloader-enhancer'), $e->getMessage()),
                'attachment_id' => $attachment_id,
            ]);
        }
    }
    
    /**
     * AJAX handler to export errors
     */
    public function ajax_export_errors() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_export_errors', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        $this->export_errors();
    }
}