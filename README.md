# Nimiq Payment Gateway for WooCommerce

A plugin for Wordpress WooCommerce to handle payments in the Nimiq (NIM) cryptocurrency.

After an order is submitted via the Nimiq payment method, the order is placed "on-hold".
Transactions are validated automatically on a short interval, but can also be validated
manually with a *Validate Transactions* bulk action from the *Orders* admin page.
When a transaction is validated, the order status changes to "processing".

## Installation

1. Be sure you're running WooCommerce 3.0 or higher in your shop.
2. You can:
    (1) upload the [.zip file](https://github.com/nimiq/woocommerce-gateway-nimiq/archive/master.zip)
        with the plugin files under **Plugins &gt; Add New &gt; Upload** or
    (2) upload this entire `woocommerce-gateway-nimiq` repository to the `/wp-content/plugins/` directory on your server,
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Payments** and select the "Nimiq" method to configure this plugin

## Automatic Currency Conversion

This plugin supports automatically converting from your store currency to NIM
upon checkout. Here is a list of supported currencies for the available conversion
services:

- [NimiqX](https://api.nimiqx.com/price?api_key=210b34d0df702dd157d31f118ae00420)
- [Coingecko](https://api.coingecko.com/api/v3/simple/supported_vs_currencies)

## Adding a new validation service

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

## Meta

- Contributors: skyverge, beka.rice, nimiq
- Tags: woocommerce, payment gateway, gateway, nimiq, cryptocurrency
- Requires at least: 4.9
- Tested up to: 5.0
- Requires WooCommerce at least: 3.5
- Tested WooCommerce up to: 3.5
- Stable Tag: 2.6.0
- License: GPLv3
- License URI: http://www.gnu.org/licenses/gpl-3.0.html

**Can I improve and fork this?**
Please do! Issues and pull requests are very welcome! Or just adapt the plugin to your needs in a fork!

**What is the text domain for translations?**
The text domain is `wc-gateway-nimiq`.

## Changelog

**2019.05.23 - version 2.6.0**
- Enable mainnet option

**2019.05.17 - version 2.5.0**
- Bug fix for JS error
- Disable redirect option on non-SSL sites
- Allow using Basic Auth with a Nimiq RPC server
- Update to Nimiq Hub API RC5

**2019.03.22 - version 2.4.0**
- Update to new Nimiq Accounts API
- Add DPD carrier handling for tracking links

**2019.01.15 - version 2.3.0**
- Fix error displayed in front-end
- Add JSON-RPC as a validation service (thanks to @terorie)
- Add scheduled automatic transaction validation

**2019.01.11 - version 2.2.1**
- Add script to settings page with scaffolding for showing/hiding conditional fields

**2019.01.11 - version 2.2.0**
- Add setting for checkout behavior: popup or redirect
- Add setting for displaying an image (e.g. the shop's logo) during checkout
- Enable easy addition and configuration of transaction validation services

**2019.01.08 - version 2.1.0**
- Fix transaction message order hash detection to always match the *last* pair of round brackets
- Calculate fee from byte array length, instead of string length
- Use site title as appName in Accounts Manager request
- Clean up code and comments for public release

**2018.11.25 - version 2.0.0**
- Use new Nimiq Accounts checkout experience

**2018.11.15 - version 1.10.0**
- Add tracking details, such as carrier and tracking number, to "completed" emails
  (This should be moved to a separate plugin in the future, as this plugin should only be for the payment gateway.)

**2018.11.14 - version 1.9.2**
- Fix tx message not being displayed correctly in the Keyguard

**2018.10.22 - version 1.9.1**
- Update Nimiq logo

**2018.09.17 - version 1.9**
- Restock inventory when an order fails during bulk transaction-validation

**2018.09.17 - version 1.8.2**
- Display hint instead of empty drop-down when no accounts are available on the device

**2018.06.27 - version 1.8.1**
- Fix transaction validation bug
- Fix function access bug from bulk action

**2018.06.20 - version 1.8.0**
- Add transaction fee setting
- Improve setting naming
- Update keyguard and network clients

**2018.06.20 - version 1.7.0**
- Replace order ID with abbreviated order hash in transaction message
- Prevent users from having to accept T&C on pay-order page again
- Fix display of muliple transaction validation errors in admin backend
- Internal file reorganization

**2018.06.08 - version 1.6.0**
- Handle Nimiq payment on separate page, after order has been placed
- Add bulk transaction validation action on 'Orders' page

**2018.06.07 - version 1.5.0**
- Add "Payment complete" message

**2018.06.07 - version 1.4.0**
- Fix network selector setting values
- Fix checkout form multi submissions
- Store transaction hashes in HEX format

**2018.06.07 - version 1.3.0**
- Fix network-client urls

**2018.06.06 - version 1.2.2**
- Fix checkout icon
- Remove coupon form hiding code

**2018.06.06 - version 1.2.0**
- Made network and message configurable
- Prepare for online deployment

**2018.06.05 - version 1.1.0**
- Adapted [plugin](https://github.com/bekarice/woocommerce-gateway-offline) for payments with Nimiq

## Legal acknowledgement
This Nimiq gateway is based on skyverge's [WooCommerce Offline Gateway](https://github.com/bekarice/woocommerce-gateway-offline),
which in turn forks the WooCommerce core "Cheque" payment gateway.

