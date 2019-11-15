<?php

$woo_nimiq_has_site_icon = !empty( get_site_icon_url() );
$woo_nimiq_has_https     = (!empty($_SERVER[ 'HTTPS' ]) && $_SERVER[ 'HTTPS' ] !== 'off') || $_SERVER[ 'SERVER_PORT' ] === 443;
$woo_nimiq_has_extension = function_exists('\gmp_init') || function_exists('\bcmul');
$woo_nimiq_has_fiat      = get_option( 'woocommerce_currency' ) !== 'NIM';

$woo_nimiq_no_extension_error = __( 'You must install & enable either the <code>php-bcmath</code> or <code>php-gmp</code> extension to accept %s with <strong>Nimiq Checkout for WooCommerce</strong>.', 'wc-gateway-nimiq' );

$woo_nimiq_redirect_behaviour_options = [ 'popup' => 'Popup' ];
if ( $woo_nimiq_has_https ) {
    $woo_nimiq_redirect_behaviour_options['redirect'] = 'Redirect';
}

// List available price services here. The option value must match the file name.
$woo_nimiq_price_services = [
    'coingecko' => 'Coingecko',
    // 'nimiqx'    => 'NimiqX (Nimiq only)',
];
$woo_nimiq_price_service_default = 'coingecko';
if ( in_array( get_option( 'woocommerce_currency' ), [ 'EUR', 'USD' ] ) ) {
    $woo_nimiq_price_services['fastspot'] = 'Fastspot (also estimates fees)';
    $woo_nimiq_price_service_default = 'fastspot';
}

