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

    /**
     * @param {WC_Order} $order
     * @return {number|boolean} A timestamp or false if the order does not expire
     */
    public static function get_order_hold_expiry( $order ) {
        // TODO: Respect $gateway->get_setting( 'tx_wait_duration' ) here?

        $order_hold_minutes = get_option( 'woocommerce_hold_stock_minutes' );
        if ( empty( $order_hold_minutes ) ) return false;

        $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
        return $order_date + $order_hold_minutes * 60;
    }

    public static function get_payment_state( $comparison ) {
        return $comparison > 0 ? 'OVERPAID' : ( $comparison < 0 ? 'UNDERPAID' : 'PAID' );
    }
}
