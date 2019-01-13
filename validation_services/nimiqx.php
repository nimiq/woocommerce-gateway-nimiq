<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Service_NimiqX implements WC_Gateway_Nimiq_Service_Interface {
    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;

        $this->api_key = $gateway->get_option( 'api_key' );
        if ( empty( $this->api_key ) ) {
            throw new WP_Error('connection', 'API key not set.');
        }
        if ( !ctype_xdigit( $this->api_key ) ) {
            throw new WP_Error('service', 'Invalid API key');
        }
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        $request_uri = 'https://api.nimiqx.com/network-stats/?api_key=' . $this->api_key;
        $api_response = wp_remote_get( $request_uri );

        if ( is_wp_error( $api_response ) ) {
            return new WP_Error('connection', $api_response->errors[ 0 ]);
        }

        $current_height = json_decode( $api_response[ 'body' ] );

        if ( $current_height->error ) {
            return new WP_Error('service', $current_height->error);
        }

        return $current_height[ 0 ]->height;
    }

    /**
     * Loads a transaction from the service
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash ) {
        if ( !ctype_xdigit( $transaction_hash ) ) {
            return new WP_Error('service', 'Invalid transaction hash');
        }

        $request_uri = 'https://api.nimiqx.com/transaction/' . $transaction_hash . '?api_key=' . $this->api_key;
        
        $api_response = wp_remote_get( $request_uri );
        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $this->transaction = json_decode( $api_response[ 'body' ] );
    }

    /**
     * Returns if transaction was found or not
     * @return {boolean}
     */
    public function transaction_found() {
        return $this->transaction->error !== 'Transaction not found';
    }

    /**
     * Returns any error that the service returned
     * @return {string|false}
     */
    public function error() {
        if ( empty( $this->transaction ) ) {
            return 'Could not retrieve transaction information from NimiqX.';
        }
        return $this->transaction->error || false;
    }

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address() {
        return $this->transaction->from_address;
    }

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address() {
        return $this->transaction->to_address;
    }

    /**
     * Returns the value of the transaction in Luna
     * @return {number}
     */
    public function value() {
        return $this->transaction->value;
    }

    /**
     * Returns the data (message) of the transaction in plain text
     * @return {string}
     */
    public function message() {
        $extraData = base64_decode( $this->transaction->data );
        return mb_convert_encoding( $extraData, 'UTF-8' );
    }

    /**
     * Returns the height of the block containing the transaction
     * @return {number}
     */
    public function block_height() {
        return $this->transaction->height;
    }
}

$service = new WC_Gateway_Nimiq_Service_NimiqX( $gateway );
