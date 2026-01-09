<?php
namespace StockAdaptixPricing\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Product;

/**
 * Pricing Service
 * Handles the core logic for dynamic stock-based pricing
 */
class PricingService {

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
		// Check if HPOS is enabled
		$hpos_enabled = $this->is_hpos_enabled();

		// Apply pricing adjustment via a method that doesn't cause sale display
		// Only apply to frontend to avoid issues with HPOS and order processing
		if ( ! is_admin() && ! defined( 'DOING_AJAX' ) && ! $this->is_order_processing_context() ) {
			add_filter( 'woocommerce_product_get_price', array( $this, 'adjust_price' ), 10, 2 );
			add_filter( 'woocommerce_product_get_regular_price', array( $this, 'adjust_price' ), 10, 2 );
			add_filter( 'woocommerce_product_get_sale_price', array( $this, 'adjust_price' ), 10, 2 );
		}

		// Use a targeted approach for the display to avoid sale appearance
		add_filter( 'woocommerce_get_price_html', array( $this, 'adjust_price_html' ), 20, 2 );
		add_filter( 'woocommerce_cart_product_price', array( $this, 'adjust_cart_product_price_html' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'adjust_cart_item_price_html' ), 20, 3 );

		// Handle cart pricing
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 20, 2 );
		add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 20 );

		// For checkout/cart calculations - only on frontend
		if ( ! is_admin() && ! defined( 'DOING_AJAX' ) && ! $this->is_order_processing_context() ) {
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20 );
		}

		// Compatibility with order processing and HPOS
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'adjust_order_item_totals' ), 10, 3 );

		// Email improvements compatibility
		add_action( 'woocommerce_email', array( $this, 'setup_email_compatibility' ), 5 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'setup_email_compatibility' ), 5 );
	}

	/**
	 * Get plugin settings
	 *
	 * @return array
	 */
	private function get_settings() {
		$defaults = array(
			'enable_plugin'              => 1,
			'low_stock_threshold'        => 5,
			'low_stock_price_increase'   => 40,
			'medium_stock_threshold'     => 20,
			'medium_stock_price_increase' => 20,
			'high_stock_threshold'       => 100,
			'high_stock_price_decrease'  => 15,
			'customer_message_enabled'   => 1,
			'customer_message'           => __( 'High demand – price adjusted based on availability', 'stockadaptix-pricing' ),
		);

		$settings = get_option( 'stockadaptix_pricing_settings', array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Check if plugin is enabled
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = $this->get_settings();
		return (bool) $settings['enable_plugin'];
	}

	/**
	 * Calculate adjusted price based on stock
	 *
	 * @param int   $product_id ID of the product.
	 * @param float $original_price Original price of the product.
	 * @return float
	 */
	public function calculate_adjusted_price( $product_id, $original_price ) {
		if ( ! $this->is_enabled() ) {
			return $original_price;
		}

		$product_id       = absint( $product_id );
		$original_price   = floatval( $original_price );
		
		// Validate inputs
		if ( $product_id <= 0 || $original_price < 0 ) {
			return $original_price;
		}

		$product = wc_get_product( $product_id );
		
		// Only apply to products with stock management enabled
		if ( ! $product || ! $product->managing_stock() ) {
			return $original_price;
		}

		$stock_quantity = $product->get_stock_quantity();
		
		// If stock is not managed or not set, return original price
		if ( null === $stock_quantity || $stock_quantity < 0 ) {
			return $original_price;
		}

		$settings = $this->get_settings();
		
		// Get thresholds and adjustments with proper sanitization
		$low_stock_threshold     = max( 0, intval( $settings['low_stock_threshold'] ) );
		$low_increase_pct        = max( 0, floatval( $settings['low_stock_price_increase'] ) );
		$medium_stock_threshold  = max( 0, intval( $settings['medium_stock_threshold'] ) );
		$medium_increase_pct     = max( 0, floatval( $settings['medium_stock_price_increase'] ) );
		$high_stock_threshold    = max( 0, intval( $settings['high_stock_threshold'] ) );
		$high_decrease_pct       = max( 0, floatval( $settings['high_stock_price_decrease'] ) );

		$adjustment_factor = 0; // Default: no adjustment

		if ( $stock_quantity <= $low_stock_threshold ) {
			// If stock <= low threshold, increase price by low_stock_price_increase%
			$adjustment_factor = $low_increase_pct / 100;
		} elseif ( $stock_quantity <= $medium_stock_threshold ) {
			// If stock <= medium threshold, increase price by medium_stock_price_increase%
			$adjustment_factor = $medium_increase_pct / 100;
		} elseif ( $stock_quantity >= $high_stock_threshold ) {
			// If stock >= high threshold, decrease price by high_stock_price_decrease%
			$adjustment_factor = -$high_decrease_pct / 100;
		}
		// Otherwise, adjustment_factor remains 0 (no change)

		// Calculate new price
		$adjusted_price = $original_price * ( 1 + $adjustment_factor );
		
		// Ensure price doesn't become negative
		return max( 0, $adjusted_price );
	}

	/**
	 * Adjust product price based on stock
	 *
	 * @param float     $price Current price.
	 * @param WC_Product $product Product object.
	 * @return float
	 */
	public function adjust_price( $price, $product ) {
		// Only apply adjustments if plugin is enabled and product exists
		if ( ! $this->is_enabled() || ! $product instanceof WC_Product ) {
			return $price;
		}

		// Skip adjustment during admin operations, AJAX calls, and order processing to maintain HPOS compatibility
		if ( is_admin() || defined( 'DOING_AJAX' ) || doing_action( 'woocommerce_new_order' ) || doing_action( 'woocommerce_update_order' ) || doing_action( 'woocommerce_save_order' ) ) {
			return $price;
		}

		// Only apply to simple products with stock management enabled
		if ( 'simple' !== $product->get_type() || ! $product->managing_stock() ) {
			return $price;
		}

		$product_id = $product->get_id();
		// Get the original price directly from post meta to avoid compounding
		$original_regular_price = floatval( get_post_meta( $product_id, '_regular_price', true ) );

		// If for some reason the original price isn't available, use the passed price
		$base_price = $original_regular_price > 0 ? $original_regular_price : floatval( $price );

		$adjusted_price = $this->calculate_adjusted_price( $product_id, $base_price );

		// Return adjusted price without creating sale appearance
		return $adjusted_price;
	}

	/**
	 * Adjust price HTML display to show only the adjusted price without sale indicators
	 *
	 * @param string    $price_html Original price HTML.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function adjust_price_html( $price_html, $product ) {
		// Only apply adjustments if plugin is enabled and product exists
		if ( ! $this->is_enabled() || ! is_object( $product ) || ! $product->is_type( 'simple' ) || ! $product->managing_stock() ) {
			return $price_html;
		}

		// Skip adjustment during admin operations, AJAX calls, and order processing to maintain HPOS compatibility
		if ( is_admin() || defined( 'DOING_AJAX' ) || doing_action( 'woocommerce_new_order' ) || doing_action( 'woocommerce_update_order' ) || doing_action( 'woocommerce_save_order' ) ) {
			return $price_html;
		}

		$product_id = $product->get_id();
		$original_regular_price = floatval( get_post_meta( $product_id, '_regular_price', true ) );
		$base_price = $original_regular_price > 0 ? $original_regular_price : floatval( $product->get_price() );
		$adjusted_price = $this->calculate_adjusted_price( $product_id, $base_price );

		// Format the adjusted price using WooCommerce's price formatting
		$formatted_price = wc_price( $adjusted_price );

		// Return the adjusted price without the original price strikethrough
		return '<span class="woocommerce-Price-amount amount">' . $formatted_price . '</span>';
	}

	/**
	 * Adjust cart product price display
	 *
	 * @param string $price_html Original price HTML.
	 * @param array  $cart_item Cart item array.
	 * @return string
	 */
	public function adjust_cart_product_price_html( $price_html, $cart_item ) {
		// Check if $cart_item is an array (expected) or a product object (WooCommerce quirk)
		if ( is_object( $cart_item ) && $cart_item instanceof WC_Product ) {
			$product = $cart_item;
		} elseif ( is_array( $cart_item ) && isset( $cart_item['data'] ) ) {
			$product = $cart_item['data'];
		} else {
			return $price_html;
		}
		
		// Only apply adjustments if plugin is enabled and product exists
		if ( ! $this->is_enabled() || ! is_object( $product ) || ! $product->is_type( 'simple' ) || ! $product->managing_stock() ) {
			return $price_html;
		}

		$product_id = $product->get_id();
		$original_regular_price = floatval( get_post_meta( $product_id, '_regular_price', true ) );
		$base_price = $original_regular_price > 0 ? $original_regular_price : floatval( $product->get_price() );
		$adjusted_price = $this->calculate_adjusted_price( $product_id, $base_price );

		// Format the adjusted price using WooCommerce's price formatting
		return wc_price( $adjusted_price );
	}

	/**
	 * Adjust cart item price display
	 *
	 * @param string $product_price Original product price.
	 * @param array  $cart_item Cart item array.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function adjust_cart_item_price_html( $product_price, $cart_item, $cart_item_key ) {
		$product = $cart_item['data'];
		
		// Only apply adjustments if plugin is enabled and product exists
		if ( ! $this->is_enabled() || ! is_object( $product ) || ! $product->is_type( 'simple' ) || ! $product->managing_stock() ) {
			return $product_price;
		}

		$product_id = $product->get_id();
		$original_regular_price = floatval( get_post_meta( $product_id, '_regular_price', true ) );
		$base_price = $original_regular_price > 0 ? $original_regular_price : floatval( $product->get_price() );
		$adjusted_price = $this->calculate_adjusted_price( $product_id, $base_price );

		// Format the adjusted price using WooCommerce's price formatting
		return wc_price( $adjusted_price );
	}

	/**
	 * Add custom data to cart item
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! $this->is_enabled() ) {
			return $cart_item_data;
		}

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( $product_id <= 0 ) {
			return $cart_item_data;
		}

		$the_product_id = $variation_id > 0 ? $variation_id : $product_id;
		$product = wc_get_product( $the_product_id );

		if ( $product && $product->managing_stock() ) {
			$original_regular_price = floatval( get_post_meta( $the_product_id, '_regular_price', true ) );
			// Use original price to avoid compounding
			$base_price     = $original_regular_price > 0 ? $original_regular_price : floatval( $product->get_price() );
			$adjusted_price = $this->calculate_adjusted_price( $the_product_id, $base_price );

			if ( $adjusted_price !== $base_price ) {
				$cart_item_data['dsp_adjusted_price'] = $adjusted_price;
				$cart_item_data['dsp_original_price'] = $base_price;

				// Generate unique hash to prevent merging of items with different prices
				$cart_item_data['dsp_unique_key'] = md5( microtime() . wp_rand() );
			}
		}

		return $cart_item_data;
	}

	/**
	 * Get cart item from session with adjusted price
	 *
	 * @param array $cart_item Cart item array.
	 * @param array $values Values from session.
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( isset( $values['dsp_adjusted_price'] ) ) {
			$adjusted_price = floatval( $values['dsp_adjusted_price'] );
			$original_price = isset( $values['dsp_original_price'] ) ? floatval( $values['dsp_original_price'] ) : 0;
			
			// Validate the prices before applying
			if ( $adjusted_price >= 0 ) {
				$cart_item['data']->set_price( $adjusted_price );
				$cart_item['dsp_adjusted_price'] = $adjusted_price;
				$cart_item['dsp_original_price'] = $original_price;
			}
		}
		return $cart_item;
	}

	/**
	 * Recalculate totals to account for adjusted prices
	 *
	 * @param object $cart Cart object.
	 */
	public function calculate_totals( $cart ) {
		// This is called after cart calculation, the prices are already adjusted through the product objects
		// This method can be extended if additional adjustment calculations are needed
	}

	/**
	 * Adjust prices before totals are calculated (for cart/checkout)
	 *
	 * @param object $cart Cart object.
	 */
	public function before_calculate_totals( $cart ) {
		if ( ! $this->is_enabled() || is_admin() || defined( 'DOING_AJAX' ) || doing_action( 'woocommerce_new_order' ) || doing_action( 'woocommerce_update_order' ) || doing_action( 'woocommerce_save_order' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product    = $cart_item['data'];
			$product_id = $product->get_id();

			if ( 'simple' === $product->get_type() && $product->managing_stock() ) {
				$original_regular_price = floatval( get_post_meta( $product_id, '_regular_price', true ) );
				$adjusted_price         = $this->calculate_adjusted_price( $product_id, $original_regular_price );

				if ( $adjusted_price !== $original_regular_price && $original_regular_price > 0 ) {
					// Store original price in cart item for reference during order creation
					$product->set_price( $adjusted_price );
					$product->set_regular_price( $adjusted_price );

					// Store adjustment info in cart for later reference
					if ( ! isset( $cart_item['dsp_adjusted_price'] ) ) {
						$cart_item['dsp_adjusted_price'] = $adjusted_price;
						$cart_item['dsp_original_price'] = $original_regular_price;
					}
				}
			}
		}
	}

	/**
	 * Adjust structured data (schema markup) for price
	 *
	 * @param array     $markup Structured data markup.
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public function structured_data_price( $markup, $product ) {
		if ( ! $this->is_enabled() || ! is_object( $product ) ) {
			return $markup;
		}

		$product_id = absint( $product->get_id() );
		
		if ( $product_id <= 0 ) {
			return $markup;
		}

		$regular_price = $this->calculate_adjusted_price( $product_id, floatval( $product->get_regular_price() ) );
		$sale_price    = $product->get_sale_price();
		
		if ( $sale_price ) {
			$sale_price = $this->calculate_adjusted_price( $product_id, floatval( $sale_price ) );
		}
		
		// Update the structured data with adjusted prices
		if ( isset( $markup['price'] ) ) {
			$markup['price'] = wc_format_decimal( max( 0, $regular_price ), wc_get_price_decimals() );
		}
		
		if ( isset( $markup['priceSpecification']['price'] ) ) {
			$markup['priceSpecification']['price'] = wc_format_decimal( max( 0, $regular_price ), wc_get_price_decimals() );
		}
		
		return $markup;
	}

	/**
	 * Get adjustment info for a product
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_adjustment_info( $product_id ) {
		$product_id = absint( $product_id );
		
		if ( $product_id <= 0 ) {
			return array(
				'has_adjustment'      => false,
				'adjustment_percentage' => 0,
				'message'             => '',
			);
		}

		$product = wc_get_product( $product_id );
		
		if ( ! $product || ! $product->managing_stock() ) {
			return array(
				'has_adjustment'      => false,
				'adjustment_percentage' => 0,
				'message'             => '',
			);
		}

		$stock_quantity = $product->get_stock_quantity();
		
		if ( null === $stock_quantity || $stock_quantity < 0 ) {
			return array(
				'has_adjustment'      => false,
				'adjustment_percentage' => 0,
				'message'             => '',
			);
		}

		$settings = $this->get_settings();
		$low_stock_threshold    = max( 0, intval( $settings['low_stock_threshold'] ) );
		$low_increase_pct       = max( 0, floatval( $settings['low_stock_price_increase'] ) );
		$medium_stock_threshold = max( 0, intval( $settings['medium_stock_threshold'] ) );
		$medium_increase_pct    = max( 0, floatval( $settings['medium_stock_price_increase'] ) );
		$high_stock_threshold   = max( 0, intval( $settings['high_stock_threshold'] ) );
		$high_decrease_pct      = max( 0, floatval( $settings['high_stock_price_decrease'] ) );

		$adjustment_percentage = 0;
		$message               = '';

		if ( $stock_quantity <= $low_stock_threshold ) {
			$adjustment_percentage = $low_increase_pct;
			/* translators: 1: adjustment percentage */
			$message = sprintf( __( 'Price increased by %d%% due to low stock', 'stockadaptix-pricing' ), $adjustment_percentage );
		} elseif ( $stock_quantity <= $medium_stock_threshold ) {
			$adjustment_percentage = $medium_increase_pct;
			/* translators: 1: adjustment percentage */
			$message = sprintf( __( 'Price increased by %d%% due to limited stock', 'stockadaptix-pricing' ), $adjustment_percentage );
		} elseif ( $stock_quantity >= $high_stock_threshold ) {
			$adjustment_percentage = -$high_decrease_pct;
			/* translators: 1: adjustment percentage */
			$message = sprintf( __( 'Price decreased by %d%% due to high stock', 'stockadaptix-pricing' ), abs( $adjustment_percentage ) );
		}

		return array(
			'has_adjustment'      => 0 !== $adjustment_percentage,
			'adjustment_percentage' => $adjustment_percentage,
			'message'             => $message,
		);
	}

	/**
	 * Add adjusted price as order item meta for HPOS compatibility
	 *
	 * @param WC_Order_Item_Product $item The order item object.
	 * @param string $cart_item_key The cart item key.
	 * @param array $values The cart item values.
	 * @param WC_Order $order The order object.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['dsp_adjusted_price'] ) ) {
			$item->add_meta_data( '_dsp_adjusted_price', $values['dsp_adjusted_price'], true );
			$item->add_meta_data( '_dsp_original_price', $values['dsp_original_price'], true );
		}
	}

	/**
	 * Adjust order item totals display
	 *
	 * @param array $total_rows Order item totals.
	 * @param WC_Order $order Order object.
	 * @param bool $tax_display Tax display setting.
	 * @return array
	 */
	public function adjust_order_item_totals( $total_rows, $order, $tax_display ) {
		// This method can be used to adjust how order totals are displayed if needed
		return $total_rows;
	}

	/**
	 * Check if HPOS (High-Performance Order Storage) is enabled
	 *
	 * @return bool
	 */
	private function is_hpos_enabled() {
		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}
		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Check if we're in an order processing context that should not have prices modified
	 *
	 * @return bool
	 */
	private function is_order_processing_context() {
		return doing_action( 'woocommerce_new_order' ) ||
			   doing_action( 'woocommerce_update_order' ) ||
			   doing_action( 'woocommerce_save_order' ) ||
			   doing_action( 'woocommerce_rest_insert_shop_order_object' ) ||
			   doing_action( 'woocommerce_gzd_shipment_created' );
	}

	/**
	 * Setup email compatibility when email is being sent
	 */
	public function setup_email_compatibility() {
		// In email contexts, temporarily disable price adjustments to show original prices
		// This ensures emails show the actual charged prices rather than dynamic adjustments
		remove_filter( 'woocommerce_product_get_price', array( $this, 'adjust_price' ), 10 );
		remove_filter( 'woocommerce_product_get_regular_price', array( $this, 'adjust_price' ), 10 );
		remove_filter( 'woocommerce_get_price_html', array( $this, 'adjust_price_html' ), 20 );
	}

	/**
	 * Adjust email order totals for email improvements compatibility
	 *
	 * @param string $total Formatted order total.
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function adjust_email_order_totals( $total, $order ) {
		// This method can be used to adjust how order totals are displayed in emails if needed
		return $total;
	}
}