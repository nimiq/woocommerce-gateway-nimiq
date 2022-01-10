=== Cryptocurrency Checkout - Accept Bitcoin, Ethereum and Nimiq ===

Contributors: nimiq
Tags: woocommerce, cryptocurrency, crypto, checkout, gateway, nimiq, nim, bitcoin, btc, ethereum, eth
Requires at least: 4.9
Tested up to: 5.6
Requires WooCommerce at least: 3.5
Tested WooCommerce up to: 4.9
Stable tag: 3.4.0
Requires PHP: 7.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Receive crypto directly from your customers + easy integration + beautiful interface + no middleman + no fees.

== Description ==

Seamlessly integrate Bitcoin, Ethereum and Nimiq payments into your webshop. Receive the equivalent of your regular price in crypto directly in your wallet. Easy to integrate and free of charge. Beautifully designed and easy to use. Open source and decentralized.

**Features Include:**

* Bitcoin, Ethereum and Nimiq support
* Automatic conversion from supported store currencies like USD or EUR to crypto at latest market prices
* Full order status feedback in your WooCommerce panel
* Decentralized and non-proprietary
* Configurable conversion and validation service providers
* Configurable confirmation times with sensible defaults

_This Plugin is just getting started. Additional features such as instant and decentralized Crypto-to-Euro swaps are being currently developed._

= How Does It Work? =

1. The customer selects “Cryptocurrency Checkout” and is sent to the Nimiq Checkout page.
2. Cryptocurrency Checkout by Nimiq offers to take payments in Bitcoin, Ethereum or Nimiq.
3. The customer selects a cryptocurrency and pays. An order is created in the WooCommerce panel and set to 'on-hold' by the plugin.
4. As soon as the transaction is confirmed on the blockchain, the plugin automatically updates the order status.

= How To Integrate It? =

1. Install and activate the plugin
2. Find the **Cryptocurrency Checkout by Nimiq** in your list of plugins and click 'Settings'
3. Enter addresses and public keys of the currencies you want to accept
4. Tell your customers about your new payment option!

Check out the documentation, with a more in-depth [tutorial](https://nimiq.github.io/tutorials/wordpress-payment-plugin-installation).

= Where Does The Crypto Go? =

You provide your wallet addresses in the WooCommerce admin panel and receive the crypto directly from your customer.

If you are new to crypto, you can create a Bitcoin and Ethereum wallet with [Jaxx](https://jaxx.io) (a third-party application). For Nimiq, you don’t need to rely on third-parties and can create an address in just seconds and without the need to provide personal data, at [safe.nimiq.com](https://safe.nimiq.com).

= What Is Nimiq? =

Nimiq is a blockchain project, NIM is its cryptocurrency, designed for ease-of-use. Sending, receiving and storing NIM is as easy as using Facebook. Creating an account is even easier.

Give it a try: [nimiq.com](https://nimiq.com)

= Why Is This Plugin For Free? =

We believe that cryptocurrencies are the future and will provide a better, more democratic and more open form of money. With this Checkout Plugin, we want to provide a tool for everyone interested in crypto. By providing BTC and ETH payments together with NIM, we wish to support cryptocurrencies in general while illustrating just how much more easy and convenient Nimiq is.

== Screenshots ==

1. Easy to set up and maintain
2. Clean and straight forward payment settings
3. Seamlessly add cryptocurrency to your payment options
4. Payment Step 1: Chose your preferred currency
5. Payment Step 2: Get all relevant info at a glance
6. Payment Step 3: Pay using an app, QR code or manual inputs
7. Receive the payments straight to your wallet

== Changelog ==

= 3.4.0 - 2022.01.10 =

* Add support for Hub's acceptance of Nimiq payments from other wallets (for all three monitoring services)
* Auto-complete orders that do not require shipping, after payment was confirmed

= 3.3.2 - 2021.11.10 =

* Rename plugin to "Cryptocurrency Checkout by Nimiq"

= 3.3.1 - 2021.06.11 =

* Fix bug where fee suggestions were sometimes converted wrongly from coins to units

= 3.3.0 - 2021.01.25 =

* Update Fastspot price service to new API
* Add Dutch translations

= 3.2.2 - 2020.08.11 =

* Add Spanish translations

= 3.2.1 - 2020.07.06 =

* Add Chinese translations
* Update French translations

= 3.2.0 - 2020.06.22 =

* Display vendor markup (margin) in Checkout price tooltip for transparency
* Accept one decimal number for the margin setting
* Nimiq Hub compatibility update

= 3.1.4 - 2020.03.26 =

* Change to custom CSRF token generation, as WP nonces sometimes have problems validating
* Update HubApi to v1.2.1
* Tested with WooCommerce 4.0

= 3.1.3 - 2020.02.01 =

* Improve French translations

= 3.1.2 - 2020.01.17 =

* Fix transaction validation not working when no Etherscan API key was set

= 3.1.1 - 2019.12.16 =

* Fix popup overlay positioning on scrollable pages
* Fix endless loop for redirect payments from payment page

= 3.1.0 - 2019.12.07 =

* Add French translations
* Add overlay to shop page when Nimiq Hub popup is open

= 3.0.0 - 2019.11.22 =

* Now accepting Bitcoin, Ethereum and Nimiq payments!
* Huge usability improvements over previous versions.

== Upgrade Notice ==

= 3.4.0 =

Enable Hub support for Nimiq payments from other wallets: update to enable your users to pay with any NIM wallet.

= 3.3.1 =

Fix bug in calculation of fee suggestions that could sometimes show a too high fee suggestion.

= 3.3.0 =

Update Fastspot price service to new API (old API will soon be shut off). Update NOW if you are using Fastspot as your price service! Also added Dutch translations.

= 3.2.2 =

Added Spanish translations.

= 3.2.1 =

Added Chinese translations, update french.

= 3.2.0 =

Display vendor markup (margin) in Checkout price tooltip for transparency.

= 3.1.4 =

Fix a problem that sometimes prevented Nimiq Checkout from opening correctly.

= 3.1.3 =

Improved French translations.

= 3.1.2 =

Fixed transaction validation when no Etherscan API key is set.

= 3.1.1 =

Fixed popup overlay positioning and endless redirect payment loop.

= 3.1.0 =

Added French translations and an overlay when the Nimiq Hub popup is open.

= 3.0.0 =

Cryptocurrency Checkout by Nimiq now also supports accepting payments in Bitcoin and Ethereum! Update your settings to enable the new currencies.

== Acknowledgement ==

This Nimiq payment gateway is based on skyverge's [WooCommerce Offline Gateway](https://github.com/bekarice/woocommerce-gateway-offline), which in turn forks the WooCommerce core "Cheque" payment gateway.
