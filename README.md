# WooCommerce Nimiq Payment Gateway

This is a plugin for Wordpress WooCommerce, to handle payments in the Nimiq (NIM) cryptocurrency.

After an order is submitted via the Nimiq payment method, the order is placed "on-hold".
Transactions need to be validated manually with a *Validate Transactions* bulk action from the *Orders* admin page.
When a transaction is validated, the order status is set to "processing".

## Important note about currency conversion

**This plugin does not currently include automatic currency conversion and requires the currency of the webshop to be set to NIM!**
The currency setup is included in this plugin and NIM will be available to select under
"WooCommerce &gt; Settings &gt; General &gt; Currency options" after activating this plugin.

## Installation

1. Be sure you're running WooCommerce 3.0 or higher in your shop.
2. You can:
    (1) upload the [.zip file](https://github.com/nimiq/woocommerce-gateway-nimiq/archive/master.zip)
        with the plugin files under **Plugins &gt; Add New &gt; Upload** or
    (2) upload this entire `woocommerce-gateway-nimiq` repository to the `/wp-content/plugins/` directory on your server,
3. Activate the plugin through the **Plugins** menu in WordPress
4. Go to **WooCommerce &gt; Settings &gt; Payments** and select the "Nimiq" method to configure this plugin

**Can I improve and fork this?**
Please do! Issues and pull requests are very welcome! Or just adapt the plugin to your needs in a fork!

**What is the text domain for translations?**
The text domain is `wc-gateway-nimiq`.

## Meta

- Contributors: skyverge, beka.rice, nimiq
- Tags: woocommerce, payment gateway, gateway, nimiq, cryptocurrency
- Requires at least: 3.8
- Tested up to: 5.0
- Requires WooCommerce at least: 3.0
- Tested WooCommerce up to: 3.5
- Stable Tag: 2.0.0
- License: GPLv3
- License URI: http://www.gnu.org/licenses/gpl-3.0.html

## Changelog

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
- Adapted for payments with Nimiq

**2015.07.27 - version 1.0.1**
- Misc: WooCommerce 2.4 Compatibility

**2015.05.04 - version 1.0.0**
- Initial Release
