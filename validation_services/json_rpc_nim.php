<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Service_JsonRpcNimiq implements WC_Gateway_Nimiq_Validation_Service_Interface {
    // Constants
    const API_TX_PER_PAGE = 25;

    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @®eturn {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->payment_state = null;
        $this->transactions = [];
        $this->head_height = null;
        $this->limit = 0;

        $this->username = $gateway->get_option( 'jsonrpc_nimiq_username' );
        $this->password = $gateway->get_option( 'jsonrpc_nimiq_password' );

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

        $api_response = wp_remote_post( $this->api_domain, $this->make_request( "blockNumber" ) );

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
        // Reset loaded transaction
        $this->transaction = null;
        $this->payment_state = null;

        $recipient_address = null;

        if ( !empty( $transaction_hash ) ) {
            if ( !ctype_xdigit( $transaction_hash ) ) {
                return new WP_Error('service', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
            }

            $api_response = wp_remote_post( $this->api_domain, $this->make_request( "getTransactionByHash", [ $transaction_hash ] ) );

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

        $recipient_address = Order_Utils::get_order_recipient_address( $order, $gateway );
        // TODO: NIM Address validation
        // if ( !AddressValidator::isValid( $recipient_address ) ) {
        //     return new WP_Error('service', __( 'Invalid merchant address.', 'wc-gateway-nimiq' ) );
        // }

        $head_height = $this->blockchain_height();
        if ( is_wp_error( $head_height ) ) {
            return $head_height;
        }

        // Use cached results, if any
        $response = $this->transactions;
        $limit = $this->limit;

        while ( !$this->transaction ) {
            $transaction = $this->find_transaction( $recipient_address, $order, $response, $gateway );
            if ( $transaction && $this->payment_state ) {
                $this->transaction = $transaction;
                // Store tx hash in order
                $order->update_meta_data( 'transaction_hash', $transaction->hash );
                $order->update_meta_data( 'customer_nim_address', $transaction->fromAddress );
                $order->update_meta_data( 'nc_payment_state', $this->payment_state );
                $order->save();
                return $this->payment_state;
            }

            if ( $this->payment_state === 'UNDERPAID' ) return $this->payment_state;

            // Stop when no more transactions are available
            // ($limit is set to -1 below, when a paged API call returns less than $limit transactions)
            if ( $limit < 0 ) {
                return 'NOT_FOUND';
            }

            // Stop when earliest transaction is earlier than the order date
            $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
            if ( end( $response ) && ( empty( end( $response )->timestamp ) || end( $response )->timestamp < $order_date ) ) {
                return 'NOT_FOUND';
            }

            $limit += self::API_TX_PER_PAGE;
            $api_response = wp_remote_post( $this->api_domain, $this->get_request_transactions_by_address( $recipient_address, $limit ) );

            if ( is_wp_error( $api_response ) ) {
                return $api_response;
            }

            $response = json_decode( $api_response[ 'body' ] );

            if ( $response->error ) {
                return new WP_Error( 'service', 'JSON-RPC replied: ' . $response->error->message );
            }

            if ( empty( $response ) ) {
                return new WP_Error( 'service', sprintf( __( 'Could not retrieve account transactions from %s.', 'wc-gateway-nimiq' ), 'JSON-RPC server') . ' (' . $api_response[ 'response' ][ 'code' ] . ': ' . $api_response[ 'response' ][ 'message' ] . ')' );
            }

            $response = $response->result;

            function cmp($a, $b) {
                if ($a->timestamp === $b->timestamp) return 0;
                if (!$a->timestamp) return -1;
                if (!$b->timestamp) return 1;
                return ($a->timestamp > $b->timestamp) ? -1 : 1;
            }
            usort( $response, "cmp");

            // Check number of returned transactions
            if ( count( $response ) < $limit ) {
                $limit = -1; // Signal that all transactions have been fetched
            }

            // Cache API results
            $this->add_transactions( $response );
            $this->limit = $limit;
        }
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
        $extraData = hex2bin( $this->transaction->data ?: '' );
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
        return $this->transaction->confirmations;
    }

    private function find_transaction( $recipient_address, $order, $transactions, $gateway ) {
        $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
        foreach ( $transactions as $tx ) {
            // Check that tx is not too old
            if (!empty( $tx->timestamp ) && $tx->timestamp < $order_date) continue;
            if ( $tx->toAddress === $recipient_address ) {
                // If tx has a message, check that it matches
                $extraData = hex2bin( $tx->data ?: '' );
                $message = mb_convert_encoding( $extraData, 'UTF-8' );
                if ( !empty( $message ) ) {
                    // Look for the last pair of round brackets in the tx message
                    preg_match_all( '/.*\((.*?)\)/', $message, $matches, PREG_SET_ORDER );
                    $tx_order_key = end( $matches )[1];
                    if ( $tx_order_key !== $gateway->get_short_order_key( $order->get_order_key() ) ) {
                        continue;
                    }
                }

                $comparison = Crypto_Manager::unit_compare( $tx->value, Order_Utils::get_order_total_crypto( $order ) );
                $this->payment_state = Order_Utils::get_payment_state( $comparison );
                if ( $comparison >= 0 ) return $tx;
            }
        }
        return null;
    }

    private function add_transactions( $transactions ) {
        foreach ( $transactions as $tx ) {
            $this->transactions[ $tx->hash ] = $tx;
        }
    }

    private function make_request( $method, $params = [] ) {
        $headers = array(
            // Need to set referer to emtpy string, as Wordpress sets it to the site domain,
            // but the Nimiq JsonRpcServer rejects the request (403) when a referer is set.
            'Referer' => '',
        );

        if ( !empty( $this->username ) || !empty( $this->password ) ) {
            $authorization = 'Basic ' . base64_encode( $this->username . ":" . $this->password );
            $headers['Authorization'] = $authorization;
        }

        $call = json_encode([
            'jsonrpc' => "2.0",
            'method' => $method,
            'params' => $params,
            'id' => 42,
        ]);

        return array(
            'headers' => $headers,
            'body' => $call,
        );
    }

    private function get_request_transactions_by_address( string $address, int $limit ) {
        return $this->make_request( "getTransactionsByAddress", [ $address, $limit ] );
    }
}

$services['nim'] = new WC_Gateway_Nimiq_Service_JsonRpcNimiq( $gateway );
