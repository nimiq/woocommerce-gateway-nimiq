# Nimiq Cryptocurrency Checkout for WooCommerce

Contributors: nimiq
Tags: woocommerce, cryptocurrency, crypto, checkout, gateway, nimiq, nim, bitcoin, btc, ethereum, eth,
Requires at least: 4.9
Tested up to: 5.3
Requires WooCommerce at least: 3.5
Tested WooCommerce up to: 3.8
Stable tag: v2.7.4
Requires PHP: 7.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A plugin for Wordpress to handle WooCommerce payments in the Nimiq (NIM), Bitcoin (BTC), and Ethereum (ETH) cryptocurrency.

## Description

A plugin for Wordpress to handle WooCommerce payments in the Nimiq (NIM), Bitcoin (BTC), and Ethereum (ETH) cryptocurrency.

Features include:

* Automatic currency conversion from supported store currencies to NIM, BTC, and ETH during checkout
* Automatic transaction validation and WooCommerce order status updates
* Configurable conversion and validation service providers
* Configurable confirmation times with reasonable defaults

[Check this in-depth tutorial for support on setting up the Nimiq Cryptocurrency Checkout](https://nimiq.github.io/tutorials/wordpress-payment-plugin-installation)

## Installation

1. Be sure you're running WooCommerce 3.5 or higher in your shop.
2. Upload the [latest release .zip file](https://github.com/nimiq/woocommerce-gateway-nimiq/releases) with the plugin files under **Plugins &gt; Add New &gt; Upload**.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Go to **WooCommerce &gt; Settings &gt; Payments** and select the "Nimiq" method to configure this plugin.

[Check this in-depth tutorial for details and help](https://nimiq.github.io/tutorials/wordpress-payment-plugin-installation)

## Changelog

Please see the Changelog section in [readme.txt](readme.txt).

## Development

### Adding A New Validation Service

Validation services are defined under [`./validation_services/`](./validation_services/). Each service class must implement the `WC_Gateway_Nimiq_Validation_Service_Interface`, defined in [`./validation_services/interface.php`](./validation_services/interface.php). The easiest way to start is to take an existing service (e.g. [`nimiq_watch.php`](./validation_services/nimiq_watch.php)) and rename and adapt it to the new service. The new service then also needs to be registered in the respective `validation_service_<currency>` setting. The value of the setting must match the file name (without the `.php` extension) of the service definition. If the new service requires additional setting fields, [`settings.js`](./js/settings.js) also needs to be adapted to show/hide those fields conditionally.

## Acknowledgement

This Nimiq gateway is based on skyverge's [WooCommerce Offline Gateway](https://github.com/bekarice/woocommerce-gateway-offline), which in turn forks the WooCommerce core "Cheque" payment gateway.
