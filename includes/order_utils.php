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
        if ( $currency === 'btc' || $currency === 'eth' ) {
            return $order->get_meta( 'order_' . $currency . '_address' );
        } else {
            return $gateway->get_option( 'nimiq_address' );
        }
    }
}
