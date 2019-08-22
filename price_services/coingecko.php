<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Price_Service_Coingecko implements WC_Gateway_Nimiq_Price_Service_Interface {

    private $api_endpoint = 'https://api.coingecko.com/api/v3';
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
     * @return {{[iso: string]: number]}}
     */
    public function get_prices( $crypto_currencies, $shop_currency ) {
        $fiat_currency = strtolower( $shop_currency );
        $ids = implode( ',', array_map( function( $currency_iso ) {
            return [
                'nim' => 'nimiq-2',
                'btc' => 'bitcoin',
                'eth' => 'ethereum',
            ][ $currency_iso ];
        }, $crypto_currencies ) );
        $api_response = wp_remote_get( $this->api_endpoint . '/simple/price?ids=' . $ids . '&vs_currencies=' . $fiat_currency );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $result = json_decode( $api_response[ 'body' ], true );

        if ( $result->error ) {
            return new WP_Error( 'service', $result->error );
        }

        $prices = [];
        foreach ( $result as $id => $price_object ) {
            $currency_iso = [
                'nimiq-2' => 'nim',
                'bitcoin' => 'btc',
                'ethereum' => 'eth',
            ][ $id ];

            $price = $price_object[ $fiat_currency ];

            if ( empty( $price ) ) {
                return new WP_Error( 'service', sprintf( __( 'The currency %s is not supported by Coingecko.', 'wc-gateway-nimiq' ), strtoupper( $fiat_currency ) ) );
            };

            $prices[ $currency_iso ] = $price;
        }

        return $prices;
    }
}
