<?php

$redirect_behaviour_options = [
    'popup' => 'Popup'
];

if ( array_key_exists( 'HTTPS', $_SERVER ) && $_SERVER[ 'HTTPS' ] === 'on' ) {
    $redirect_behaviour_options['redirect'] = 'Redirect';
}

$woo_nimiq_checkout_settings = [
    'enabled' => [
        'title'   => __( 'Enable/Disable', 'wc-gateway-nimiq' ),
        'type'    => 'checkbox',
        'label'   => __( 'Enable Nimiq Crypto Checkout', 'wc-gateway-nimiq' ),
        'default' => 'yes'
    ],

    'network' => [
        'title'       => __( 'Nimiq Network', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which network to use. Use the Testnet for testing.', 'wc-gateway-nimiq' ),
        'default'     => 'test',
        'options'     => [ /*'main' => 'Mainnet', */'test' => 'Testnet' ],
        'desc_tip'    => true,
    ],

    'network_btc_eth' => [
        'title'       => __( 'BTC/ETH Network', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which network to use. Use the Testnet for testing.', 'wc-gateway-nimiq' ),
        'default'     => 'main',
        'options'     => [ 'main' => 'Mainnet', 'test' => 'Testnet' ],
        'desc_tip'    => true,
    ],

    'nimiq_address' => [
        'title'       => __( 'Shop NIM Address', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Your Nimiq address where customers will send their transactions to.', 'wc-gateway-nimiq' ),
        'default'     => '',
        'placeholder' => 'NQ...',
        'desc_tip'    => true,
    ],

    'bitcoin_xpub' => [
        'title'       => __( 'BTC Extended Public Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Your Bitcoin xpub/zpub/tpub from which recipient addresses are derived.', 'wc-gateway-nimiq' ),
        'default'     => '',
        'placeholder' => 'xpub...',
        'desc_tip'    => true,
    ],

    'ethereum_xpub' => [
        'title'       => __( 'ETH Extended Public Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Your Ethereum xpub from which recipient addresses are derived.', 'wc-gateway-nimiq' ),
        'default'     => '',
        'placeholder' => '0x...',
        'desc_tip'    => true,
    ],

    'margin' => [
        'title'       => __( 'Margin Percentage', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'A margin to apply to crypto payments, in percent. Can also be negative.', 'wc-gateway-nimiq' ),
        'default'     => 0,
        'placeholder' => '0',
        'desc_tip'    => true,
    ],

    'price_service' => [
        'title'       => __( 'Price Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service to use for fetching price information for automatic currency conversion.', 'wc-gateway-nimiq' ),
        'default'     => 'coingecko',
        'options'     => [
            // List available price services here. The option value must match the file name.
            'coingecko' => 'Coingecko',
            'fastspot'  => 'Fastspot (also estimates fees)',
            // 'nimiqx'    => 'NimiqX (Nimiq only)',
        ],
        'desc_tip'    => true,
    ],

    'validation_service_nim' => [
        'title'       => __( 'Nimiq Validation Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service to use for Nimiq transaction validation.', 'wc-gateway-nimiq' ),
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
        'title'       => __( 'Nimiq JSON-RPC URL', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'URL (including port) of the Nimiq JSON-RPC server used to verify transactions.', 'wc-gateway-nimiq' ),
        'default'     => 'http://localhost:8648',
        'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'jsonrpc_nimiq_username' => [
        'title'       => __( 'Nimiq JSON-RPC Username', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( '(Optional) Username for the protected JSON-RPC service', 'wc-gateway-nimiq' ),
        'default'     => '',
        'desc_tip'    => true,
    ],

    'jsonrpc_nimiq_password' => [
        'title'       => __( 'Nimiq JSON-RPC Password', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( '(Optional) Password for the protected JSON-RPC service', 'wc-gateway-nimiq' ),
        'default'     => '',
        'desc_tip'    => true,
    ],

    'nimiqx_api_key' => [
        'title'       => __( 'NimiqX API Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Token for accessing the NimiqX price and validation service.', 'wc-gateway-nimiq' ),
        'default'     => '',
        'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'validation_service_btc' => [
        'title'       => __( 'Bitcoin Validation Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service to use for Bitcoin transaction validation.', 'wc-gateway-nimiq' ),
        'default'     => 'blockstream',
        'options'     => [
            // List available validation services here. The option value must match the file name.
            'blockstream'  => 'Blockstream.info (testnet & mainnet)',
        ],
        'desc_tip'    => true,
    ],

    'validation_service_eth' => [
        'title'       => __( 'Ethereum Validation Service', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'Which service to use for Ethereum transaction validation.', 'wc-gateway-nimiq' ),
        'default'     => 'etherscan',
        'options'     => [
            // List available validation services here. The option value must match the file name.
            'etherscan'  => 'Etherscan.io (testnet & mainnet)',
        ],
        'desc_tip'    => true,
    ],

    'etherscan_api_key' => [
        'title'       => __( 'Etherscan API Key', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Token for accessing the Etherscan validation service.', 'wc-gateway-nimiq' ),
        'default'     => '',
        'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'validation_interval' => [
        'title'       => __( 'Validation Interval', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Interval to validate transactions, in minutes. If you change this, disable and enable this plugin to apply the new interval.', 'wc-gateway-nimiq' ),
        'default'     => 5,
        'placeholder' => '5 minutes',
        'desc_tip'    => true,
    ],

    'rpc_behavior' => [
        'title'       => __( 'Behavior', 'wc-gateway-nimiq' ),
        'type'        => 'select',
        'description' => __( 'How the user should visit the Nimiq Checkout.', 'wc-gateway-nimiq' ),
        'default'     => 'popup',
        'options'     => $redirect_behaviour_options,
        'desc_tip'    => true,
    ],

    'shop_logo_url' => [
        'title'       => __( 'Shop Logo URL (optional)', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'An image that should be displayed instead of the shop\'s identicon. ' .
                             'The URL must be under the same domain as the webshop. ' .
                             'Should be quadratic for best results.', 'wc-gateway-nimiq' ),
        'default'     => '',
        'placeholder' => 'No image set',
        'desc_tip'    => true,
    ],

    'message' => [
        'title'       => __( 'NIM Transaction Message', 'wc-gateway-nimiq' ),
        'type'        => 'text',
        'description' => __( 'Enter a message that should be included in every transaction. 50 byte limit.', 'wc-gateway-nimiq' ),
        'default'     => __( 'Thank you for shopping with us!', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'fee_nim' => [
        'title'       => __( 'NIM Fee per Byte', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Luna per byte to be applied to transactions.', 'wc-gateway-nimiq' ),
        'default'     => 1,
        'desc_tip'    => true,
    ],

    'fee_btc' => [
        'title'       => __( 'BTC Fee per Byte', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Satoshi per byte to be applied to transactions.', 'wc-gateway-nimiq' ),
        'default'     => 40,
        'desc_tip'    => true,
    ],

    'fee_eth' => [
        'title'       => __( 'ETH Gas Price (Gwei)', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'Gas price in Gwei to be applied to transactions.', 'wc-gateway-nimiq' ),
        'default'     => 8,
        'desc_tip'    => true,
    ],

    'tx_wait_duration' => [
        'title'       => __( 'Mempool Wait Limit', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'How many minutes to wait for a transaction to be found, before marking the order as failed.', 'wc-gateway-nimiq' ),
        'default'     => 120, // 2 hours
        'desc_tip'    => true,
    ],

    'confirmations_nim' => [
        'title'       => __( 'Required NIM Confirmations', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'The number of confirmations required to accept a Nimiq transaction.', 'wc-gateway-nimiq' ),
        'default'     => 10, // ~ 10 minutes
        'desc_tip'    => true,
    ],

    'confirmations_btc' => [
        'title'       => __( 'Required BTC Confirmations', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'The number of confirmations required to accept a Bitcoin transaction.', 'wc-gateway-nimiq' ),
        'default'     => 1, // ~ 10 minutes
        'desc_tip'    => true,
    ],

    'confirmations_eth' => [
        'title'       => __( 'Required ETH Confirmations', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'description' => __( 'The number of confirmations required to accept an Ethereum transaction.', 'wc-gateway-nimiq' ),
        'default'     => 45, // ~ 10 minutes
        'desc_tip'    => true,
    ],

    'instructions' => [
        'title'       => __( 'Email Instructions', 'wc-gateway-nimiq' ),
        'type'        => 'textarea',
        'description' => __( 'Instructions that will be added to the thank-you page and emails.', 'wc-gateway-nimiq' ),
        'default'     => __( 'You will receive email updates after your payment has been confirmed and when we sent your order.', 'wc-gateway-nimiq' ),
        'desc_tip'    => true,
    ],

    'current_address_index_btc' => [
        'title'       => __( '[BTC Address Index]', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'min'    => '-1',
        'description' => __( 'DO NOT CHANGE! The current BTC address derivation index.', 'wc-gateway-nimiq' ),
        'default'     => -1,
        'desc_tip'    => true,
    ],

    'current_address_index_eth' => [
        'title'       => __( '[ETH Address Index]', 'wc-gateway-nimiq' ),
        'type'        => 'number',
        'min'    => '-1',
        'description' => __( 'DO NOT CHANGE! The current ETH address derivation index.', 'wc-gateway-nimiq' ),
        'default'     => -1,
        'desc_tip'    => true,
    ],
];