$woo_nimiq_checkout_settings = [
    'shop_logo_url' => [
        'title'       => __( 'Shop Logo', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Display your logo during Nimiq Checkout by entering a URL to an image file here. ' .
                             'The file must be on the same domain as your webshop. ' .
                             'The image should be quadratic for best results.', 'wc-gateway-nimiq' ),
        'placeholder' => $woo_nimiq_has_site_icon
            ? __( 'Enter URL or leave empty to use your WordPress\'s site icon.', 'wc-gateway-nimiq' )
            : __( 'Enter URL to display your logo during checkout', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
        'class'       => $woo_nimiq_has_site_icon || !$woo_nimiq_has_fiat ? '' : 'required',
        'custom_attributes' => [
            'data-site-icon' => get_site_icon_url(),
        ],
    ],

    'instructions' => [
        'title'       => __( 'Email Instructions', 'wc-gateway-nimiq' ),
        'type'        => 'textarea',
        'description' => __( 'Instructions that will be added to the thank-you page and emails.', 'wc-gateway-nimiq' ),
        'default'     => __( 'You will receive email updates after your payment has been confirmed and when we shipped your order.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'section_nimiq' => [
        'title'       => 'Nimiq',
        'type'        => 'title',
        'description' => sprintf( __( 'All %s-related settings', 'wc-gateway-nimiq' ), 'Nimiq'),
        'class'       => 'section-nimiq',
    ],

    'nimiq_address' => [
        'title'       => __( 'Wallet Address', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Your Nimiq address where orders are paid to.', 'wc-gateway-nimiq' ),
        'placeholder' => 'NQ...',
        'desc_tip'    => true,
        'class'       => 'required',
    ],

    'message' => [
        'title'       => __( 'Transaction Message', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Enter a message that should be included in every transaction. 50 characters maximum.', 'wc-gateway-nimiq' ),
        'default'     => __( 'Thank you for shopping with us!', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'validation_service_nim' => [
        'title'       => __( 'Chain Monitoring Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service should be used for monitoring the Nimiq blockchain.', 'wc-gateway-nimiq' ),
        'default'     => 'nimiq_watch',
        'options'     => [
            // List available validation services here. The option value must match the file name.
            'nimiq_watch'  => 'NIMIQ.WATCH (testnet & mainnet)',
            'json_rpc_nim' => 'Nimiq JSON-RPC API',
            'nimiqx'       => 'NimiqX (mainnet)',
        ],
        'desc_tip'    => true,
    ],

    'jsonrpc_nimiq_url' => [
        'title'       => __( 'JSON-RPC URL', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'URL (including port) of the Nimiq JSON-RPC server used to monitor the Nimiq blockchain.', 'wc-gateway-nimiq' ),
        'default'     => 'http://localhost:8648',
        'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
        'class'       => 'required',
    ],

    'jsonrpc_nimiq_username' => [
        'title'       => __( 'JSON-RPC Username', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Username for the protected JSON-RPC service. (Optional)', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'jsonrpc_nimiq_password' => [
        'title'       => __( 'JSON-RPC Password', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Password for the protected JSON-RPC service. (Optional)', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'nimiqx_api_key' => [
        'title'       => __( 'NimiqX API Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Token for accessing the NimiqX exchange rate and chain monitoring service.', 'wc-gateway-nimiq' ),
        'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
        'class'       => 'required',
    ],

    'section_bitcoin' => [
        'title'       => 'Bitcoin',
        'type'        => 'title',
        'description' => $woo_nimiq_has_extension
            ? sprintf( __( 'All %s-related settings', 'wc-gateway-nimiq' ), 'Bitcoin')
            : sprintf( $woo_nimiq_no_extension_error, 'Bitcoin' ),
        'class'       => $woo_nimiq_has_extension ? 'section-bitcoin' : 'section-bitcoin-disabled',
    ],

    'bitcoin_xpub' => [
        'title'       => __( 'Wallet Master/Account Public Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Your Bitcoin xpub/zpub/tpub or "Master Public Key" from which payment addresses are derived.', 'wc-gateway-nimiq' ),
        'placeholder' => 'xpub...',
        'desc_tip'    => true,
    ],

    'validation_service_btc' => [
        'title'       => __( 'Chain Monitoring Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service should be used for monitoring the Bitcoin blockchain.', 'wc-gateway-nimiq' ),
        'default'     => 'blockstream',
        'options'     => [
            // List available validation services here. The option value must match the file name.
            'blockstream'  => 'Blockstream.info (testnet & mainnet)',
        ],
        'desc_tip'    => true,
    ],

    'section_ethereum' => [
        'title'       => 'Ethereum',
        'type'        => 'title',
        'description' => $woo_nimiq_has_extension
            ? sprintf( __( 'All %s-related settings', 'wc-gateway-nimiq' ), 'Ethereum')
            : sprintf( $woo_nimiq_no_extension_error, 'Ethereum' ),
        'class'       => $woo_nimiq_has_extension ? 'section-ethereum' : 'section-ethereum-disabled',
    ],

    'ethereum_xpub' => [
        'title'       => __( 'Wallet Account Public Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Your Ethereum xpub or "Account Public Key" from which payment addresses are derived.', 'wc-gateway-nimiq' ),
        'placeholder' => 'xpub...',
        'desc_tip'    => true,
    ],

    'reuse_eth_addresses' => [
        // 'title'       => '',
        'type'        => 'checkbox',
        'description' => __( 'Re-using addresses reduces your shop\'s privacy but gives you the comfort of having payments distributed over less addresses.', 'wc-gateway-nimiq' ),
        'label'       => __( 'Re-use Addresses', 'wc-gateway-nimiq' ),
        'default'     => 'no',
        // 'desc_tip'    => true,
    ],

    'validation_service_eth' => [
        'title'       => __( 'Chain Monitoring Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service should be used for monitoring the Ethereum blockchain.', 'wc-gateway-nimiq' ),
        'default'     => 'etherscan',
        'options'     => [
            // List available validation services here. The option value must match the file name.
            'etherscan'  => 'Etherscan.io (testnet & mainnet)',
        ],
        'desc_tip'    => true,
    ],

    'etherscan_api_key' => [
        'title'       => __( 'Etherscan.io API Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Token for accessing the Etherscan chain monitoring service.', 'wc-gateway-nimiq' ),
        'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
        'class'       => 'required',
    ],

    'section_advanced' => [
        'title'       => 'Advanced',
        'type'        => 'title',
        'description' => 'Settings for advanced users. Touch only when you know what you are doing.',
        'class'       => 'section-advanced'
    ],

    'network' => [
        'title'       => __( 'Network Mode', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which network to use: Testnet for testing, Mainnet when the shop is running live.', 'wc-gateway-nimiq' ),
        'default'     => 'main',
        'options'     => [ 'main' => 'Mainnet', 'test' => 'Testnet' ],
        'desc_tip'    => true,
    ],

    'price_service' => [
        'title'       => __( 'Exchange Rate service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service to use for fetching price information for currency conversion.', 'wc-gateway-nimiq' ),
        'default'     => $woo_nimiq_price_service_default,
        'options'     => $woo_nimiq_price_services,
        'desc_tip'    => true,
    ],

    'fee_nim' => [
        'title'       => __( 'NIM Fee per Byte [Luna]', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Lunas per byte to be applied to transactions.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d %s', 'wc-gateway-nimiq' ), 1, 'Luna' ),
        'desc_tip'    => true,
    ],

    'fee_btc' => [
        'title'       => __( 'BTC Fee per Byte [Sat]', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Satoshis per byte to be applied to transactions.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d %s', 'wc-gateway-nimiq' ), 40, 'Satoshi' ),
        'desc_tip'    => true,
    ],

    'fee_eth' => [
        'title'       => __( 'ETH Gas Price [Gwei]', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Gas price in Gwei to be applied to transactions.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d %s', 'wc-gateway-nimiq' ), 8, 'Gwei' ),
        'desc_tip'    => true,
    ],

    'margin' => [
        'title'       => __( 'Margin Percentage', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'A margin to apply to crypto payments, in percent. Can also be negative.', 'wc-gateway-nimiq' ),
        'placeholder' => 'Optional - Default: 0%',
        'desc_tip'    => true,
    ],

    'validation_interval' => [
        'title'       => __( 'Validation Interval [minutes]', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Interval to validate transactions, in minutes. If you change this, disable and enable this plugin to apply the new interval.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d minutes', 'wc-gateway-nimiq' ), 5 ),
        'desc_tip'    => true,
    ],

    'rpc_behavior' => [
        'title'       => __( 'Checkout Behavior', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'How the user should visit the Nimiq Checkout, as a popup or by being redirected.', 'wc-gateway-nimiq' ),
        'default'     => 'popup',
        'options'     => $woo_nimiq_redirect_behaviour_options,
        'desc_tip'    => true,
    ],

    'tx_wait_duration' => [
        'title'       => __( 'Transaction Wait Limit', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'How many minutes to wait for a transaction to be found, before considering the order to have failed.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d minutes', 'wc-gateway-nimiq' ), 120 ),
        'desc_tip'    => true,
    ],

    'confirmations_nim' => [
        'title'       => __( 'Required NIM confirmations', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'The number of confirmations required to accept a Nimiq transaction. Each confirmation takes 1 minute on average.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d blocks', 'wc-gateway-nimiq' ), 10 ),
        'desc_tip'    => true,
    ],

    'confirmations_btc' => [
        'title'       => __( 'Required BTC Confirmations', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'The number of confirmations required to accept a Bitcoin transaction. Each confirmation takes 10 minutes on average.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d blocks', 'wc-gateway-nimiq' ), 2 ),
        'desc_tip'    => true,
    ],

    'confirmations_eth' => [
        'title'       => __( 'Required ETH Confirmations', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'The number of confirmations required to accept an Ethereum transaction. Each confirmation takes 15 seconds on average.', 'wc-gateway-nimiq' ),
        'placeholder' => sprintf( __( 'Optional - Default: %d blocks', 'wc-gateway-nimiq' ), 45 ),
        'desc_tip'    => true,
    ],

    // 'current_address_index_btc' => [
    //     'title'       => __( '[BTC Address Index]', 'wc-gateway-nimiq' ),
    //     'type'        => 'number',
    //     'min'    => '-1',
    //     'description' => __( 'DO NOT CHANGE! The current BTC address derivation index.', 'wc-gateway-nimiq' ),
    //     'default'     => -1,
    //     'desc_tip'    => true,
    // ],

    // 'current_address_index_eth' => [
    //     'title'       => __( '[ETH Address Index]', 'wc-gateway-nimiq' ),
    //     'type'        => 'number',
    //     'min'    => '-1',
    //     'description' => __( 'DO NOT CHANGE! The current ETH address derivation index.', 'wc-gateway-nimiq' ),
    //     'default'     => -1,
    //     'desc_tip'    => true,
    // ],
];
