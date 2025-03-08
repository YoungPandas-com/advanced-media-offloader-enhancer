<?php
/**
 * Assets management for Advanced Media Offloader Enhancer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ADVMO_Enhancer_Assets {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);
    }

    /**
     * Register and enqueue assets
     * 
     * @param string $hook Current admin page
     */
    public function register_assets($hook) {
        // Only load on our plugin pages or the original plugin's pages
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        // Register and enqueue CSS
        wp_register_style(
            'advmo-enhancer-admin',
            ADVMO_ENHANCER_URL . 'assets/css/admin.css',
            [],
            ADVMO_ENHANCER_VERSION
        );
        wp_enqueue_style('advmo-enhancer-admin');

        // Register and enqueue JS
        wp_register_script(
            'advmo-enhancer-admin',
            ADVMO_ENHANCER_URL . 'assets/js/admin.js',
            ['jquery'],
            ADVMO_ENHANCER_VERSION,
            true
        );

        // Add localization data for JS
        $settings = ADVMO_Enhancer_Utils::get_settings();
        $bulk_data = ADVMO_Enhancer_Utils::get_bulk_offload_data();

        wp_localize_script('advmo-enhancer-admin', 'advmoEnhancer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('advmo_enhancer_nonce'),
            'settings' => [
                'autoProcess' => $settings['auto_process_batches'] === 'yes',
                'maxFileSize' => (int)$settings['max_file_size'],
                'enableCloudflare' => $settings['enable_cloudflare'] === 'yes',
                'autoResume' => $settings['auto_resume_on_error'] === 'yes',
                'retryAttempts' => (int)$settings['retry_attempts'],
            ],
            'bulkData' => [
                'status' => $bulk_data['status'],
                'autoProcessing' => $bulk_data['auto_processing'],
                'currentBatch' => $bulk_data['current_batch'],
                'totalBatches' => $bulk_data['total_batches'],
                'processedFiles' => $bulk_data['processed_files'],
                'totalFiles' => $bulk_data['total_files'],
            ],
            'i18n' => [
                'processing' => __('Processing...', 'advanced-media-offloader-enhancer'),
                'success' => __('Success!', 'advanced-media-offloader-enhancer'),
                'error' => __('Error!', 'advanced-media-offloader-enhancer'),
                'confirmCancel' => __('Are you sure you want to cancel the offload process? Progress will be lost.', 'advanced-media-offloader-enhancer'),
                'errorOccurred' => __('An error occurred. Please check the error log for details.', 'advanced-media-offloader-enhancer'),
                'batchComplete' => __('Batch complete!', 'advanced-media-offloader-enhancer'),
                'allComplete' => __('All media files have been processed!', 'advanced-media-offloader-enhancer'),
            ]
        ]);

        wp_enqueue_script('advmo-enhancer-admin');
    }

    /**
     * Check if current page is a plugin page
     * 
     * @param string $hook Current admin page
     * @return bool
     */
    private function is_plugin_page($hook) {
        $plugin_pages = [
            'toplevel_page_advmo',
            'media-offloader_page_advmo_media_overview',
            'toplevel_page_advmo-enhancer',
            'advmo-enhancer_page_advmo-enhancer-settings',
            'advmo-enhancer_page_advmo-enhancer-errors'
        ];

        return in_array($hook, $plugin_pages);
    }
}