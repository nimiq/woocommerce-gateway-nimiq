<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Service_Jsonrpc implements WC_Gateway_Nimiq_Service_Interface {
    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @®eturn {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->api_domain = 'http://localhost:8648';
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        $call = '{"jsonrpc":"2.0","method":"blockNumber","params":[],"id":42}';

        $api_response = wp_remote_post( $this->api_domain, array( 'body' => $call ) );

        if ( is_wp_error( $api_response ) ) {
            return new WP_Error('connection', $api_response->errors[ 0 ]);
        }

        $current_height = json_decode( $api_response[ 'body' ] );

        if ( $current_height->error ) {
            return new WP_Error('service', $current_height->error);
        }

        return $current_height[ 0 ]->result;
    }

    /**
     * Loads a transaction from the node
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash ) {
        // TODO Injection vuln?
        $call = '{"jsonrpc":"2.0","method":"getTransactionByHash","params":["' . $transaction_hash . '"],"id":42}';

        $api_response = wp_remote_post( $this->api_domain, array( 'body' => $call ) );

        if ( is_wp_error( $api_response ) ) {
            return new WP_Error('connection', $api_response->errors[ 0 ]);
        }

        $this->transaction = json_decode( $api_response[ 'body' ] );
    }

    /**
     * Returns if transaction was found or not
     * @return {boolean}
     */
    public function transaction_found() {
        return $this->transaction->result !== null;
    }

    /**
     * Returns any error that the node returned
     * @return {string|false}
     */
    public function error() {
        if ( empty( $this->transaction ) ) {
            return 'Could not retrieve transaction information from Nimiq node.';
        }
        return $this->transaction->error || false;
    }

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address() {
        return $this->transaction->result->fromAddress;
    }

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address() {
        return $this->transaction->result->toAddress;
    }

    /**
     * Returns the value of the transaction in Luna
     * @return {number}
     */
    public function value() {
        return $this->transaction->result->value;
    }

    /**
     * Returns the data (message) of the transaction in plain text
     * @return {string}
     */
    public function message() {
        if ( $this->transaction->data === null ) {
            return '';
        }

        // TODO Is this really base64?
        $extraData = base64_decode( $this->transaction->data );
        return mb_convert_encoding( $extraData, 'UTF-8' );
    }

    /**
     * Returns the height of the block containing the transaction
     * @return {number}
     */
    public function block_height() {
        return $this->transaction->blockNumber;
    }
}
