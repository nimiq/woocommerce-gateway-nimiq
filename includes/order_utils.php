<?php

class Order_Utils {
    public static function get_order_currency( $order, $fallback_to_nim = true ) {
        return $order->get_meta( 'order_crypto_currency' ) ?: ( $fallback_to_nim ? 'nim' : null );
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
        switch( $currency ) {
            case 'nim': return $gateway->get_option( 'nimiq_address' );
            case 'btc': return $order->get_meta( 'order_' . $currency . '_address' );
            case 'eth': return strtolower( $order->get_meta( 'order_' . $currency . '_address' ) );
        }
    }

    public static function get_order_recipient_addresses( $order, $gateway ) {
        return [
            'nim' => $gateway->get_option( 'nimiq_address' ),
            'btc' => $order->get_meta( 'order_btc_address' ),
            'eth' => strtolower( $order->get_meta( 'order_eth_address' ) ),
        ];
    }
}
