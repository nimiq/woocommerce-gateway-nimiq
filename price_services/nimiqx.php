<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Price_Service_Nimiqx implements WC_Gateway_Nimiq_Price_Service_Interface {

    private $api_endpoint = 'https://api.nimiqx.com/';
    private $api_key = false;

    /**
     * Initializes the validation service
     *
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     *
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
        $this->api_key = $gateway->get_option( 'nimiqx_api_key' );
    }

    /**
     * @param $currency
     *
     * @return float
     * @throws Exception
     */
    public function getCurrentPrice( $currency ) {
        if ( ! $this->api_key ) {
            throw new Exception( new WP_Error( 'Invalid NimiqX api key!' ) );
        }
        $currency = strtolower( $currency );
        $output   = file_get_contents( $this->api_endpoint . 'price/btc,' . $currency . '?api_key=' . $this->api_key );
        $data     = json_decode( $output, true );

        return ( $data[ $currency ] ) ? $data[ $currency ] : 0;
    }
}


