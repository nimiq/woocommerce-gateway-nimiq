=== Nimiq Cryptocurrency Checkout for WooCommerce ===

Contributors: nimiq
Tags: woocommerce, payment gateway, checkout, gateway, nimiq, nim, btc, bitcoin, eth, ethereum, crypto, cryptocurrency
Requires at least: 4.9
Tested up to: 5.2
Requires WooCommerce at least: 3.5
Tested WooCommerce up to: 3.8
Stable tag: v2.7.4
Requires PHP: 7.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A plugin for Wordpress WooCommerce to handle payments in the Nimiq (NIM), Bitcoin (BTC), and Ethereum (ETH) cryptocurrency.

== Description ==

A plugin for Wordpress WooCommerce to handle payments in the Nimiq (NIM), Bitcoin (BTC), and Ethereum (ETH) cryptocurrency.

Features include:

* Automatic currency conversion from supported store currencies to NIM, BTC, and ETH during checkout
* Automatic transaction validation and WooCommerce order status updates
* Configurable conversion and validation service providers
* Configurable confirmation times with reasonable defaults

[Check this in-depth tutorial for support on setting up the Nimiq Cryptocurrency Checkout](https://nimiq.github.io/tutorials/wordpress-payment-plugin-installation)


= Automatic Currency Conversion =

This plugin can automatically convert from your store currency to NIM, BTC, and ETH during checkout. Here is a list of supported currencies for the included conversion services:

* [NimiqX](https://api.nimiqx.com/price?api_key=210b34d0df702dd157d31f118ae00420)
* [Coingecko](https://api.coingecko.com/api/v3/simple/supported_vs_currencies)

= Order Status Updates =

After an order is submitted and payment completed, the order is placed "on-hold". Transactions are validated automatically on a short interval, and can also be validated manually with a *Validate Transactions* bulk action from the *Orders* admin page. When a transaction is validated, the order status changes to "processing".

== Installation ==

1. Be sure you're running WooCommerce 3.5 or higher in your shop.
2. Upload the [latest release .zip file](https://github.com/nimiq/woocommerce-gateway-nimiq/releases) with the plugin files under **Plugins &gt; Add New &gt; Upload**.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Go to **WooCommerce &gt; Settings &gt; Payments** and select the "Nimiq" method to configure this plugin.

[Check this in-depth tutorial for details and help](https://nimiq.github.io/tutorials/wordpress-payment-plugin-installation)

== Changelog ==

= 3.0.0 - 2019.11.xx =

* Add support for Bitcoin and Ethereum payments
* Remove other payment options from payment page
* Various smaller fixes

== Upgrade Notice ==

= 3.0.0 =

Nimiq Checkout for WooCommerce now supports taking payments in Bitcoin and Ethereum!

== Development ==

= Adding A New Validation Service =

Validation services are defined under [`./validation_services/`](./validation_services/). Each service class must implement the `WC_Gateway_Nimiq_Validation_Service_Interface`, defined in [`./validation_services/interface.php`](./validation_services/interface.php). The easiest way to start is to take an existing service (e.g. [`nimiq_watch.php`](./validation_services/nimiq_watch.php)) and rename and adapt it to the new service. The new service then also needs to be registered in the respective `validation_service_<currency>` setting. The value of the setting must match the file name (without the `.php` extension) of the service definition. If the new service requires additional setting fields, [`settings.js`](./js/settings.js) also needs to be adapted to show/hide those fields conditionally.

== Acknowledgement ==

This Nimiq gateway is based on skyverge's [WooCommerce Offline Gateway](https://github.com/bekarice/woocommerce-gateway-offline), which in turn forks the WooCommerce core "Cheque" payment gateway.
