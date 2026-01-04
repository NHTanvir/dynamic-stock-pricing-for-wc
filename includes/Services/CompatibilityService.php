<?php
namespace StockAdaptixPricing\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility Service
 * Ensures plugin works correctly with WooCommerce stock management
 */
class CompatibilityService {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Verify compatibility with WooCommerce features
		add_action( 'init', array( $this, 'verify_compatibility' ) );
		
		// Handle edge cases for different product types
		add_filter( 'woocommerce_product_is_visible', array( $this, 'ensure_simple_product_handling' ), 10, 2 );
	}

	/**
	 * Verify plugin compatibility with current WooCommerce installation
	 */
	public function verify_compatibility() {
		// Check if WooCommerce is active and at the required version
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : false;
		
		if ( $wc_version && version_compare( $wc_version, '5.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'outdated_wc_notice' ) );
		}
	}

	/**
	 * Show notice if WooCommerce version is outdated
	 */
	public function outdated_wc_notice() {
		/* translators: %s: plugin name */
		$message = __( 'StockAdaptix Pricing for WooCommerce: Requires WooCommerce version 5.0 or higher.', 'stockadaptix-pricing' );
		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Ensure plugin only affects simple products with stock management
	 *
	 * @param bool $visible Visibility status.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public function ensure_simple_product_handling( $visible, $product_id ) {
		if ( ! $this->is_enabled() ) {
			return $visible;
		}

		$product = wc_get_product( $product_id );
		
		// Only apply to simple products with stock management enabled
		if ( $product && 'simple' === $product->get_type() && $product->managing_stock() ) {
			return $visible;
		}
		
		return $visible;
	}

	/**
	 * Check if plugin is enabled
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = get_option( 'stockadaptix_pricing_settings', array() );
		return ! empty( $settings['enable_plugin'] );
	}

	/**
	 * Check if product meets requirements for stock-based pricing
	 *
	 * @param int|WC_Product $product Product ID or product object.
	 * @return bool True if product is compatible, false otherwise
	 */
	public function is_product_compatible( $product ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// Accept both product ID and product object
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		// Check if it's a valid product object
		if ( ! $product || ! is_object( $product ) ) {
			return false;
		}

		// Only apply to simple products
		if ( 'simple' !== $product->get_type() ) {
			return false;
		}

		// Only apply to products with stock management enabled
		if ( ! $product->managing_stock() ) {
			return false;
		}

		// Check if stock quantity is valid
		$stock_quantity = $product->get_stock_quantity();
		if ( null === $stock_quantity || $stock_quantity < 0 ) {
			return false; // Stock not managed or unlimited
		}

		return true;
	}
}