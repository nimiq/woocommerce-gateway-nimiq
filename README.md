=== WooCommerce Nimiq Gateway ===

 - Contributors: skyverge, beka.rice, nimiq
 - Tags: woocommerce, payment gateway, gateway, nimiq, cryptocurrency
 - Requires at least: 3.8
 - Tested up to: 4.3
 - Requires WooCommerce at least: 3.0
 - Tested WooCommerce up to: 3.4
 - Stable Tag: 1.2.1
 - License: GPLv3
 - License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

> **Requires: WooCommerce 3.0+**

...

When an order is submitted via the Nimiq payment method, the order will be placed "on-hold".

= More Details =
 - See the [product page](http://www.skyverge.com/product/woocommerce-offline-gateway/) for full details and documentation

== Installation ==

1. Be sure you're running WooCommerce 3.0+ in your shop.
2. You can: (1) upload the entire `woocommerce-gateway-nimiq` folder to the `/wp-content/plugins/` directory, (2) upload the .zip file with the plugin under **Plugins &gt; Add New &gt; Upload**
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Checkout** and select "Nimiq" to configure

== Frequently Asked Questions ==

**What is the text domain for translations?**
The text domain is `wc-gateway-nimiq`.

**Can I fork this?**
Please do! This is meant to be a simple starter Nimiq gateway, and can be modified easily.

== Changelog ==

= 2018.06.06 - version 1.2.1 =
 * Fix checkout icon
 * Remove coupon form hiding code

= 2018.06.06 - version 1.2.0 =
 * Made network and message configurable
 * Prepare for online deployment

= 2018.06.05 - version 1.1.0 =
 * Adapted for payments with Nimiq

= 2015.07.27 - version 1.0.1 =
 * Misc: WooCommerce 2.4 Compatibility

= 2015.05.04 - version 1.0.0 =
 * Initial Release
