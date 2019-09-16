<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

add_action( 'woocommerce_api_nimiq_checkout_callback', 'woo_nimiq_checkout_callback' );

function woo_nimiq_checkout_reply( $data, $status = 200 ) {
    wp_send_json( $data, $status );
}

function woo_nimiq_checkout_error( $message, $status = 400 ) {
    woo_nimiq_checkout_reply( [
        'error' => $message,
        'status' => $status,
    ], $status );
}

function woo_nimiq_checkout_get_param( $key, $method = 'get' ) {
    $data = $method === 'get' ? $_GET : $_POST;

    if ( !isset( $data[ $key ] ) ) return null;
    return sanitize_text_field( $data[ $key ] );
}

/**
 * @param array $request Options for the function.
 */
function woo_nimiq_checkout_callback() {
    header( 'Access-Control-Allow-Origin: *' );
    header( 'Content-Type: application/json' );

    $request = [
        'id' => woo_nimiq_checkout_get_param( 'id' ),
        'csrf' => woo_nimiq_checkout_get_param( 'csrf', 'post' ),
        'command' => woo_nimiq_checkout_get_param( 'command', 'post' ),
        'currency' => woo_nimiq_checkout_get_param( 'currency', 'post' ),
    ];

    $id = $request[ 'id' ];
    $csrf_token = $request[ 'csrf' ];
    $command = strtolower( $request[ 'command' ] );

    $order = wc_get_order( $id );

    // Validate that the order exists
    if ( !$order ) {
        return woo_nimiq_checkout_error( 'Invalid order ID', 404 );
    }

    $gateway = new WC_Gateway_Nimiq();

    // Validate that the order's payment method is this plugin and that the order is currently 'pending'
    if ( $order->get_payment_method() !== $gateway->id || $order->get_status() !== 'pending' ) {
        return woo_nimiq_checkout_error( 'Bad order', 406 );
    }

    // Validate CSRF token
    $order_csrf_token = $order->get_meta( 'checkout_csrf_token' );
    if ( empty( $order_csrf_token ) || $order_csrf_token !== $csrf_token ) {
        return woo_nimiq_checkout_error( 'Invalid CSRF token', 403 );
    }

    // Call handler depending on command
    switch ( $command ) {
        case 'get_time':
            return woo_nimiq_checkout_callback_get_time( $request, $order, $gateway );
        case 'set_currency':
            return woo_nimiq_checkout_callback_set_currency( $request, $order, $gateway );
        case 'check_network':
            return woo_nimiq_checkout_callback_check_network( $request, $order, $gateway );
        default:
            return woo_nimiq_checkout_callback_unknown( $request, $order, $gateway );
    }
}

function woo_nimiq_checkout_callback_get_time( $request, $order, $gateway ) {
    return woo_nimiq_checkout_reply( [
        'time' => time(),
    ] );
}

function woo_nimiq_checkout_callback_set_currency( $request, $order, $gateway ) {
    $currency = strtolower( $request[ 'currency' ] );

    $cryptoman = new Crypto_Manager( $gateway );
    $accepted_currencies = $cryptoman->get_accepted_cryptos();

    // Validate that the submitted currency is valid
    if ( !in_array( $currency, $accepted_currencies, true ) ) {
        return woo_nimiq_checkout_error( 'Bad currency', 406 );
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

    $order_hash = $order->get_meta( 'order_hash' );
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

    return woo_nimiq_checkout_reply( [
        'type' => 0, // DIRECT
        'currency' => $currency,
        'expires' => intval( $order->get_meta( 'crypto_rate_expires' ) ),
        'amount' => Order_Utils::get_order_total_crypto( $order ),
        'protocolSpecific' => $protocolSpecific,
    ] );
}

function woo_nimiq_checkout_callback_check_network( $request, $order, $gateway ) {
    $currency = Order_Utils::get_order_currency( $order, false );

    if ( empty( $currency ) ) {
        return woo_nimiq_checkout_error( 'Method not allowed. Select currency first.', 405 );
    }

    if ( $currency === 'nim' ) {
        return woo_nimiq_checkout_error( 'Forbidden. Cannot check the network for orders payed in NIM.', 403 );
    }

    // Init validation service
    $services = [];
    $service_slug = $gateway->get_option( 'validation_service_' . $currency );
    include_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'validation_services' . DIRECTORY_SEPARATOR . $service_slug . '.php' );
    $service = $services[ $currency ];

    $transaction_hash = $order->get_meta('transaction_hash');

    $is_loaded = $service->load_transaction( $transaction_hash, $order, $gateway );
    if ( is_wp_error( $is_loaded ) ) {
        return woo_nimiq_checkout_error( $is_loaded->get_error_message(), 500 );
    }

    if ( $is_loaded ) {
        // Delete CSRF token when transaction found
        $order->delete_meta_data( 'checkout_csrf_token' );
        $order->save();
    }

    return woo_nimiq_checkout_reply( [
        'transaction_found' => $is_loaded,
    ] );

    // For now, we need to update the status here, because at least for BTC and ETH the Hub request does not return yet.
    // $order->update_status( 'on-hold', __( 'Awaiting transaction validation.', 'wc-gateway-nimiq' ) );
}

function woo_nimiq_checkout_callback_unknown( $request, $order, $gateway ) {
    return woo_nimiq_checkout_error( 'Bad command', 406 );
}
