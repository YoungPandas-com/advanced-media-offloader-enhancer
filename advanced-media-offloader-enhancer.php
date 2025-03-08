<?php
/**
 * Plugin Name: Advanced Media Offloader Enhancer
 * Description: Enhances the Advanced Media Offloader with auto-batch processing, increased file size limits, better error handling, and Cloudflare CDN integration.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 8.1
 * Author: YP Studio
 * Author URI: https://yp.studio
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-media-offloader-enhancer
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('ADVMO_ENHANCER_VERSION', '1.0.0');
define('ADVMO_ENHANCER_PATH', plugin_dir_path(__FILE__));
define('ADVMO_ENHANCER_URL', plugin_dir_url(__FILE__));
define('ADVMO_ENHANCER_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class ADVMO_Enhancer {
    /**
     * The single instance of the class
     * 
     * @var ADVMO_Enhancer
     */
    private static $instance = null;

    /**
     * Main plugin instance
     * 
     * @return ADVMO_Enhancer
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Check if Advanced Media Offloader is active
        add_action('admin_init', [$this, 'check_dependencies']);

        // Initialize the plugin
        add_action('plugins_loaded', [$this, 'init']);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . ADVMO_ENHANCER_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        if (!class_exists('ADVMO')) {
            add_action('admin_notices', [$this, 'dependency_notice']);
            deactivate_plugins(ADVMO_ENHANCER_BASENAME);
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }

    /**
     * Display dependency notice
     */
    public function dependency_notice() {
        echo '<div class="error"><p>';
        echo esc_html__('Advanced Media Offloader Enhancer requires Advanced Media Offloader plugin to be installed and activated.', 'advanced-media-offloader-enhancer');
        echo '</p></div>';
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Only proceed if the original plugin is active
        if (!class_exists('ADVMO')) {
            return;
        }

        // Load textdomain
        load_plugin_textdomain('advanced-media-offloader-enhancer', false, dirname(ADVMO_ENHANCER_BASENAME) . '/languages/');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core
        require_once ADVMO_ENHANCER_PATH . 'includes/class-advmo-enhancer-assets.php';
        require_once ADVMO_ENHANCER_PATH . 'includes/class-advmo-enhancer-utils.php';
        
        // Admin
        if (is_admin()) {
            require_once ADVMO_ENHANCER_PATH . 'includes/admin/class-advmo-enhancer-settings.php';
            require_once ADVMO_ENHANCER_PATH . 'includes/admin/class-advmo-enhancer-media-overview.php';
            require_once ADVMO_ENHANCER_PATH . 'includes/admin/class-advmo-enhancer-error-dashboard.php';
        }
        
        // Services
        require_once ADVMO_ENHANCER_PATH . 'includes/services/class-advmo-enhancer-bulk-handler.php';
        require_once ADVMO_ENHANCER_PATH . 'includes/services/class-advmo-enhancer-cloudflare.php';
        require_once ADVMO_ENHANCER_PATH . 'includes/services/class-advmo-enhancer-error-manager.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize assets
        new ADVMO_Enhancer_Assets();
        
        // Initialize admin components
        if (is_admin()) {
            new ADVMO_Enhancer_Settings();
            new ADVMO_Enhancer_Media_Overview();
            new ADVMO_Enhancer_Error_Dashboard();
        }
        
        // Initialize services
        new ADVMO_Enhancer_Bulk_Handler();
        new ADVMO_Enhancer_Cloudflare();
        new ADVMO_Enhancer_Error_Manager();
    }
    
    /**
     * Add settings link to plugins page
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=advmo-enhancer-settings') . '">' . __('Settings', 'advanced-media-offloader-enhancer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Returns the main instance of ADVMO_Enhancer
 * 
 * @return ADVMO_Enhancer
 */
function ADVMO_Enhancer() {
    return ADVMO_Enhancer::instance();
}

// Initialize the plugin
ADVMO_Enhancer();