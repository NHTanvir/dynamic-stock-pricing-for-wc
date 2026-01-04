# StockAdaptix Pricing for WooCommerce

**Contributors:** tanvir26
**Tags:** woocommerce, dynamic pricing, stock management, inventory, pricing
**Requires at least:** 5.0
**Tested up to:** 6.9
**WC requires at least:** 5.0
**WC tested up to:** 8.0
**Stable tag:** 1.0.0
**License:** GPLv2 or later
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

Dynamically adjust product prices based on current stock quantity to reflect supply and demand in real-time.

## Description

The StockAdaptix Pricing for WooCommerce plugin allows you to automatically adjust product prices based on current inventory levels. This helps you respond to supply and demand fluctuations in real-time without manual price changes.

### Key Features:
* Automatically adjust prices based on stock quantities
* Configurable thresholds and percentage adjustments
* Works with simple products and WooCommerce stock management
* Compatible with cart and checkout processes
* Optional customer messaging about price adjustments
* Translation-ready
* HPOS (High-Performance Order Storage) compatible

### Pricing Rules (Configurable):
* If stock ≤ 5 → increase price by 40%
* If stock ≤ 20 → increase price by 20%
* If stock ≥ 100 → decrease price by 15%
* Otherwise → use normal price

All thresholds and percentage adjustments can be configured in the admin settings under WooCommerce > Stock Pricing.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/stockadaptix-pricing` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Stock Pricing to configure settings
4. Enable the plugin and set your desired thresholds and percentage adjustments

## Frequently Asked Questions

### Which products are supported?
Currently, the plugin works with simple products that have stock management enabled in WooCommerce.

### How do I customize the pricing rules?
Go to WooCommerce > Stock Pricing in your WordPress admin to configure all pricing thresholds and percentage adjustments.

### Does this affect the original product price?
No, the plugin only displays adjusted prices based on stock levels without modifying your original product prices in the database.

### Can customers see that prices are adjusted?
Yes, optionally you can display a custom message to customers explaining that prices have been adjusted based on availability.

### Is this compatible with other pricing plugins?
This plugin modifies product pricing based on stock levels, so it may conflict with other plugins that directly alter product prices. We recommend testing compatibility with other pricing plugins before using them together.

### Does the plugin work with variable products?
Currently, the plugin only supports simple products with stock management enabled. Support for variable products may be added in future versions.

## Changelog

### 1.0.0
* Initial release