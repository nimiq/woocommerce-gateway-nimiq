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
            $coins = is_string( $value ) ? $value : number_format($value, $pad_length, '.', '');

            // 2. Split by decimal dot
            $split = explode( '.', $coins, 2 );
            $integers = $split[0];
            $decimals = count( $split ) > 1 ? $split[1] : '';

            // 3.1. Extend decimals with 0s until number of crypto-specific decimals is reached
            $decimals = str_pad( $decimals, $pad_length, '0', STR_PAD_RIGHT );
            // 3.2. Ensure decimals are not too long
            $decimals = substr( $decimals, 0, $pad_length );

            // 4. Join integers with decimals to create value string
            $unit = implode( '', [ $integers, $decimals ] );

            // 5. Remove leading zeros
            $units[ $crypto ] = ltrim($unit, '0') ?: '0';
        }
        return $units;
    }

    public static function required_decimals( $crypto, $price = 1000000 ) {
        // Find number of required significant decimals based on price, can be negative
        return min( ceil( log10( $price ) ) + 2, self::DECIMALS[ $crypto ] );
    }

    public static function calculate_quotes( $value, $prices ) {
        $quotes = [];
        foreach ( $prices as $crypto => $price ) {
            $quotes[ $crypto ] = $value / $price;
        }
        return self::format_quotes( $value, $quotes, $prices );
    }

    public static function format_quotes( $value, $quotes, $prices = null ) {
        foreach ( $quotes as $crypto => $quote ) {
            $price = !empty( $prices ) ? $prices[ $crypto ] : $value / $quote;
            $decimals = self::required_decimals( $crypto, $price );
            $quotes[ $crypto ] = number_format( $quote, $decimals, '.', '' );
        }
        return $quotes;
    }

    /**
     * Returns 1 if $a > $b, -1 if $b > $a, 0 if equal
     */
    public static function unit_compare( $a, $b ) {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');

        $a_length = strlen( $a );
        $b_length = strlen( $b );

        if ( $a_length > $b_length ) return 1;
        if ( $a_length < $b_length ) return -1;

        return strcmp( $a, $b );
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

    public function get_fees_per_byte() {
        return [
            'nim' => $this->gateway->get_setting( 'fee_nim' ),
            'btc' => $this->gateway->get_setting( 'fee_btc' ),
            'eth' => strval( $this->gateway->get_setting( 'fee_eth' ) * 1e9 ), // Option is in Gwei
        ];
    }

    public function get_fees( $message_length ) {
        $perFees = $this->get_fees_per_byte();
        return [
            'nim' => ( 166 + $message_length ) * $perFees[ 'nim' ],
            'btc' => 250 * $perFees[ 'btc' ],
            'eth' => [
                'gas_limit' => 21000,
                'gas_price' => $perFees[ 'eth' ],
            ],
        ];
    }
}
