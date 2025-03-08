<?php
/**
 * Settings page for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Settings {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'advmo', // Parent slug
            __('Enhancer Settings', 'advanced-media-offloader-enhancer'),
            __('Enhancer Settings', 'advanced-media-offloader-enhancer'),
            'manage_options',
            'advmo-enhancer-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'advmo_enhancer_settings',
            'advmo_enhancer_settings',
            [$this, 'sanitize_settings']
        );

        // General settings section
        add_settings_section(
            'advmo_enhancer_general',
            __('General Settings', 'advanced-media-offloader-enhancer'),
            [$this, 'render_general_section'],
            'advmo-enhancer-settings'
        );

        add_settings_field(
            'auto_process_batches',
            __('Auto-Process Batches', 'advanced-media-offloader-enhancer'),
            [$this, 'render_checkbox_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_general',
            [
                'id' => 'auto_process_batches',
                'desc' => __('Automatically process all batches without user intervention', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'max_file_size',
            __('Maximum File Size (MB)', 'advanced-media-offloader-enhancer'),
            [$this, 'render_number_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_general',
            [
                'id' => 'max_file_size',
                'desc' => __('Maximum file size in MB for offloading (10-200)', 'advanced-media-offloader-enhancer'),
                'min' => 10,
                'max' => 200,
                'step' => 1,
            ]
        );

        add_settings_field(
            'delete_local_after_offload',
            __('Delete Local Files', 'advanced-media-offloader-enhancer'),
            [$this, 'render_checkbox_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_general',
            [
                'id' => 'delete_local_after_offload',
                'desc' => __('Delete local files after successful offload', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'detailed_error_logging',
            __('Detailed Error Logging', 'advanced-media-offloader-enhancer'),
            [$this, 'render_checkbox_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_general',
            [
                'id' => 'detailed_error_logging',
                'desc' => __('Enable detailed error logging for troubleshooting', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'auto_resume_on_error',
            __('Auto-Resume on Error', 'advanced-media-offloader-enhancer'),
            [$this, 'render_checkbox_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_general',
            [
                'id' => 'auto_resume_on_error',
                'desc' => __('Automatically resume processing after an error', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'retry_attempts',
            __('Retry Attempts', 'advanced-media-offloader-enhancer'),
            [$this, 'render_number_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_general',
            [
                'id' => 'retry_attempts',
                'desc' => __('Number of retry attempts for failed offloads', 'advanced-media-offloader-enhancer'),
                'min' => 0,
                'max' => 10,
                'step' => 1,
            ]
        );

        // Cloudflare CDN section
        add_settings_section(
            'advmo_enhancer_cloudflare',
            __('Cloudflare CDN Integration', 'advanced-media-offloader-enhancer'),
            [$this, 'render_cloudflare_section'],
            'advmo-enhancer-settings'
        );

        add_settings_field(
            'enable_cloudflare',
            __('Enable Cloudflare Integration', 'advanced-media-offloader-enhancer'),
            [$this, 'render_checkbox_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_cloudflare',
            [
                'id' => 'enable_cloudflare',
                'desc' => __('Enable integration with Cloudflare CDN', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'cloudflare_api_token',
            __('Cloudflare API Token', 'advanced-media-offloader-enhancer'),
            [$this, 'render_password_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_cloudflare',
            [
                'id' => 'cloudflare_api_token',
                'desc' => __('Your Cloudflare API token with cache purge permissions', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'cloudflare_zone_id',
            __('Cloudflare Zone ID', 'advanced-media-offloader-enhancer'),
            [$this, 'render_text_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_cloudflare',
            [
                'id' => 'cloudflare_zone_id',
                'desc' => __('Your Cloudflare Zone ID for this domain', 'advanced-media-offloader-enhancer'),
            ]
        );

        add_settings_field(
            'cloudflare_auto_purge',
            __('Auto Purge Cache', 'advanced-media-offloader-enhancer'),
            [$this, 'render_checkbox_field'],
            'advmo-enhancer-settings',
            'advmo_enhancer_cloudflare',
            [
                'id' => 'cloudflare_auto_purge',
                'desc' => __('Automatically purge Cloudflare cache after offloading media', 'advanced-media-offloader-enhancer'),
            ]
        );
    }

    /**
     * Sanitize settings
     * 
     * @param array $input Input settings
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Checkboxes
        $checkboxes = [
            'auto_process_batches',
            'delete_local_after_offload',
            'detailed_error_logging',
            'enable_cloudflare',
            'cloudflare_auto_purge',
            'auto_resume_on_error',
        ];

        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) ? 'yes' : 'no';
        }

        // Number fields
        $sanitized['max_file_size'] = isset($input['max_file_size']) 
            ? max(10, min(200, intval($input['max_file_size']))) 
            : 50;

        $sanitized['retry_attempts'] = isset($input['retry_attempts']) 
            ? max(0, min(10, intval($input['retry_attempts']))) 
            : 3;

        // Text fields
        $sanitized['cloudflare_zone_id'] = isset($input['cloudflare_zone_id']) 
            ? sanitize_text_field($input['cloudflare_zone_id']) 
            : '';

        // Password fields (store as-is if provided, or keep existing value)
        $existing = ADVMO_Enhancer_Utils::get_settings();
        
        $sanitized['cloudflare_api_token'] = !empty($input['cloudflare_api_token']) 
            ? $input['cloudflare_api_token'] 
            : $existing['cloudflare_api_token'];

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Advanced Media Offloader Enhancer Settings', 'advanced-media-offloader-enhancer'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('advmo_enhancer_settings');
                do_settings_sections('advmo-enhancer-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general settings for the Advanced Media Offloader Enhancer.', 'advanced-media-offloader-enhancer') . '</p>';
    }

    /**
     * Render Cloudflare section
     */
    public function render_cloudflare_section() {
        echo '<p>' . esc_html__('Configure Cloudflare CDN integration settings.', 'advanced-media-offloader-enhancer') . '</p>';
    }

    /**
     * Render checkbox field
     * 
     * @param array $args Field arguments
     */
    public function render_checkbox_field($args) {
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $id = $args['id'];
        $checked = isset($settings[$id]) && $settings[$id] === 'yes';
        ?>
        <input type="checkbox" id="<?php echo esc_attr($id); ?>" 
               name="advmo_enhancer_settings[<?php echo esc_attr($id); ?>]" 
               value="yes" <?php checked($checked); ?>>
        <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($args['desc']); ?></label>
        <?php
    }

    /**
     * Render text field
     * 
     * @param array $args Field arguments
     */
    public function render_text_field($args) {
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $id = $args['id'];
        $value = isset($settings[$id]) ? $settings[$id] : '';
        ?>
        <input type="text" id="<?php echo esc_attr($id); ?>" 
               name="advmo_enhancer_settings[<?php echo esc_attr($id); ?>]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php
    }

    /**
     * Render password field
     * 
     * @param array $args Field arguments
     */
    public function render_password_field($args) {
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $id = $args['id'];
        $value = isset($settings[$id]) ? $settings[$id] : '';
        $placeholder = !empty($value) ? '••••••••••••••••' : '';
        ?>
        <input type="password" id="<?php echo esc_attr($id); ?>" 
               name="advmo_enhancer_settings[<?php echo esc_attr($id); ?>]" 
               value="" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>">
        <p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php
    }

    /**
     * Render number field
     * 
     * @param array $args Field arguments
     */
    public function render_number_field($args) {
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $id = $args['id'];
        $value = isset($settings[$id]) ? $settings[$id] : '';
        ?>
        <input type="number" id="<?php echo esc_attr($id); ?>" 
               name="advmo_enhancer_settings[<?php echo esc_attr($id); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($args['min']); ?>" 
               max="<?php echo esc_attr($args['max']); ?>" 
               step="<?php echo esc_attr($args['step']); ?>">
        <p class="description"><?php echo esc_html($args['desc']); ?></p>
        <?php
    }
}