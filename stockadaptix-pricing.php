<?php
/**
 * Plugin Name: StockAdaptix Pricing for WooCommerce
 * Description: Dynamically adjust product prices based on current stock quantity to reflect supply and demand in real-time.
 * Version: 1.0.0
 * Author: Naymul Hasan Tanvir
 * License: GPL v2 or later
 * Text Domain: stockadaptix-pricing
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * WC requires at least: 5.0
 * WC tested up to: 10.4.3
 * Requires Plugins: woocommerce
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use Automattic\WooCommerce\Utilities\FeaturesUtil;

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( FeaturesUtil::class ) ) {
        FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

// Define plugin constants
define( 'STOCKADAPTIX_PRICING_VERSION', '1.0.1' );
define( 'STOCKADAPTIX_PRICING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STOCKADAPTIX_PRICING_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );


/**
 * Main plugin class
 */
class StockAdaptix_Pricing_For_WC {

	/**
	 * Plugin instance
	 *
	 * @var object
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
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {

		// Initialize services after plugins are loaded
		add_action( 'plugins_loaded', array( $this, 'init_services' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}


	/**
	 * Initialize services
	 */
	public function init_services() {
		// Check if WooCommerce is active before initializing services
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_required_notice' ) );
			return;
		}

		// Include service classes
		require_once STOCKADAPTIX_PRICING_PLUGIN_PATH . 'includes/Services/PricingService.php';
		require_once STOCKADAPTIX_PRICING_PLUGIN_PATH . 'includes/Services/CompatibilityService.php';
		require_once STOCKADAPTIX_PRICING_PLUGIN_PATH . 'includes/Modules/AdminSettingsModule.php';
		require_once STOCKADAPTIX_PRICING_PLUGIN_PATH . 'includes/Modules/CustomerMessagingModule.php';

		// Initialize the pricing service
		new \StockAdaptixPricing\Services\PricingService();

		// Initialize the compatibility service
		new \StockAdaptixPricing\Services\CompatibilityService();

		// Initialize admin settings module
		new \StockAdaptixPricing\Modules\AdminSettingsModule();

		// Initialize customer messaging module
		new \StockAdaptixPricing\Modules\CustomerMessagingModule();
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Display notice if WooCommerce is not active
	 */
	public function woocommerce_required_notice() {
		/* translators: %s: Plugin name */
		$message = sprintf( __( '%s requires WooCommerce to be installed and active.', 'stockadaptix-pricing' ), __( 'StockAdaptix Pricing for WooCommerce', 'stockadaptix-pricing' ) );
		printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'stockadaptix-pricing-frontend',
			STOCKADAPTIX_PRICING_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			STOCKADAPTIX_PRICING_VERSION
		);
	}
}

/**
 * Initialize the main plugin class
 *
 * @return object
 */
function stockadaptix_pricing_init() {
	return StockAdaptix_Pricing_For_WC::get_instance();
}

// Initialize the plugin
stockadaptix_pricing_init();