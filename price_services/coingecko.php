<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Price_Service_Coingecko implements WC_Gateway_Nimiq_Price_Service_Interface {

    private $api_endpoint = 'https://api.coingecko.com/';
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
     * @param $currency
     *
     * @return float
     * @throws Exception
     */
    public function getCurrentPrice( $currency ) {
        $currency = strtolower( $currency );
        $output   = file_get_contents( $this->api_endpoint . 'api/v3/coins/nimiq-2' );
        $data     = json_decode( $output, true );

        return ( $data['market_data']['current_price'][ $currency ] ) ? $data['market_data']['current_price'][ $currency ] : 0;
    }
}


