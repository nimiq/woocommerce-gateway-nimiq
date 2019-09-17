<?php

class Crypto_Manager {
    const DECIMALS = [
        'nim' => 5,
        'btc' => 8,
        'eth' => 18,
    ];

    public static function iso_to_name( $iso ) {
        return [
            'nim' => 'nimiq',
            'btc' => 'bitcoin',
            'eth' => 'ethereum',
        ][ $iso ];
    }

    public static function name_to_iso( $name ) {
        return [
            'nimiq' => 'nim',
            'bitcoin' => 'btc',
            'ethereum' => 'eth',
        ][ $name ];
    }

    public static function coins_to_units( $values ) {
        $units = [];
        foreach ( $values as $crypto => $value ) {
            // Convert to smallest unit string

            // 1. Format as string without exponent
            $pad_length = self::DECIMALS[ $crypto ];
            $coins = number_format($value, $pad_length, '.', '');

            // 2. Split by decimal dot
            $split = explode( '.', $coins, 2 );
            $integers = $split[0];
            $decimals = $split[1] ?: '';

            // 3. Extend decimals with 0s until number of crypto-specific decimals is reached
            $decimals = str_pad( $decimals, $pad_length, '0', STR_PAD_RIGHT );

            // 4. Join integers with decimals to create value string
            $unit = implode( '', [ $integers, $decimals ] );

            // 5. Remove leading zeros
            $units[ $crypto ] = ltrim($unit, '0');
        }
        return $units;
    }

    public static function required_decimals( $crypto, $price = 1000000 ) {
        // Find number of required significant decimals based on price
        return max( min( ceil( log10( $price ) ) + 2, self::DECIMALS[ $crypto ] ), 0 );
    }

    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }

    public function get_accepted_cryptos() {
        $accepted_cryptos = [ 'nim' ];
        if ( !empty( $this->gateway->get_option( 'bitcoin_xpub' ) ) ) $accepted_cryptos[] = 'btc';
        if ( !empty( $this->gateway->get_option( 'ethereum_xpub' ) ) ) $accepted_cryptos[] = 'eth';
        return $accepted_cryptos;
    }

    public function calculate_quotes( $value, $prices ) {
        $quotes = [];
        foreach ( $prices as $crypto => $price ) {
            // TODO: Add margins
            $quotes[ $crypto ] = round( $value / $price, $this::required_decimals( $crypto, $price ) );
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
