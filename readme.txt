=== Nimiq Payments for WooCommerce ===

Contributors: nimiq, skyverge, beka.rice
Tags: woocommerce, payment gateway, gateway, nimiq, cryptocurrency
Requires at least: 4.9
Tested up to: 5.2
Requires WooCommerce at least: 3.5
Tested WooCommerce up to: 3.6
Stable tag: v2.7.1
Requires PHP: 5.2.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A plugin for Wordpress WooCommerce to handle payments in the Nimiq (NIM) cryptocurrency.

== Description ==

A plugin for Wordpress WooCommerce to handle payments in the Nimiq (NIM) cryptocurrency.

Features include:

* Automatic currency conversion from supported store currencies to NIM during checkout
* Automatic transaction validation and WooCommerce order status updates
* Configurable conversion and validation service providers
* Configurable confirmation times with sane defaults
* Includes the NIM currency for WooCommerce

= Automatic Currency Conversion =

This plugin can automatically convert from your store currency to NIM during checkout.
Here is a list of supported currencies for the included conversion services:

* [NimiqX](https://api.nimiqx.com/price?api_key=210b34d0df702dd157d31f118ae00420)
* [Coingecko](https://api.coingecko.com/api/v3/simple/supported_vs_currencies)

= Order Status Updates =

After an order is submitted using the Nimiq payment method, the order is placed "on-hold".
Transactions are validated automatically on a short interval, and can also be validated
manually with a *Validate Transactions* bulk action from the *Orders* admin page.
When a transaction is validated, the order status changes to "processing".

== Installation ==

1. Be sure you're running WooCommerce 3.5 or higher in your shop.
2. Upload the [latest release .zip file](https://github.com/nimiq/woocommerce-gateway-nimiq/releases)
   with the plugin files under **Plugins &gt; Add New &gt; Upload**.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Go to **WooCommerce &gt; Settings &gt; Payments** and select the "Nimiq" method
   to configure this plugin.

== Changelog ==

= 2.7.1 - 2019.06.13 =
* Fix wrong variable name preventing use of plugin outside of nimiq.com and nimiq-testnet.com
* Remove package tracking code
* Normalize tested WP and WC version numbers between readme and plugin file

= 2.7.0 - 2019.06.05 =

* Enable automatic currency conversion to NIM on checkout
* Make transaction validation interval configurable

= 2.6.0 - 2019.05.23 =

* Update to Nimiq Hub API 1.0.0
* Enable mainnet option

= 2.5.0 - 2019.05.17 =

* Bug fix for JS error
* Disable redirect option on non-SSL sites
* Allow using Basic Auth with a Nimiq RPC server
* Update to Nimiq Hub API RC5

= 2.4.0 - 2019.03.22 =

* Update to new Nimiq Accounts API
* Add DPD carrier handling for tracking links

= 2.3.0 - 2019.01.15 =

* Fix error displayed in front-end
* Add JSON-RPC as a validation service (thanks to @terorie)
* Add scheduled automatic transaction validation

= 2.2.1 - 2019.01.11 =

* Add script to settings page with scaffolding for showing/hiding conditional fields

= 2.2.0 - 2019.01.11 =

* Add setting for checkout behavior: popup or redirect
* Add setting for displaying an image (e.g. the shop's logo) during checkout
* Enable easy addition and configuration of transaction validation services

= 2.1.0 - 2019.01.08 =

* Fix transaction message order hash detection to always match the *last* pair of
  round brackets
* Calculate fee from byte array length, instead of string length
* Use site title as appName in Accounts Manager request
* Clean up code and comments for public release

= 2.0.0 - 2018.11.25 =

* Use new Nimiq Accounts checkout experience

= 1.10.0 - 2018.11.15 =

* Add tracking details, such as carrier and tracking number, to "completed" emails
  (This should be moved to a separate plugin in the future, as this plugin should
  only be for the payment gateway.)

= 1.9.2 - 2018.11.14 =

* Fix tx message not being displayed correctly in the Keyguard

= 1.9.1 - 2018.10.22 =

* Update Nimiq logo

= 1.9 - 2018.09.17 =

* Restock inventory when an order fails during bulk transaction-validation

= 1.8.2 - 2018.09.17 =

* Display hint instead of empty drop-down when no accounts are available on the device

= 1.8.1 - 2018.06.27 =

* Fix transaction validation bug
* Fix function access bug from bulk action

= 1.8.0 - 2018.06.20 =

* Add transaction fee setting
* Improve setting naming
* Update keyguard and network clients

= 1.7.0 - 2018.06.20 =

* Replace order ID with abbreviated order hash in transaction message
* Prevent users from having to accept T&C on pay-order page again
* Fix display of muliple transaction validation errors in admin backend
* Internal file reorganization

= 1.6.0 - 2018.06.08 =

* Handle Nimiq payment on separate page, after order has been placed
* Add bulk transaction validation action on 'Orders' page

= 1.5.0 - 2018.06.07 =

* Add "Payment complete" message

= 1.4.0 - 2018.06.07 =

* Fix network selector setting values
* Fix checkout form multi submissions
* Store transaction hashes in HEX format

= 1.3.0 - 2018.06.07 =

* Fix network-client urls

= 1.2.2 - 2018.06.06 =

* Fix checkout icon
* Remove coupon form hiding code

= 1.2.0 - 2018.06.06 =

* Made network and message configurable
* Prepare for online deployment

= 1.1.0 - 2018.06.05 =

* Adapted [plugin](https://github.com/bekarice/woocommerce-gateway-offline) for payments
  with Nimiq

== Upgrade Notice ==

= 2.7.0 =

This version adds the ability to automatically convert from the store currency to
NIM during checkout.

== Development ==

= Adding A New Validation Service =

Validation services are defined under [`./validation_services/`](./validation_services/).
Each service class must implement the `WC_Gateway_Nimiq_Service_Interface`, defined
in [`./validation_services/interface.php`](./validation_services/interface.php).
The easiest way to start is to take an existing service (e.g.
[`nimiq_watch.php`](./validation_services/nimiq_watch.php)) and rename and adapt
it to the new service.
The new service then also needs to be registered in the `validation_service` setting.
The value of the setting must match the file name (without the `.php` extension)
of the service definition.
If the new service requires additional setting fields, [`settings.js`](./js/settings.js)
also needs to be adapted to show/hide those fields conditionally.

== Legal Acknowledgement ==

This Nimiq gateway is based on skyverge's [WooCommerce Offline Gateway](https://github.com/bekarice/woocommerce-gateway-offline),
which in turn forks the WooCommerce core "Cheque" payment gateway.
