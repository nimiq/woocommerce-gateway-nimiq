<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Price_Service_Fastspot implements WC_Gateway_Nimiq_Price_Service_Interface {

    private $api_endpoint = 'https://api-v0.fastspot.io';
    private $api_key = false;

    /**
     * Initializes the validation service
     *
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }

    /**
     * @param {string[]} $crypto_currencies
     * @param {string} $shop_currency
     * @param {number} $order_amount
     * @return {[
     *     'prices'? => [[iso: string]: number]],
     *     'quotes'? => [[iso: string]: number]],
     *     'fees'? => [[iso: string]: number | ['gas_limit' => number, 'gas_price' => number]],
     *     'fees_per_byte'? => [[iso: string]: number],
     * ]} - Must include either prices or quotes, may include fees
     */
    public function get_prices( $crypto_currencies, $shop_currency, $order_amount ) {
        $fiat_currency = strtoupper( $shop_currency );
        $uppercase_cryptos = array_map( 'strtoupper', $crypto_currencies );

        $options = [
            'from' => $uppercase_cryptos,
            'to' => [
                $fiat_currency => $order_amount,
            ],
        ];

        $api_response = wp_remote_post( $this->api_endpoint . '/estimates', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode( $options ),
        ] );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $result = json_decode( $api_response[ 'body' ], true ); // Return as associative array (instead of object)

        if ( array_key_exists( 'status', $result ) && $result[ 'status' ] >= 400 ) {
            return new WP_Error( 'service', $result[ 'detail' ] );
        }

        $quotes = [];
        $fees = [];
        $feesPerByte = [];
        foreach ( $result[ 'from' ] as $price_object ) {
            $currency_iso = strtolower( $price_object[ 'symbol' ] );

            $quotes[ $currency_iso ] = $price_object[ 'amount' ];

            $fee = Crypto_Manager::coins_to_units( [ $currency_iso => $price_object[ 'fee' ] ] )[ $currency_iso ];
            $feePerByte = Crypto_Manager::coins_to_units( [ $currency_iso => $price_object[ 'perFee' ] ] )[ $currency_iso ];
            $fees[ $currency_iso ] = $currency_iso === 'eth'
                ? [
                    'gas_limit' => 21000,
                    'gas_price' => $feePerByte,
                ] : $fee;
            $feesPerByte[ $currency_iso ] = $feePerByte;
        }

        return [
            'quotes' => $quotes,
            'fees' => $fees,
            'fees_per_byte' => $feesPerByte,
        ];
    }
}
