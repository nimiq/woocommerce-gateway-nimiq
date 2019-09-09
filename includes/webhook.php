<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

add_action( 'rest_api_init', function () {
    register_rest_route( 'nimiq-checkout/v1', '/callback/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'woo_nimiq_checkout_callback',
        'args' => [
            'id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param );
                }
            ],
        ],
    ] );
} );

/**
 * @param array $request Options for the function.
 */
function woo_nimiq_checkout_callback( WP_REST_Request $request ) {
    $id = $request[ 'id' ];
    $csrf_token = $request[ 'csrf_token' ];
    $command = strtolower( $request[ 'command' ] );

    $order = wc_get_order( $id );

    // Validate that the order exists
    if ( !$order ) {
        return new WP_Error( 'no_order', 'Invalid order ID', array( 'status' => 404 ) );
    }

    $gateway = new WC_Gateway_Nimiq();

    // Validate that the order's payment method is this plugin and that the order is currently 'pending'
    if ( $order->get_payment_method() !== $gateway->id || $order->get_status() !== 'pending' ) {
        return new WP_Error( 'bad_order', 'Bad order', array( 'status' => 406 ) );
    }

    // Validate CSRF token
    $order_csrf_token = $order->get_meta( 'checkout_csrf_token' );
    if ( empty( $order_csrf_token ) || $order_csrf_token !== $csrf_token ) {
        return new WP_Error( 'invalid_csrf', 'Invalid CSRF token', array( 'status' => 403 ) );
    }

    // Call handler depending on command
    switch ( $command ) {
        case 'get_time':
            return woo_nimiq_checkout_callback_get_time( $request, $order, $gateway ); break;
        case 'set_currency':
            return woo_nimiq_checkout_callback_set_currency( $request, $order, $gateway ); break;
        case 'check_network':
            return woo_nimiq_checkout_callback_check_network( $request, $order, $gateway ); break;
        default:
            return woo_nimiq_checkout_callback_unknown( $request, $order, $gateway ); break;
    }
}

function woo_nimiq_checkout_callback_get_time( $request, $order, $gateway ) {
    return [
        'time' => time(),
    ];
}

function woo_nimiq_checkout_callback_set_currency( $request, $order, $gateway ) {
    $currency = strtolower( $request[ 'currency' ] );

    $cryptoman = new Crypto_Manager( $gateway );
    $accepted_currencies = $cryptoman->get_accepted_cryptos();

    // Validate that the submitted currency is valid
    if ( !in_array( $currency, $accepted_currencies, true ) ) {
        return new WP_Error( 'bad_currency', 'Bad currency', array( 'status' => 406 ) );
    }

    $order->update_meta_data( 'order_crypto_currency', $currency );

    $address = Order_Utils::get_order_recipient_address( $order, $gateway );
    if ( empty( $address ) && ( $currency === 'btc' || $currency === 'eth' ) ) {
        include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'address_deriver.php' );
        $deriver = new Address_Deriver( $gateway );
        $address = $deriver->get_next_address( $currency );
        if ( !empty( $address ) ) {
            $order->update_meta_data( 'order_' . $currency . '_address', $address );
        }
    }

    $order->save();

    $protocolSpecific = [
        'recipient' => $address,
    ];

    $tx_message = ( !empty( $gateway->get_option( 'message' ) ) ? $gateway->get_option( 'message' ) . ' ' : '' )
        . '(' . strtoupper( $gateway->get_short_order_hash( $order_hash ) ) . ')';
    $tx_message_bytes = unpack('C*', $tx_message); // Convert to byte array

    $fees = $cryptoman->get_fees( count( $tx_message_bytes ) );

    if ( $currency === 'eth' ) {
        $protocolSpecific[ 'gasLimit' ] = $fees[ $currency ]['gas_limit'];
        $protocolSpecific[ 'gasPrice' ] = $fees[ $currency ]['gas_price'];
    } else {
        $protocolSpecific[ 'fee' ] = $fees[ $currency ];
    }

    $paymentOption = [
        'type' => 0, // DIRECT
        'currency' => strtoupper( $currency ),
        'expires' => intval( $order->get_meta( 'crypto_rate_expires' ) ),
        'amount' => Order_Utils::get_order_total_crypto( $order ),
        'protocolSpecific' => $protocolSpecific,
    ];

    return $paymentOption;
}

function woo_nimiq_checkout_callback_check_network( $request, $order, $gateway ) {
    return new WP_Error( 'not_implemented', 'Not implemented', array( 'status' => 501 ) );

    // For now, we need to update the status here, because at least for BTC and ETH the Hub request does not return yet.
    // $order->update_status( 'on-hold', __( 'Awaiting transaction validation.', 'wc-gateway-nimiq' ) );

    // Delete CSRF token when transaction found
    // $order->delete_meta_data( 'checkout_csrf_token' );

    // $order->save();
}

function woo_nimiq_checkout_callback_unknown( $request, $order, $gateway ) {
    return new WP_Error( 'bad_command', 'Bad command', array( 'status' => 406 ) );
}
