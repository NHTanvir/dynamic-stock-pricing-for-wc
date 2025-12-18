<?php
namespace DynamicStockPricing\Services;

if (!defined('ABSPATH')) {
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
        // Apply pricing adjustments
        add_filter('woocommerce_product_get_price', array($this, 'adjust_price'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'adjust_price'), 10, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'adjust_price'), 10, 2);
        
        // Handle cart pricing
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 20, 2);
        add_action('woocommerce_calculate_totals', array($this, 'calculate_totals'), 20);
        
        
        // Compatibility with WooCommerce structured data for schema markup
        add_filter('woocommerce_structured_data_product_offer', array($this, 'structured_data_price'), 10, 2);
    }

    /**
     * Get plugin settings
     */
    private function get_settings() {
        $defaults = array(
            'enable_plugin' => 1,
            'low_stock_threshold' => 5,
            'low_stock_price_increase' => 40,
            'medium_stock_threshold' => 20,
            'medium_stock_price_increase' => 20,
            'high_stock_threshold' => 100,
            'high_stock_price_decrease' => 15,
            'customer_message_enabled' => 1,
            'customer_message' => __('High demand – price adjusted based on availability', 'dynamic-stock-pricing')
        );

        $settings = get_option('dynamic_stock_pricing_settings', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Check if plugin is enabled
     */
    private function is_enabled() {
        $settings = $this->get_settings();
        return (bool)$settings['enable_plugin'];
    }

    /**
     * Calculate adjusted price based on stock
     */
    public function calculate_adjusted_price($product_id, $original_price) {
        if (!$this->is_enabled()) {
            return $original_price;
        }

        $product_id = absint($product_id);
        $original_price = floatval($original_price);

        // Validate inputs
        if ($product_id <= 0 || $original_price < 0) {
            return $original_price;
        }

        $product = wc_get_product($product_id);

        // Only apply to products with stock management enabled
        if (!$product || !$product->managing_stock()) {
            return $original_price;
        }

        $stock_quantity = $product->get_stock_quantity();

        // If stock is not managed or not set, return original price
        if ($stock_quantity === null || $stock_quantity < 0) {
            return $original_price;
        }

        $settings = $this->get_settings();

        // Get thresholds and adjustments with proper sanitization
        $low_stock_threshold = max(0, intval($settings['low_stock_threshold']));
        $low_increase_pct = max(0, floatval($settings['low_stock_price_increase']));
        $medium_stock_threshold = max(0, intval($settings['medium_stock_threshold']));
        $medium_increase_pct = max(0, floatval($settings['medium_stock_price_increase']));
        $high_stock_threshold = max(0, intval($settings['high_stock_threshold']));
        $high_decrease_pct = max(0, floatval($settings['high_stock_price_decrease']));

        $adjustment_factor = 0; // Default: no adjustment

        if ($stock_quantity <= $low_stock_threshold) {
            // If stock <= low threshold, increase price by low_stock_price_increase%
            $adjustment_factor = $low_increase_pct / 100;
        } elseif ($stock_quantity <= $medium_stock_threshold) {
            // If stock <= medium threshold, increase price by medium_stock_price_increase%
            $adjustment_factor = $medium_increase_pct / 100;
        } elseif ($stock_quantity >= $high_stock_threshold) {
            // If stock >= high threshold, decrease price by high_stock_price_decrease%
            $adjustment_factor = -$high_decrease_pct / 100;
        }
        // Otherwise, adjustment_factor remains 0 (no change)

        // Calculate new price
        $adjusted_price = $original_price * (1 + $adjustment_factor);

        // Ensure price doesn't become negative
        return max(0, $adjusted_price);
    }

    /**
     * Adjust product price based on stock
     */
    public function adjust_price($price, $product) {
        // Only apply adjustments if plugin is enabled and product exists
        if (!$this->is_enabled() || !$product instanceof WC_Product) {
            return $price;
        }

        // Only apply to simple products with stock management enabled
        if ($product->get_type() !== 'simple' || !$product->managing_stock()) {
            return $price;
        }

        $product_id = $product->get_id();
        $original_price = floatval($price);

        return $this->calculate_adjusted_price($product_id, $original_price);
    }

    /**
     * Add custom data to cart item
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!$this->is_enabled()) {
            return $cart_item_data;
        }

        $product_id = absint($product_id);
        $variation_id = absint($variation_id);

        if ($product_id <= 0) {
            return $cart_item_data;
        }

        $the_product_id = $variation_id > 0 ? $variation_id : $product_id;
        $product = wc_get_product($the_product_id);

        if ($product && $product->managing_stock()) {
            $original_price = floatval($product->get_price());
            $adjusted_price = $this->calculate_adjusted_price($the_product_id, $original_price);

            if ($adjusted_price != $original_price) {
                $cart_item_data['dsp_adjusted_price'] = $adjusted_price;
                $cart_item_data['dsp_original_price'] = $original_price;

                // Generate unique hash to prevent merging of items with different prices
                $cart_item_data['dsp_unique_key'] = md5(microtime().rand());
            }
        }

        return $cart_item_data;
    }

    /**
     * Get cart item from session with adjusted price
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['dsp_adjusted_price'])) {
            $adjusted_price = floatval($values['dsp_adjusted_price']);
            $original_price = isset($values['dsp_original_price']) ? floatval($values['dsp_original_price']) : 0;

            // Validate the prices before applying
            if ($adjusted_price >= 0) {
                $cart_item['data']->set_price($adjusted_price);
                $cart_item['dsp_adjusted_price'] = $adjusted_price;
                $cart_item['dsp_original_price'] = $original_price;
            }
        }
        return $cart_item;
    }

    /**
     * Recalculate totals to account for adjusted prices
     */
    public function calculate_totals($cart) {
        // This is called after cart calculation, the prices are already adjusted through the product objects
        // This method can be extended if additional adjustment calculations are needed
    }


    /**
     * Adjust structured data (schema markup) for price
     */
    public function structured_data_price($markup, $product) {
        if (!$this->is_enabled() || !is_object($product)) {
            return $markup;
        }

        $product_id = absint($product->get_id());

        if ($product_id <= 0) {
            return $markup;
        }

        $regular_price = $this->calculate_adjusted_price($product_id, floatval($product->get_regular_price()));
        $sale_price = $product->get_sale_price();

        if ($sale_price) {
            $sale_price = $this->calculate_adjusted_price($product_id, floatval($sale_price));
        }

        // Update the structured data with adjusted prices
        if (isset($markup['price'])) {
            $markup['price'] = wc_format_decimal(max(0, $regular_price), wc_get_price_decimals());
        }

        if (isset($markup['priceSpecification']['price'])) {
            $markup['priceSpecification']['price'] = wc_format_decimal(max(0, $regular_price), wc_get_price_decimals());
        }

        return $markup;
    }

    /**
     * Get adjustment info for a product
     */
    public function get_adjustment_info($product_id) {
        $product_id = absint($product_id);

        if ($product_id <= 0) {
            return array(
                'has_adjustment' => false,
                'adjustment_percentage' => 0,
                'message' => ''
            );
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->managing_stock()) {
            return array(
                'has_adjustment' => false,
                'adjustment_percentage' => 0,
                'message' => ''
            );
        }

        $stock_quantity = $product->get_stock_quantity();

        if ($stock_quantity === null || $stock_quantity < 0) {
            return array(
                'has_adjustment' => false,
                'adjustment_percentage' => 0,
                'message' => ''
            );
        }

        $settings = $this->get_settings();
        $low_stock_threshold = max(0, intval($settings['low_stock_threshold']));
        $low_increase_pct = max(0, floatval($settings['low_stock_price_increase']));
        $medium_stock_threshold = max(0, intval($settings['medium_stock_threshold']));
        $medium_increase_pct = max(0, floatval($settings['medium_stock_price_increase']));
        $high_stock_threshold = max(0, intval($settings['high_stock_threshold']));
        $high_decrease_pct = max(0, floatval($settings['high_stock_price_decrease']));

        $adjustment_percentage = 0;
        $message = '';

        if ($stock_quantity <= $low_stock_threshold) {
            $adjustment_percentage = $low_increase_pct;
            /* translators: 1: adjustment percentage */
            $message = sprintf(__('Price increased by %d%% due to low stock', 'dynamic-stock-pricing'), $adjustment_percentage);
        } elseif ($stock_quantity <= $medium_stock_threshold) {
            $adjustment_percentage = $medium_increase_pct;
            /* translators: 1: adjustment percentage */
            $message = sprintf(__('Price increased by %d%% due to limited stock', 'dynamic-stock-pricing'), $adjustment_percentage);
        } elseif ($stock_quantity >= $high_stock_threshold) {
            $adjustment_percentage = -$high_decrease_pct;
            /* translators: 1: adjustment percentage */
            $message = sprintf(__('Price decreased by %d%% due to high stock', 'dynamic-stock-pricing'), abs($adjustment_percentage));
        }

        return array(
            'has_adjustment' => $adjustment_percentage !== 0,
            'adjustment_percentage' => $adjustment_percentage,
            'message' => $message
        );
    }
}