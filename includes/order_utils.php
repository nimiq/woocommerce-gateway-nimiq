<?php

class Order_Utils {
    public static function get_order_currency( $order ) {
        return $order->get_meta('order_crypto_currency') ?: 'nim';
    }

    public static function get_order_total_crypto( $order ) {
        // 1. Get order crypto currency
        $currency = self::get_order_currency( $order );

        // 2. Get order crypto total
        $order_total = $order->get_meta( 'order_total_' . $currency );

        return Crypto_Manager::coins_to_units( [ $currency => $order_total ] )[ $currency ];
    }

    public static function get_order_sender_address( $order ) {
        $currency = self::get_order_currency( $order );
        return $order->get_meta( 'customer_' . $currency . '_address' );
    }

    public static function get_order_recipient_address( $order, $gateway ) {
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
