<?php
namespace DynamicStockPricing\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer Messaging Module
 * Handles displaying messages to customers about price adjustments
 */
class CustomerMessagingModule {

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
        // Display messages on product pages
        add_action('woocommerce_single_product_summary', array($this, 'display_adjustment_message'), 25);
        
        // Add message to cart items
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_message'), 10, 2);
        
        // Add message to order items
        add_filter('woocommerce_order_item_display_meta_key', array($this, 'display_order_message'), 10, 3);
    }

    /**
     * Display adjustment message on product page
     */
    public function display_adjustment_message() {
        global $product;

        if (!$this->is_enabled() || !is_object($product) || !$product->is_type('simple') || !$product->managing_stock()) {
            return;
        }

        $stock_quantity = $product->get_stock_quantity();
        
        if ($stock_quantity === null || $stock_quantity < 0) {
            return; // Stock not managed
        }

        $settings = $this->get_settings();
        
        // Check if this product qualifies for price adjustment
        $low_stock_threshold = intval($settings['low_stock_threshold']);
        $medium_stock_threshold = intval($settings['medium_stock_threshold']);
        $high_stock_threshold = intval($settings['high_stock_threshold']);

        $needs_message = false;

        if ($stock_quantity <= $low_stock_threshold || 
            $stock_quantity <= $medium_stock_threshold || 
            $stock_quantity >= $high_stock_threshold) {
            $needs_message = true;
        }

        if ($needs_message && $settings['customer_message_enabled']) {
            $message = $settings['customer_message'];
            echo '<div class="stock-price-adjustment-message">' . esc_html($message) . '</div>';
        }
    }

    /**
     * Display message in cart
     */
    public function display_cart_message($item_data, $cart_item) {
        if (!$this->is_enabled()) {
            return $item_data;
        }

        $product = $cart_item['data'];

        if (!$product->is_type('simple') || !$product->managing_stock()) {
            return $item_data;
        }

        $stock_quantity = $product->get_stock_quantity();
        
        if ($stock_quantity === null || $stock_quantity < 0) {
            return $item_data; // Stock not managed
        }

        $settings = $this->get_settings();
        
        // Check if this product qualifies for price adjustment
        $low_stock_threshold = intval($settings['low_stock_threshold']);
        $medium_stock_threshold = intval($settings['medium_stock_threshold']);
        $high_stock_threshold = intval($settings['high_stock_threshold']);

        $needs_message = false;

        if ($stock_quantity <= $low_stock_threshold || 
            $stock_quantity <= $medium_stock_threshold || 
            $stock_quantity >= $high_stock_threshold) {
            $needs_message = true;
        }

        if ($needs_message && $settings['customer_message_enabled']) {
            $item_data[] = array(
                'key'     => __('Dynamic Pricing Notice', 'dynamic-stock-pricing'),
                'value'   => esc_html($settings['customer_message']),
                'display' => ''
            );
        }

        return $item_data;
    }

    /**
     * Display message in order
     */
    public function display_order_message($display_key, $meta, $order_item) {
        if ($meta->key === '_dynamic_pricing_notice' && $this->is_enabled()) {
            return __('Dynamic Pricing Notice', 'dynamic-stock-pricing');
        }
        return $display_key;
    }

    /**
     * Get plugin settings
     */
    private function get_settings() {
        $defaults = array(
            'enable_plugin' => 1,
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
}