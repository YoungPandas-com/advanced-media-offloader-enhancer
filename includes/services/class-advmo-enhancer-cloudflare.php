<?php
/**
 * Cloudflare CDN Integration for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Cloudflare {
    /**
     * API endpoint
     * 
     * @var string
     */
    private $api_endpoint = 'https://api.cloudflare.com/client/v4/';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only hook if Cloudflare integration is enabled
        if (ADVMO_Enhancer_Utils::get_setting('enable_cloudflare') !== 'yes') {
            return;
        }
        
        // Add actions for cache purging
        add_action('advmo_after_upload_to_cloud', [$this, 'purge_cache_for_attachment'], 10, 1);
        add_action('admin_post_advmo_enhancer_purge_all_cache', [$this, 'handle_purge_all_cache']);
        add_action('wp_ajax_advmo_enhancer_purge_attachment_cache', [$this, 'ajax_purge_attachment_cache']);
        
        // Add settings page section for manual cache purging
        add_action('admin_menu', [$this, 'add_cf_actions_page']);
        
        // Add purge link to media list table
        add_filter('media_row_actions', [$this, 'add_purge_action_link'], 10, 2);
    }
    
    /**
     * Add Cloudflare actions submenu
     */
    public function add_cf_actions_page() {
        add_submenu_page(
            'advmo',
            __('Cloudflare Actions', 'advanced-media-offloader-enhancer'),
            __('Cloudflare Actions', 'advanced-media-offloader-enhancer'),
            'manage_options',
            'advmo-cloudflare',
            [$this, 'render_cf_actions_page']
        );
    }
    
    /**
     * Render Cloudflare actions page
     */
    public function render_cf_actions_page() {
        $zone_id = ADVMO_Enhancer_Utils::get_setting('cloudflare_zone_id');
        $api_token = ADVMO_Enhancer_Utils::get_setting('cloudflare_api_token');
        $auto_purge = ADVMO_Enhancer_Utils::get_setting('cloudflare_auto_purge') === 'yes';
        
        $zone_valid = !empty($zone_id);
        $token_valid = !empty($api_token);
        $purge_url = admin_url('admin-post.php?action=advmo_enhancer_purge_all_cache');
        $purge_nonce = wp_create_nonce('advmo_enhancer_purge_all_cache');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Cloudflare CDN Actions', 'advanced-media-offloader-enhancer'); ?></h1>
            
            <?php if (!$zone_valid || !$token_valid): ?>
                <div class="notice notice-error">
                    <p>
                        <?php echo esc_html__('Cloudflare integration is not properly configured.', 'advanced-media-offloader-enhancer'); ?>
                        <a href="<?php echo admin_url('admin.php?page=advmo-enhancer-settings'); ?>"><?php echo esc_html__('Configure settings', 'advanced-media-offloader-enhancer'); ?></a>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="advmo-cf-card">
                <h2><?php echo esc_html__('Cache Management', 'advanced-media-offloader-enhancer'); ?></h2>
                
                <div class="advmo-cf-settings-summary">
                    <p>
                        <strong><?php echo esc_html__('Zone ID:', 'advanced-media-offloader-enhancer'); ?></strong>
                        <?php echo $zone_valid ? esc_html(substr($zone_id, 0, 6) . '...' . substr($zone_id, -4)) : esc_html__('Not set', 'advanced-media-offloader-enhancer'); ?>
                    </p>
                    
                    <p>
                        <strong><?php echo esc_html__('API Token:', 'advanced-media-offloader-enhancer'); ?></strong>
                        <?php echo $token_valid ? esc_html__('Configured', 'advanced-media-offloader-enhancer') : esc_html__('Not set', 'advanced-media-offloader-enhancer'); ?>
                    </p>
                    
                    <p>
                        <strong><?php echo esc_html__('Auto-Purge:', 'advanced-media-offloader-enhancer'); ?></strong>
                        <?php echo $auto_purge ? esc_html__('Enabled', 'advanced-media-offloader-enhancer') : esc_html__('Disabled', 'advanced-media-offloader-enhancer'); ?>
                    </p>
                </div>
                
                <?php if ($zone_valid && $token_valid): ?>
                    <div class="advmo-cf-actions">
                        <h3><?php echo esc_html__('Purge All Cache', 'advanced-media-offloader-enhancer'); ?></h3>
                        <p><?php echo esc_html__('This will purge all cached files from Cloudflare CDN. Use with caution as it may temporarily affect site performance.', 'advanced-media-offloader-enhancer'); ?></p>
                        
                        <form method="post" action="<?php echo esc_url($purge_url); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to purge all cached files from Cloudflare CDN?', 'advanced-media-offloader-enhancer')); ?>');">
                            <?php wp_nonce_field('advmo_enhancer_purge_all_cache', 'purge_nonce'); ?>
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Purge All Cache', 'advanced-media-offloader-enhancer'); ?></button>
                        </form>
                    </div>
                    
                    <div class="advmo-cf-actions">
                        <h3><?php echo esc_html__('Purge Media Files Cache', 'advanced-media-offloader-enhancer'); ?></h3>
                        <p><?php echo esc_html__('This will purge the cache for all media files only.', 'advanced-media-offloader-enhancer'); ?></p>
                        
                        <button type="button" class="button button-secondary" id="advmo-purge-media-cache"><?php echo esc_html__('Purge Media Cache', 'advanced-media-offloader-enhancer'); ?></button>
                        <span id="advmo-purge-media-status"></span>
                    </div>
                    
                    <script>
                    jQuery(document).ready(function($) {
                        $('#advmo-purge-media-cache').on('click', function() {
                            if (confirm('<?php echo esc_js(__('Are you sure you want to purge all media files from Cloudflare CDN cache?', 'advanced-media-offloader-enhancer')); ?>')) {
                                $('#advmo-purge-media-status').text('<?php echo esc_js(__('Purging...', 'advanced-media-offloader-enhancer')); ?>');
                                
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'advmo_enhancer_purge_media_cache',
                                        nonce: '<?php echo wp_create_nonce('advmo_enhancer_purge_media_cache'); ?>'
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            $('#advmo-purge-media-status').text(response.data.message);
                                        } else {
                                            $('#advmo-purge-media-status').text(response.data.message || '<?php echo esc_js(__('Error purging cache.', 'advanced-media-offloader-enhancer')); ?>');
                                        }
                                    },
                                    error: function() {
                                        $('#advmo-purge-media-status').text('<?php echo esc_js(__('Error purging cache.', 'advanced-media-offloader-enhancer')); ?>');
                                    }
                                });
                            }
                        });
                    });
                    </script>
                <?php endif; ?>
            </div>
            
            <div class="advmo-cf-card">
                <h2><?php echo esc_html__('Cloudflare Setup Instructions', 'advanced-media-offloader-enhancer'); ?></h2>
                
                <div class="advmo-cf-instructions">
                    <h3><?php echo esc_html__('Step 1: Find your Zone ID', 'advanced-media-offloader-enhancer'); ?></h3>
                    <p><?php echo esc_html__('Log in to your Cloudflare dashboard and select your domain. The Zone ID is displayed on the overview page in the right sidebar.', 'advanced-media-offloader-enhancer'); ?></p>
                    
                    <h3><?php echo esc_html__('Step 2: Create an API Token', 'advanced-media-offloader-enhancer'); ?></h3>
                    <ol>
                        <li><?php echo esc_html__('Go to your Cloudflare dashboard.', 'advanced-media-offloader-enhancer'); ?></li>
                        <li><?php echo esc_html__('Navigate to My Profile > API Tokens > Create Token.', 'advanced-media-offloader-enhancer'); ?></li>
                        <li><?php echo esc_html__('Use the "Edit zone" template or create a custom token.', 'advanced-media-offloader-enhancer'); ?></li>
                        <li><?php echo esc_html__('Ensure the token has the "Cache Purge" permission.', 'advanced-media-offloader-enhancer'); ?></li>
                        <li><?php echo esc_html__('Set the Zone Resources to include your website.', 'advanced-media-offloader-enhancer'); ?></li>
                        <li><?php echo esc_html__('Create the token and copy it to the Cloudflare settings in this plugin.', 'advanced-media-offloader-enhancer'); ?></li>
                    </ol>
                    
                    <h3><?php echo esc_html__('Step 3: Configure the plugin', 'advanced-media-offloader-enhancer'); ?></h3>
                    <p>
                        <?php echo esc_html__('Enter your Zone ID and API Token in the plugin settings, then enable the Cloudflare integration.', 'advanced-media-offloader-enhancer'); ?>
                        <a href="<?php echo admin_url('admin.php?page=advmo-enhancer-settings'); ?>"><?php echo esc_html__('Go to settings', 'advanced-media-offloader-enhancer'); ?></a>
                    </p>
                </div>
            </div>
        </div>
        
        <style>
        .advmo-cf-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin: 20px 0;
            padding: 20px;
        }
        
        .advmo-cf-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .advmo-cf-settings-summary {
            margin-bottom: 20px;
        }
        
        .advmo-cf-actions {
            margin-bottom: 30px;
        }
        
        .advmo-cf-instructions ol {
            margin-left: 20px;
        }
        
        #advmo-purge-media-status {
            margin-left: 10px;
            vertical-align: middle;
        }
        </style>
        <?php
    }
    
    /**
     * Purge cache for a specific attachment
     * 
     * @param int $attachment_id Attachment ID
     * @return bool Success status
     */
    public function purge_cache_for_attachment($attachment_id) {
        // Skip if auto-purge is disabled
        if (ADVMO_Enhancer_Utils::get_setting('cloudflare_auto_purge') !== 'yes') {
            return false;
        }
        
        // Get attachment URL
        $urls = $this->get_attachment_urls($attachment_id);
        
        if (empty($urls)) {
            return false;
        }
        
        return $this->purge_urls($urls);
    }
    
    /**
     * Handle purge all cache action
     */
    public function handle_purge_all_cache() {
        // Check nonce
        if (!isset($_POST['purge_nonce']) || !wp_verify_nonce($_POST['purge_nonce'], 'advmo_enhancer_purge_all_cache')) {
            wp_die(__('Security check failed.', 'advanced-media-offloader-enhancer'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer'));
        }
        
        // Purge all cache
        $result = $this->purge_all();
        
        // Redirect back with status
        if ($result) {
            wp_redirect(add_query_arg(['page' => 'advmo-cloudflare', 'purged' => '1'], admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(['page' => 'advmo-cloudflare', 'error' => '1'], admin_url('admin.php')));
        }
        
        exit;
    }
    
    /**
     * AJAX handler to purge cache for a specific attachment
     */
    public function ajax_purge_attachment_cache() {
        // Check nonce
        if (!check_ajax_referer('advmo_enhancer_purge_cache', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Check user permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'advanced-media-offloader-enhancer')]);
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if ($attachment_id <= 0) {
            wp_send_json_error(['message' => __('Invalid attachment ID.', 'advanced-media-offloader-enhancer')]);
        }
        
        // Purge cache for the attachment
        $result = $this->purge_cache_for_attachment($attachment_id);
        
        if ($result) {
            wp_send_json_success([
                'message' => __('Cache purged successfully.', 'advanced-media-offloader-enhancer'),
                'attachment_id' => $attachment_id,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to purge cache. Check Cloudflare settings.', 'advanced-media-offloader-enhancer'),
                'attachment_id' => $attachment_id,
            ]);
        }
    }
    
    /**
     * Add purge cache link to media row actions
     * 
     * @param array $actions Existing actions
     * @param WP_Post $post Post object
     * @return array Modified actions
     */
    public function add_purge_action_link($actions, $post) {
        // Only add for offloaded attachments
        $is_offloaded = get_post_meta($post->ID, 'advmo_offloaded', true);
        
        if ($is_offloaded && ADVMO_Enhancer_Utils::get_setting('enable_cloudflare') === 'yes') {
            $purge_url = admin_url('admin-ajax.php');
            $nonce = wp_create_nonce('advmo_enhancer_purge_cache');
            
            $actions['cf_purge'] = sprintf(
                '<a href="#" class="advmo-enhancer-purge-cache" data-id="%d" data-nonce="%s">%s</a>',
                $post->ID,
                $nonce,
                __('Purge CDN Cache', 'advanced-media-offloader-enhancer')
            );
            
            // Add script for the AJAX action
            add_action('admin_footer', function() {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    $('.advmo-enhancer-purge-cache').on('click', function(e) {
                        e.preventDefault();
                        
                        var $link = $(this);
                        var attachment_id = $link.data('id');
                        var nonce = $link.data('nonce');
                        
                        $link.text('<?php echo esc_js(__('Purging...', 'advanced-media-offloader-enhancer')); ?>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'advmo_enhancer_purge_attachment_cache',
                                attachment_id: attachment_id,
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $link.text('<?php echo esc_js(__('Purged!', 'advanced-media-offloader-enhancer')); ?>');
                                    setTimeout(function() {
                                        $link.text('<?php echo esc_js(__('Purge CDN Cache', 'advanced-media-offloader-enhancer')); ?>');
                                    }, 2000);
                                } else {
                                    $link.text('<?php echo esc_js(__('Failed!', 'advanced-media-offloader-enhancer')); ?>');
                                    setTimeout(function() {
                                        $link.text('<?php echo esc_js(__('Purge CDN Cache', 'advanced-media-offloader-enhancer')); ?>');
                                    }, 2000);
                                    console.error('Error:', response.data.message);
                                }
                            },
                            error: function() {
                                $link.text('<?php echo esc_js(__('Failed!', 'advanced-media-offloader-enhancer')); ?>');
                                setTimeout(function() {
                                    $link.text('<?php echo esc_js(__('Purge CDN Cache', 'advanced-media-offloader-enhancer')); ?>');
                                }, 2000);
                            }
                        });
                    });
                });
                </script>
                <?php
            });
        }
        
        return $actions;
    }
    
    /**
     * Get all URLs associated with an attachment
     * 
     * @param int $attachment_id Attachment ID
     * @return array Array of URLs
     */
    private function get_attachment_urls($attachment_id) {
        $urls = [];
        
        // Get the main URL
        $main_url = wp_get_attachment_url($attachment_id);
        
        if ($main_url) {
            $urls[] = $main_url;
            
            // For images, get all size URLs
            if (wp_attachment_is_image($attachment_id)) {
                $sizes = get_intermediate_image_sizes();
                
                foreach ($sizes as $size) {
                    $sized_image = wp_get_attachment_image_src($attachment_id, $size);
                    
                    if ($sized_image && isset($sized_image[0])) {
                        $urls[] = $sized_image[0];
                    }
                }
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Purge specific URLs from Cloudflare cache
     * 
     * @param array $urls URLs to purge
     * @return bool Success status
     */
    private function purge_urls($urls) {
        if (empty($urls)) {
            return false;
        }
        
        $zone_id = ADVMO_Enhancer_Utils::get_setting('cloudflare_zone_id');
        $api_token = ADVMO_Enhancer_Utils::get_setting('cloudflare_api_token');
        
        if (empty($zone_id) || empty($api_token)) {
            return false;
        }
        
        $endpoint = $this->api_endpoint . 'zones/' . $zone_id . '/purge_cache';
        
        $request_args = [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'files' => $urls,
            ]),
        ];
        
        $response = wp_remote_request($endpoint, $request_args);
        
        if (is_wp_error($response)) {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'cloudflare_purge_failed',
                $response->get_error_message(),
                ['urls' => $urls]
            );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300 && isset($response_data['success']) && $response_data['success']) {
            return true;
        }
        
        // Log error
        ADVMO_Enhancer_Utils::log_error(
            0,
            'cloudflare_purge_error',
            isset($response_data['errors'][0]['message']) ? $response_data['errors'][0]['message'] : 'Unknown error',
            [
                'urls' => $urls,
                'response_code' => $response_code,
                'response_body' => $response_body,
            ]
        );
        
        return false;
    }
    
    /**
     * Purge all cache from Cloudflare
     * 
     * @return bool Success status
     */
    private function purge_all() {
        $zone_id = ADVMO_Enhancer_Utils::get_setting('cloudflare_zone_id');
        $api_token = ADVMO_Enhancer_Utils::get_setting('cloudflare_api_token');
        
        if (empty($zone_id) || empty($api_token)) {
            return false;
        }
        
        $endpoint = $this->api_endpoint . 'zones/' . $zone_id . '/purge_cache';
        
        $request_args = [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'purge_everything' => true,
            ]),
        ];
        
        $response = wp_remote_request($endpoint, $request_args);
        
        if (is_wp_error($response)) {
            ADVMO_Enhancer_Utils::log_error(
                0,
                'cloudflare_purge_all_failed',
                $response->get_error_message(),
                []
            );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300 && isset($response_data['success']) && $response_data['success']) {
            return true;
        }
        
        // Log error
        ADVMO_Enhancer_Utils::log_error(
            0,
            'cloudflare_purge_all_error',
            isset($response_data['errors'][0]['message']) ? $response_data['errors'][0]['message'] : 'Unknown error',
            [
                'response_code' => $response_code,
                'response_body' => $response_body,
            ]
        );
        
        return false;
    }
}