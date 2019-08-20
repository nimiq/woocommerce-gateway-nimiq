<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Service_JsonRpcNimiq implements WC_Gateway_Nimiq_Validation_Service_Interface {
    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @®eturn {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->head_height = null;

        $this->api_domain = $gateway->get_option( 'jsonrpc_nimiq_url' );
        if ( empty( $this->api_domain ) ) {
            throw new Exception( __( 'API URL not set.', 'wc-gateway-nimiq' ) );
        }
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        if ( !empty( $this->head_height ) ) {
            return $this->head_height;
        }

        $call = '{"jsonrpc":"2.0","method":"blockNumber","params":[],"id":42}';

        $api_response = wp_remote_post( $this->api_domain, array( 'body' => $call ) );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $block_number = json_decode( $api_response[ 'body' ] );

        if ( $block_number->error ) {
            return new WP_Error( 'service', __( 'JSON-RPC replied:', 'wc-gateway-nimiq' ) . ' ' . $block_number->error->message );
        }

        if ( empty( $block_number ) ) {
            return new WP_Error( 'service', __( 'Could not get the current blockchain height from JSON-RPC.', 'wc-gateway-nimiq' ) . ' (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
        }

        return $block_number->result;
    }

    /**
     * Loads a transaction from the node
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @param {WP_Order} $order
     * @param {WC_Gateway_Nimiq} $gateway
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash, $order, $gateway ) {
        if ( !ctype_xdigit( $transaction_hash ) ) {
            return new WP_Error( 'connection', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
        }

        if ( empty( $this->head_height ) ) {
            $this->head_height = $this->blockchain_height();
            if ( is_wp_error( $this->head_height ) ) {
                return $this->head_height;
            }
        }

        $call = '{"jsonrpc":"2.0","method":"getTransactionByHash","params":["' . $transaction_hash . '"],"id":42}';

        $username = $gateway->get_option( 'jsonrpc_nimiq_username' );
        $password = $gateway->get_option( 'jsonrpc_nimiq_password' );
        $headers = array( );

        if ( !empty( $username ) || !empty( $password ) ) {
            $authorization = 'Basic ' . base64_encode( $username . ":" . $password );
            $headers['Authorization'] = $authorization;
        }

        $api_response = wp_remote_post($this->api_domain, array(
                'headers' => $headers,
                'body' => $call,
            )
        );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $response = json_decode( $api_response[ 'body' ] );

        if ( $response->error ) {
            return new WP_Error( 'service', 'JSON-RPC replied: ' . $response->error->message );
        }

        if ( empty( $response ) ) {
            return new WP_Error( 'service', __( 'Could not retrieve transaction information from JSON-RPC.', 'wc-gateway-nimiq' ) . ' (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
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
     * Returns the value of the transaction in the smallest unit
     * @return {string}
     */
    public function value() {
        return strval( $this->transaction->value );
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

    /**
     * Returns the confirmations of the transaction
     * @return {number}
     */
    public function confirmations() {
        return $this->blockchain_height() - $this->block_height();
    }
}

$service = new WC_Gateway_Nimiq_Service_JsonRpcNimiq( $gateway );
