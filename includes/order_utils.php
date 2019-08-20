<?php

class Order_Utils {
    function get_order_currency( $order ) {
        return $order->get_meta('order_crypto_currency') ?: 'nim';
    }

    public static function get_order_total_crypto( $order ) {
        // 1. Get order crypto currency
        $currency = self::get_order_currency( $order );

        // 2. Get order crypto total
        $order_total = $order->get_meta( 'order_total_' . $currency );

        // 3. Convert to smallest unit string
        // 3.1. Split by decimal dot
        $split = explode( '.', $order_total, 2 );
        $integers = $split[0];
        $decimals = $split[1] ?: '';

        // 3.2. Extend decimals with 0s until crypto-specific decimals is reached
        $pad_length = [
            'nim' => 5,
            'btc' => 8,
            'eth' => 18,
        ][ $currency ];
        $decimals = str_pad( $decimals, $pad_length, '0', STR_PAD_RIGHT );

        // 3.3. Join integers with decimals to create value string
        $wei = implode( '', [ $integers, $decimals ] );

        // 3.4. Remove leading zeros
        return ltrim($wei, '0');
    }

    function get_order_sender_address( $order ) {
        $currency = self::get_order_currency( $order );
        return $order->get_meta( 'customer_' . $currency . '_address' );
    }

    function get_order_recipient_address( $order, $gateway ) {
        $currency = self::get_order_currency( $order );
        $qualified_currency_name = [
            'nim' => 'nimiq',
            'btc' => 'bitcoin',
            'eth' => 'ethereum',
        ][ $currency ];
        $order_address = $order->get_meta( 'order_' . $currency . '_address' );
        $gateway_address = $gateway->get_option( $qualified_currency_name . '_address' );
        return $order_address ?: $gateway_address;
    }
}
