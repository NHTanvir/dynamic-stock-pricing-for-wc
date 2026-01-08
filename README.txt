=== StockAdaptix – Inventory-Driven Dynamic Pricing for WooCommerce ===
Contributors: tanvir26
Tags: woocommerce, dynamic pricing, stock management, inventory, pricing
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dynamically adjust WooCommerce product prices based on inventory levels to reflect real-time supply and demand.

== Description ==

StockAdaptix is an inventory-driven dynamic pricing plugin for WooCommerce that automatically adjusts product prices based on current stock levels. This allows store owners to respond to supply and demand changes in real time without manually updating prices.

Prices can increase when stock is low and decrease when inventory is high, helping maximize revenue and manage demand efficiently.

### Key Features
* Automatically adjust prices based on stock quantity
* Fully configurable stock thresholds and percentage adjustments
* Works with WooCommerce simple products using stock management
* Compatible with cart and checkout pricing
* Optional customer messaging for price changes
* Translation-ready
* HPOS (High-Performance Order Storage) compatible

### Example Pricing Rules (Configurable)
* If stock <= 5 → increase price by 40%
* If stock <= 20 → increase price by 20%
* If stock >= 100 → decrease price by 15%
* Otherwise → use the regular price

All rules and thresholds can be configured from **WooCommerce → Stock Pricing** in the admin panel.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/stockadaptix-pricing` directory, or install the plugin directly from the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Stock Pricing** to configure pricing rules.
4. Enable the plugin and set your desired stock thresholds and price adjustments.

== Frequently Asked Questions ==

= Which products are supported? =
Currently, the plugin supports WooCommerce simple products with stock management enabled.

= How do I customize the pricing rules? =
Navigate to **WooCommerce → Stock Pricing** in your WordPress admin to configure all thresholds and percentage adjustments.

= Does this affect the original product price? =
No. StockAdaptix dynamically adjusts displayed prices without modifying the original product prices stored in the database.

= Can customers see that prices are adjusted? =
Yes. You can optionally display a custom message informing customers that prices are adjusted based on availability.

= Is this compatible with other pricing plugins? =
Because this plugin modifies prices dynamically, it may conflict with other pricing plugins. We recommend testing compatibility before using them together.

= Does the plugin support variable products? =
Currently, only simple products are supported. Variable product support may be added in future releases.

== Screenshots ==
1. Stock-based pricing settings page

== Changelog ==

= 1.0.0 =
* Initial public release
