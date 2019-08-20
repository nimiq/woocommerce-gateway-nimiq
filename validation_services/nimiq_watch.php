<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Service_Nimiqwatch implements WC_Gateway_Nimiq_Validation_Service_Interface {
    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->head_height = null;

        $this->api_domain = $gateway->get_option( 'network' ) === 'main' ? 'https://api.nimiq.watch' : 'https://test-api.nimiq.watch';
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        if ( !empty( $this->head_height ) ) {
            return $this->head_height;
        }

        $api_response = wp_remote_get( $this->api_domain . '/latest/1' );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $latest_block = json_decode( $api_response[ 'body' ] );

        if ( $latest_block->error ) {
            return new WP_Error( 'service', $latest_block->error );
        }

        if ( empty( $latest_block ) ) {
            return new WP_Error( 'service', __( 'Could not get the current blockchain height from NIMIQ.WATCH.', 'wc-gateway-nimiq' ) );
        }

        return $latest_block[ 0 ]->height;
    }

    /**
     * Loads a transaction from the service
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @param {WP_Order} $order
     * @param {WC_Gateway_Nimiq} $gateway
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash, $order, $gateway ) {
        if ( !ctype_xdigit( $transaction_hash ) ) {
            return new WP_Error('service', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
        }

        if ( empty( $this->head_height ) ) {
            $this->head_height = $this->blockchain_height();
            if ( is_wp_error( $this->head_height ) ) {
                return $this->head_height;
            }
        }

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
     * Returns any error that the service returned
     * @return {string|false}
     */
    public function error() {
        if ( empty( $this->transaction ) ) {
            return __( 'Could not retrieve transaction information from NIMIQ.WATCH.', 'wc-gateway-nimiq' );
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

    /**
     * Returns the confirmations of the transaction
     * @return {number}
     */
    public function confirmations() {
        return $this->blockchain_height() - $this->block_height();
    }
}

$services['nim'] = new WC_Gateway_Nimiq_Service_Nimiqwatch( $gateway );
