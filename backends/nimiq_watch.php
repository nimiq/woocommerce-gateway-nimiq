<?php
include_once( './interface.php' );

class WC_Gateway_Nimiq_Backend_Nimiqwatch implements WC_Gateway_Nimiq_Backend_Interface {
    /**
     * Initializes the driver for the given HEX transaction hash
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->api_domain = $gateway->get_option( 'network' ) === 'main' ? 'https://api.nimiq.watch' : 'https://test-api.nimiq.watch';
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        $api_response = wp_remote_get( $this->api_domain . '/latest/1' );

        if ( is_wp_error( $api_response ) ) {
            return new WP_Error('connection', $api_response->errors[ 0 ]);
        }

        $current_height = json_decode( $api_response[ 'body' ] );

        if ( $current_height->error ) {
            return new WP_Error('backend', $current_height->error);
        }

        if ( empty( $current_height ) ) {
            return new WP_Error('backend', 'Could not get the current blockchain height from NIMIQ.WATCH.');
        }

        return $current_height[ 0 ]->height;
    }

    /**
     * Load a transaction from the backend
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash ) {
        // Convert HEX hash into base64
        $transaction_hash = urlencode( base64_encode( pack( 'H*', $transaction_hash ) ) );

        $api_response = wp_remote_get( $this->api_domain . '/transaction/' . $transaction_hash );
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
     * Returns any error that the backend returned
     * @return {string|false}
     */
    public function error() {
        if ( empty( $this->transaction ) ) {
            return 'Could not retrieve transaction information from NIMIQ.WATCH.';
        }
        return $this->transaction->error || false;
    }

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address() {
        return $this->transaction->sender_address;
    }

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address() {
        return $this->transaction->receiver_address;
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
        return $this->transaction->block_height;
    }
}
