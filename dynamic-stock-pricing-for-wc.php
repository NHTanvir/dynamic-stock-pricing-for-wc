<?php
/**
 * Plugin Name: Dynamic Stock Pricing for WooCommerce
 * Description: Dynamically adjust product prices based on current stock quantity to reflect supply and demand in real-time.
 * Version: 1.0.0
 * Author: Naymul Hasan Tanvir
 * License: GPL v2 or later
 * Text Domain: dynamic-stock-pricing
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DYNAMIC_STOCK_PRICING_VERSION', '1.0.0');
define('DYNAMIC_STOCK_PRICING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DYNAMIC_STOCK_PRICING_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Dynamic Stock Pricing for WooCommerce</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

// Main plugin class
class Dynamic_Stock_Pricing_For_WC {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize the plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load plugin text domain for translations
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'load_textdomain_late'), 999);

        // Initialize services after plugins are loaded
        add_action('plugins_loaded', array($this, 'init_services'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'dynamic-stock-pricing',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Late load text domain to ensure compatibility with translation systems
     */
    public function load_textdomain_late() {
        load_plugin_textdomain(
            'dynamic-stock-pricing',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Initialize services
     */
    public function init_services() {
        // Include service classes
        require_once DYNAMIC_STOCK_PRICING_PLUGIN_PATH . 'includes/Services/PricingService.php';
        require_once DYNAMIC_STOCK_PRICING_PLUGIN_PATH . 'includes/Services/CompatibilityService.php';
        require_once DYNAMIC_STOCK_PRICING_PLUGIN_PATH . 'includes/Modules/AdminSettingsModule.php';
        require_once DYNAMIC_STOCK_PRICING_PLUGIN_PATH . 'includes/Modules/CustomerMessagingModule.php';

        // Initialize the pricing service
        new \DynamicStockPricing\Services\PricingService();

        // Initialize the compatibility service
        new \DynamicStockPricing\Services\CompatibilityService();

        // Initialize admin settings module
        new \DynamicStockPricing\Modules\AdminSettingsModule();

        // Initialize customer messaging module
        new \DynamicStockPricing\Modules\CustomerMessagingModule();
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'dynamic-stock-pricing-frontend',
            DYNAMIC_STOCK_PRICING_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            DYNAMIC_STOCK_PRICING_VERSION
        );
    }
}

/**
 * Initialize the main plugin class
 */
function dynamic_stock_pricing_init() {
    return Dynamic_Stock_Pricing_For_WC::get_instance();
}

// Initialize the plugin
dynamic_stock_pricing_init();