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
        $this->transactions = [];
        $this->page = 0;
        $this->last_api_call_time = null;

        $this->api_key = $gateway->get_option( 'etherscan_api_key' );
        if ( empty( $this->api_key ) ) {
            throw new Exception( __( 'API key not set.', 'wc-gateway-nimiq' ) );
        }
    }

    /**
     * Loads a transaction from the service
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @param {WP_Order} $order
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash, $order, $gateway ) {
        // Reset loaded transaction
        $this->transaction = null;

        $recipient_address = null;

        if ( !empty( $transaction_hash ) ) {
            if ( !ctype_xdigit( $transaction_hash ) ) {
                return new WP_Error('service', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
            }
        } else {
            $recipient_address = $order->get_meta( 'order_eth_address' ) || $gateway->get_option( 'ethereum_address' );
            if ( !ctype_xdigit( $recipient_address ) ) {
                return new WP_Error('service', __( 'Invalid merchant address.', 'wc-gateway-nimiq' ) );
            }
        }

        // Fake result for the first while loop iteration
        if ( $this->has_unique_order_address( $order ) ) {
            $response = [ 'result' => [] ];
            $page = 0;
        } else {
            $response = [ 'result' => $this->transactions ];
            $page = $this->page;
        }

        while ( !$this->transaction ) {
            $transaction = $this->find_transaction( $transaction_hash, $recipient_address, $response->result );
            if ( $transaction ) {
                $this->transaction = $transaction;
                return true;
            }

            if ( !$transaction ) {
                // Stop when no more transactions are available
                // ($page is set to -1 below, when a paged API call returns less than API_TX_PER_PAGE transactions)
                if ( $page < 0 ) {
                    return false;
                }

                // Stop when earlierst transaction is earlier than the order date
                $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
                if ( end( $response->result )->timeStamp < $order_date ) {
                    return false;
                }
            }

            // Etherscan API has a rate-limit of 5 requests/second, so we need to sleep for 200ms between requests
            if ( $this->last_api_call_time ) {
                $diff = $this->get_milliseconds() - $this->last_api_call_time;
                $wait = max( 0, 200 - $diff );
                usleep( $wait * 1e3 );
            }

            $page += 1;
            $this->last_api_call_time = $this->get_milliseconds();
            $api_response = wp_remote_get( $this->get_url_transactions_by_address( $recipient_address, $page, self::API_TX_PER_PAGE ) );

            if ( is_wp_error( $api_response ) ) {
                return $api_response;
            }

            $response = json_decode( $api_response[ 'body' ] );

            if ( $response->status !== 1 || $response->message !== 'OK' ) {
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
            return __( 'Could not retrieve transaction information from Etherscan.', 'wc-gateway-nimiq' );
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
        $extraData = hex2bin( $this->transaction->input );
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

    private function get_milliseconds() {
        return ceil( microtime(true) * 1e3 );
    }

    private function has_unique_order_address( $order ) {
        return !empty( $order->get_meta( 'order_eth_address' ) );
    }

    private function get_url_transactions_by_address( string $address, int $page = 1, int $offset = 10 ) {
        $query = 'module=account&action=txlist&startblock=0&endblock=99999999&sort=desc';
        $query .= '&page=' . $page;
        $query .= '&offset=' . $offset;
        $query .= '&address=' . $address;
        return $this->makeUrl( $query );
    }

    private function makeUrl( $query ) {
        $api = $gateway->get_option( 'network' ) === 'main'
            ? 'https://api.etherscan.io/api?'
            : 'https://api-ropsten.etherscan.io/api?';

        return $api . $query . '?apikey=' . $this->api_key;
    }
}

$services['eth'] = new WC_Gateway_Nimiq_Validation_Service_Etherscan( $gateway );
