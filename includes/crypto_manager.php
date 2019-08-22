<?php

class Crypto_Manager {
    const DECIMALS = [
        'nim' => 5,
        'btc' => 8,
        'eth' => 18,
    ];

    public static function coins_to_units( $values ) {
        $units = [];
        foreach ( $values as $crypto => $value ) {
            // Convert to smallest unit string
            // 1. Split by decimal dot
            $split = explode( '.', strval( $value ), 2 );
            $integers = $split[0];
            $decimals = $split[1] ?: '';

            // 2. Extend decimals with 0s until number of crypto-specific decimals is reached
            $pad_length = self::DECIMALS[ $crypto ];
            $decimals = str_pad( $decimals, $pad_length, '0', STR_PAD_RIGHT );

            // 3. Join integers with decimals to create value string
            $unit = implode( '', [ $integers, $decimals ] );

            // 4. Remove leading zeros
            $units[ $crypto ] = ltrim($unit, '0');
        }
        return $units;
    }

    public static function required_decimals( $prices = [] ) {
        // TODO: Find number of required significant decimals based on price
        return [
            'nim' => 0, // FIXME: For now, to reduce visual complexity
            'btc' => 8,
            'eth' => 6, // FIXME: For now, to reduce visual complexity
        ];
    }

    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }

    public function get_accepted_cryptos() {
        $accepted_cryptos = [ 'nim' ];
        if ( !empty( $this->gateway->get_option( 'bitcoin_address' ) ) ) $accepted_cryptos[] = 'btc';
        if ( !empty( $this->gateway->get_option( 'ethereum_address' ) ) ) $accepted_cryptos[] = 'eth';
        return $accepted_cryptos;
    }

    public function calculate_quotes( $value, $prices ) {
        $quotes = [];
        foreach ( $prices as $crypto => $price ) {
            // TODO: Add margins
            $quotes[ $crypto ] = round( $value / $prices[ $crypto ], $this::required_decimals()[ $crypto ] );
        }
        return $quotes;
    }

    public function get_fees( $message_length ) {
        return [
            'nim' => ( 166 + $message_length ) * ( intval( $this->gateway->get_option( 'fee_nim' ) ?: 0 ) ),
            'btc' => 250 * ( intval( $this->gateway->get_option( 'fee_btc' ) ?: 0 ) ),
            'eth' => [
                'gas_limit' => 21000,
                'gas_price' => intval( $this->gateway->get_option( 'fee_eth' ) ?: 0 ) * 1e9, // Gwei
            ],
        ];
    }
}
