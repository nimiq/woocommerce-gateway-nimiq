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
        $this->api_domain = $gateway->get_option( 'jsonrpc_url' );
        if ( empty( $this->api_domain ) ) {
            throw new WP_Error( 'connection', 'API URL not set.' );
        }
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        $call = '{"jsonrpc":"2.0","method":"blockNumber","params":[],"id":42}';

        $api_response = wp_remote_post( $this->api_domain, array( 'body' => $call ) );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $current_height = json_decode( $api_response[ 'body' ] );

        if ( $current_height->error ) {
            return new WP_Error( 'service', 'JSON-RPC replied: ' . $current_height->error->message );
        }

        if ( empty( $current_height ) ) {
            return new WP_Error( 'service', 'Could not get the current blockchain height from JSON-RPC. (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
        }

        return $current_height->result;
    }

    /**
     * Loads a transaction from the node
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash ) {
        if ( !ctype_xdigit( $transaction_hash ) ) {
            return new WP_Error( 'connection', 'Invalid transaction hash' );
        }

        $call = '{"jsonrpc":"2.0","method":"getTransactionByHash","params":["' . $transaction_hash . '"],"id":42}';

        $api_response = wp_remote_post( $this->api_domain, array( 'body' => $call ) );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $response = json_decode( $api_response[ 'body' ] );

        if ( $response->error ) {
            return new WP_Error( 'service', 'JSON-RPC replied: ' . $response->error->message );
        }

        if ( empty( $response ) ) {
            return new WP_Error( 'service', 'Could not retrieve transaction information from JSON-RPC. (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
        }

        $this->transaction = $response->result;
    }

    /**
     * Returns if transaction was found or not
     * @return {boolean}
     */
    public function transaction_found() {
        return !empty( $this->transaction );
    }

    /**
     * Returns any error that the node returned
     * @return {string|false}
     */
    public function error() {
        return $this->transaction->error ? $this->transaction->error->message : false;
    }

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address() {
        return $this->transaction->fromAddress;
    }

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address() {
        return $this->transaction->toAddress;
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
        if ( $this->transaction->data === null ) {
            return '';
        }

        $extraData = hex2bin( $this->transaction->data );
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

$service = new WC_Gateway_Nimiq_Service_Jsonrpc( $gateway );
