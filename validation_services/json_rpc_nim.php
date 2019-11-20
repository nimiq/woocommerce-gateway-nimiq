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
            return new WP_Error( 'service', sprintf( __( 'Could not get the current blockchain height from %s.', 'wc-gateway-nimiq' ), 'JSON-RPC server') . ' (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
        }

        $this->head_height = $block_number->result;
        return $this->head_height;
    }

    /**
     * Loads a transaction from the node
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @param {WP_Order} $order
     * @param {WC_Gateway_Nimiq} $gateway
     * @return {'NOT_FOUND'|'PAID'|'OVERPAID'|'UNDERPAID'|WP_Error}
     */
    public function load_transaction( $transaction_hash, $order, $gateway ) {
        $this->transaction = null;

        // Automatic transaction finding is not yet available for Nimiq
        if ( empty( $transaction_hash ) ) return 'NOT_FOUND';

        if ( !ctype_xdigit( $transaction_hash ) ) {
            return new WP_Error( 'connection', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
        }

        $head_height = $this->blockchain_height();
        if ( is_wp_error( $head_height ) ) {
            return $head_height;
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
            return new WP_Error( 'service', sprintf( __( 'Could not retrieve transaction information from %s.', 'wc-gateway-nimiq' ), 'JSON-RPC server') . ' (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
        }

        $this->transaction = $response->result;
        return $this->transaction_found() ? 'PAID' : 'NOT_FOUND';
    }

    /**
     * Returns if transaction was found or not
     * @return {boolean}
     */
    public function transaction_found() {
        return !!$this->transaction;
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
        if ( empty( $this->block_height() ) ) return 0;
        return $this->blockchain_height() + 1 - $this->block_height();
    }
}

$services['nim'] = new WC_Gateway_Nimiq_Service_JsonRpcNimiq( $gateway );
