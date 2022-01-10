<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Validation_Service_Etherscan implements WC_Gateway_Nimiq_Validation_Service_Interface {
    // Constants
    const API_TX_PER_PAGE = 25;

    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->payment_state = null;
        $this->transactions = [];
        $this->page = 0;
        $this->last_api_call_time = null;

        $this->api_key = $gateway->get_option( 'etherscan_api_key' );
        $this->api_url = $gateway->get_option( 'network' ) === 'main'
            ? 'https://api.etherscan.io/api'
            : 'https://api-ropsten.etherscan.io/api';
    }

    /**
     * Loads a transaction from the service
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @param {WP_Order} $order
     * @return {'NOT_FOUND'|'PAID'|'OVERPAID'|'UNDERPAID'|WP_Error}
     */
    public function load_transaction( $transaction_hash, $order, $gateway ) {
        // Reset loaded transaction
        $this->transaction = null;
        $this->payment_state = null;

        $recipient_address = null;

        if ( !empty( $transaction_hash ) ) {
            if ( !ctype_xdigit( str_replace( '0x', '', $transaction_hash ) ) ) {
                return new WP_Error('service', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
            }
        }

        $recipient_address = Order_Utils::get_order_recipient_address( $order, $gateway );
        if ( !ctype_xdigit( str_replace( '0x', '', $recipient_address ) ) ) {
            return new WP_Error('service', __( 'Invalid merchant address.', 'wc-gateway-nimiq' ) );
        }

        // Fake result for the first while loop iteration
        $response = (object) [ 'result' => [] ];
        $page = 0;

        if ( !$this->has_unique_order_address( $order ) ) {
            $response->result = $this->transactions;
            $page = $this->page;
        }

        while ( !$this->transaction ) {
            $transaction = $this->find_transaction( $transaction_hash, $recipient_address, $order, $response->result );
            if ( $transaction && $this->payment_state ) {
                $this->transaction = $transaction;
                if ( empty( $transaction_hash ) ) {
                    // Store tx hash in order
                    $order->update_meta_data( 'transaction_hash', $transaction->hash );
                    $order->update_meta_data( 'nc_payment_state', $this->payment_state );
                    $order->save();
                }
                return $this->payment_state;
            }

            if ( $this->payment_state === 'UNDERPAID' ) return $this->payment_state;

            // Stop when no more transactions are available
            // ($page is set to -1 below, when a paged API call returns less than API_TX_PER_PAGE transactions)
            if ( $page < 0 ) {
                return 'NOT_FOUND';
            }

            // Stop when earlierst transaction is earlier than the order date
            $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
            if ( end( $response->result ) && end( $response->result )->timeStamp < $order_date ) {
                return 'NOT_FOUND';
            }

            // Etherscan API has a rate-limit of 5 requests/second, so we need to sleep for 200ms between requests
            if ( $this->last_api_call_time ) {
                $diff = $this->get_milliseconds() - $this->last_api_call_time;
                $wait = max( 0, 200 - $diff );
                usleep( $wait * 1e3 );
            }

            $page += 1;
            $this->last_api_call_time = $this->get_milliseconds();
            $api_response = wp_remote_get( $this->get_url_transactions_by_address( $recipient_address, $page ) );

            if ( is_wp_error( $api_response ) ) {
                return $api_response;
            }

            $response = json_decode( $api_response[ 'body' ] );

            if ( $response->status !== '1' || $response->message !== 'OK' ) {
                // Etherscan API returns {status: 0, message: "No transactions found", result: []} when the requested page
                // has no entries anymore. In this case we don't want to return an error, but simply stop querying new pages.
                if ( $response->message !== 'No transactions found' ) {
                    return new WP_Error( 'service', $response->message );
                }
            }

            // Check number of returned transactions
            if ( count( $response->result ) < self::API_TX_PER_PAGE ) {
                $page = -1; // Signal that all transactions have been fetched
            }

            if ( !$this->has_unique_order_address( $order ) ) {
                // Cache API results
                $this->add_transactions( $response->result );
                $this->page = $page;
            }
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
     * Returns any error that the service returned
     * @return {string|false}
     */
    public function error() {
        if ( empty( $this->transaction ) ) {
            return sprintf( __( 'Could not retrieve transaction information from %s.', 'wc-gateway-nimiq' ), 'Etherscan' );
        }
        return $this->transaction->error || false;
    }

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address() {
        return $this->transaction->from;
    }

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address() {
        return $this->transaction->to;
    }

    /**
     * Returns the value of the transaction in the smallest unit
     * @return {string}
     */
    public function value() {
        return $this->transaction->value;
    }

    /**
     * Returns the data (message) of the transaction in plain text
     * @return {string}
     */
    public function message() {
        $input = str_replace( '0x', '', $this->transaction->input );
        $extraData = hex2bin( $input );
        return mb_convert_encoding( $extraData, 'UTF-8' );
    }

    /**
     * Returns the height of the block containing the transaction
     * @return {number}
     */
    public function block_height() {
        return intval( $this->transaction->blockNumber );
    }

    /**
     * Returns the confirmations of the transaction
     * @return {number}
     */
    public function confirmations() {
        return intval( $this->transaction->confirmations );
    }

    private function get_milliseconds() {
        return ceil( microtime(true) * 1e3 );
    }

    private function has_unique_order_address( $order ) {
        return !empty( $order->get_meta( 'order_eth_address' ) );
    }

    private function find_transaction( $transaction_hash, $recipient_address, $order, $transactions ) {
        $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
        foreach ( $transactions as $tx ) {
            if ( $tx->hash === $transaction_hash ) {
                $this->payment_state = $order->get_meta( 'nc_payment_state' ) ?: 'PAID';
                return $tx;
            }
            // Check that tx is not too old
            if ($tx->timeStamp < $order_date) return null;
            if ( empty( $transaction_hash ) ) {
                if ( $tx->to === $recipient_address ) {
                    $comparison = Crypto_Manager::unit_compare( $tx->value, Order_Utils::get_order_total_crypto( $order ) );
                    $this->payment_state = Order_Utils::get_payment_state( $comparison );
                    if ( $comparison >= 0 ) return $tx;
                }
            }
        }
        return null;
    }

    private function add_transactions( $transactions ) {
        foreach ( $transactions as $tx ) {
            $this->transactions[ $tx->hash ] = $tx;
        }
    }

    private function get_url_transactions_by_address( string $address, int $page = 1 ) {
        $query = '?module=account&action=txlist&startblock=0&endblock=99999999&sort=desc';
        $query .= '&page=' . $page;
        $query .= '&offset=' . self::API_TX_PER_PAGE;
        $query .= '&address=' . $address;
        return $this->makeUrl( $query );
    }

    private function makeUrl( $query ) {
        if ( empty( $this->api_key ) ) {
            throw new Exception( __( 'Etherscan API key not set.', 'wc-gateway-nimiq' ) );
        }

        return $this->api_url . $query . '&apikey=' . $this->api_key;
    }
}

$services['eth'] = new WC_Gateway_Nimiq_Validation_Service_Etherscan( $gateway );
