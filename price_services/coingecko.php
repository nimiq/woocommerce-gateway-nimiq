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
     * @param {string} $currency
     * @return {float}
     */
    public function getCurrentPrice( $currency ) {
        $currency = strtolower( $currency );
        $api_response = wp_remote_get( $this->api_endpoint . '/simple/price?ids=nimiq-2&vs_currencies=' . $currency );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $result = json_decode( $api_response[ 'body' ], true );

        if ( $result->error ) {
            return new WP_Error( 'service', $result->error );
        }

        $price = $result['nimiq-2'][ $currency ];

        if ( empty( $price ) ) {
            return new WP_Error( 'service', 'The currency ' . strtoupper( $currency ) . ' is not supported by Coingecko.' );
        };

        return $price;
    }
}


