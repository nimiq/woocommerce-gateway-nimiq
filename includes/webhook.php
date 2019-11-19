<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ERROR);

add_action( 'woocommerce_api_nimiq_checkout_callback', 'woo_nimiq_checkout_callback' );
add_action( 'wp_ajax_nimiq_checkout_callback', 'woo_nimiq_checkout_callback' );
add_action( 'wp_ajax_nopriv_nimiq_checkout_callback', 'woo_nimiq_checkout_callback' );

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
    $request = [
        'id' => woo_nimiq_checkout_get_param( 'id' ),
        'csrf' => woo_nimiq_checkout_get_param( 'csrf', 'post' ),
        'command' => woo_nimiq_checkout_get_param( 'command', 'post' ),
        'currency' => woo_nimiq_checkout_get_param( 'currency', 'post' ),
    ];

    $gateway = new WC_Gateway_Nimiq();

    // Set headers
    $cors_origin = $gateway->get_option( 'network' ) === 'main' ? 'https://hub.nimiq.com' : $_SERVER['HTTP_ORIGIN'];
    header( 'Access-Control-Allow-Origin: ' . $cors_origin );
    header( 'Access-Control-Allow-Credentials: true');

    $order = wc_get_order( $request[ 'id' ] );

    // Validate that the order exists
    if ( !$order ) {
        return woo_nimiq_checkout_error( 'Invalid order ID', 404 );
    }

    // Validate that the order's payment method is this plugin and that the order is currently 'pending'
    if ( $order->get_payment_method() !== $gateway->id || $order->get_status() !== 'pending' ) {
        return woo_nimiq_checkout_error( 'Bad order', 406 );
    }

    // Validate CSRF token
    $order_hash = $order->get_meta( 'order_hash' );
    if ( !wp_verify_nonce( $request[ 'csrf' ], 'nimiq_checkout_' . $order_hash ) ) {
        return woo_nimiq_checkout_error( 'Invalid CSRF token', 403 );
    }

    try {
        // Call handler depending on command
        switch ( strtolower( $request[ 'command' ] ) ) {
            case 'set_currency':
                return woo_nimiq_checkout_callback_set_currency( $request, $order, $gateway );
            case 'get_state':
                return woo_nimiq_checkout_callback_get_state( $request, $order, $gateway );
            default:
                return woo_nimiq_checkout_callback_unknown( $request, $order, $gateway );
        }
    } catch (Exception $error) {
        return woo_nimiq_checkout_error( $error->getMessage(), 500 );
    }
}

function woo_nimiq_checkout_array_find($function, $array) {
    foreach ($array as $item) {
        if (call_user_func($function, $item) === true) return $item;
    }
    return null;
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

    // Get payment option for the selected currency from stored request
    $stored_request = $order->get_meta( 'nc_payment_request' ) ?: null;
    if ( !$stored_request ) return woo_nimiq_checkout_error( 'Original request not found in order', 500 );

    $request = json_decode( $stored_request, true );

    $payment_option = woo_nimiq_checkout_array_find( function( $option ) use ( $currency ) {
        return $option[ 'currency' ] === $currency;
    }, $request[ 'paymentOptions' ] );
    if ( !$payment_option ) return woo_nimiq_checkout_error( 'Selected currency not found in original request', 500 );

    // Get the order address, or derive a new one
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

    $payment_option[ 'protocolSpecific' ][ 'recipient' ] = $address;

    return woo_nimiq_checkout_reply( $payment_option );

    // // Rebuild the payment option for the selected currency from stored data
    // $protocolSpecific = [
    //     'recipient' => $address,
    // ];

    // $order_hash = $order->get_meta( 'order_hash' );
    // $tx_message = ( !empty( $gateway->get_option( 'message' ) ) ? $gateway->get_option( 'message' ) . ' ' : '' )
    //     . '(' . strtoupper( $gateway->get_short_order_hash( $order_hash ) ) . ')';
    // $tx_message_bytes = unpack('C*', $tx_message); // Convert to byte array

    // // Get fees from order meta
    // $fee = $order->get_meta( 'crypto_fee_' . $currency ) ?: $cryptoman->get_fees( count( $tx_message_bytes ) )[ $currency ];

    // if ( $currency === 'eth' ) {
    //     // For ETH, the fee stored in the order is the gas_price, but the Crypto_Manager fallback returns a keyed array
    //     $gas_price = is_array( $fee ) ? $fee[ 'gas_price' ] : $fee;
    //     $protocolSpecific[ 'gasLimit' ] = 21000;
    //     $protocolSpecific[ 'gasPrice' ] = $gas_price;
    // } else {
    //     $protocolSpecific[ 'fee' ] = intval( $fee );
    // }

    // return woo_nimiq_checkout_reply( [
    //     'type' => 0, // DIRECT
    //     'currency' => $currency,
    //     'expires' => intval( $order->get_meta( 'crypto_rate_expires' ) ),
    //     'amount' => Order_Utils::get_order_total_crypto( $order ),
    //     'protocolSpecific' => $protocolSpecific,
    // ] );
}

function woo_nimiq_checkout_callback_get_state( $request, $order, $gateway ) {
    $currency = Order_Utils::get_order_currency( $order, false );

    if ( empty( $currency ) || $currency === 'nim' ) {
        // When no currency was selected, or the currency is NIM, respond with the time only.
        return woo_nimiq_checkout_reply( [
            'time' => time(),
            'payment_accepted' => false,
            'payment_state' => 'NOT_FOUND',
        ] );
    }

    $transaction_hash = $order->get_meta( 'transaction_hash' );

    if ( !empty( $transaction_hash ) ) {
        return woo_nimiq_checkout_reply( [
            'time' => time(),
            'payment_accepted' => true,
            'payment_state' => $order->get_meta( 'nc_payment_state' ) ?: 'PAID',
        ] );
    }

    // Init validation service
    $services = [];
    $service_slug = $gateway->get_option( 'validation_service_' . $currency );
    include_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'validation_services' . DIRECTORY_SEPARATOR . $service_slug . '.php' );
    $service = $services[ $currency ];

    $payment_state = $service->load_transaction( $transaction_hash, $order, $gateway );
    if ( is_wp_error( $payment_state ) ) {
        return woo_nimiq_checkout_error( $payment_state->get_error_message(), 500 );
    }

    $payment_accepted = $service->transaction_found();

    $order->update_meta_data( 'nc_payment_state', $payment_state );
    $order->save();

    return woo_nimiq_checkout_reply( [
        'time' => time(),
        'payment_accepted' => $payment_accepted,
        'payment_state' => $payment_state,
    ] );
}

function woo_nimiq_checkout_callback_unknown( $request, $order, $gateway ) {
    return woo_nimiq_checkout_error( 'Bad command', 406 );
}
