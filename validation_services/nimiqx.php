<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Service_NimiqX implements WC_Gateway_Nimiq_Validation_Service_Interface {
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
        $this->head_height = null;
        $this->page = 0;

        if ( $gateway->get_option( 'network' ) !== 'main' ) {
            throw new Exception( __( 'NimiqX can only be used for mainnet.', 'wc-gateway-nimiq' ) );
        }

        $this->api_key = $gateway->get_option( 'nimiqx_api_key' );
        if ( empty( $this->api_key ) ) {
            throw new Exception( __( 'API key not set.', 'wc-gateway-nimiq' ) );
        }
        if ( !ctype_xdigit( $this->api_key ) ) {
            throw new Exception( __( 'Invalid API key.', 'wc-gateway-nimiq' ) );
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

        $api_response = wp_remote_get( $this->makeUrl( 'network-stats' ) );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $network_stats = json_decode( $api_response[ 'body' ] );

        if ( $network_stats->error ) {
            return new WP_Error( 'service', $network_stats->error );
        }

        $this->head_height = $network_stats->height;
        return $this->head_height;
    }

    /**
     * Loads a transaction from the service
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

            $api_response = wp_remote_get( $this->makeUrl( 'transaction/' . $transaction_hash ) );

            if ( is_wp_error( $api_response ) ) {
                return $api_response;
            }

            $transaction = json_decode( $api_response[ 'body' ] );

            if ( $transaction->error ) {
                return new WP_Error( 'service', $transaction->error );
            }

            $this->transaction = $transaction;
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
        $page = $this->page;

        while ( !$this->transaction ) {
            $transaction = $this->find_transaction( $recipient_address, $order, $response, $gateway );
            if ( $transaction && $this->payment_state ) {
                $this->transaction = $transaction;
                // Store tx hash in order
                $order->update_meta_data( 'transaction_hash', $transaction->hash );
                $order->update_meta_data( 'customer_nim_address', $transaction->from_address );
                $order->update_meta_data( 'nc_payment_state', $this->payment_state );
                $order->save();
                return $this->payment_state;
            }

            if ( $this->payment_state === 'UNDERPAID' ) return $this->payment_state;

            // Stop when no more transactions are available
            // ($page is set to -1 below, when a paged API call returns less than API_TX_PER_PAGE transactions)
            if ( $page < 0 ) {
                return 'NOT_FOUND';
            }

            // Stop when earliest transaction is earlier than the order date
            $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
            if ( end( $response ) && ( empty( end( $response )->timestamp ) || end( $response )->timestamp < $order_date ) ) {
                return 'NOT_FOUND';
            }

            $page += 1;
            $api_response = wp_remote_get( $this->get_url_transactions_by_address( $recipient_address, $page, self::API_TX_PER_PAGE ) );

            if ( is_wp_error( $api_response ) ) {
                return $api_response;
            }

            $response = json_decode( $api_response[ 'body' ] );

            if ( $response->error ) {
                return new WP_Error( 'service', $transaction->error );
            }

            // Check number of returned transactions
            if ( count( $response ) < self::API_TX_PER_PAGE ) {
                $page = -1; // Signal that all transactions have been fetched
            }

            // Cache API results
            $this->add_transactions( $response );
            $this->page = $page;
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
        return $this->transaction->error ?: false;
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

    /**
     * Returns the confirmations of the transaction
     * @return {number}
     */
    public function confirmations() {
        return $this->blockchain_height() + 1 - $this->block_height();
    }

    private function find_transaction( $recipient_address, $order, $transactions, $gateway ) {
        $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
        foreach ( $transactions as $tx ) {
            // Check that tx is not too old
            if (!empty( $tx->timestamp ) && $tx->timestamp < $order_date) continue;
            if ( $tx->to_address === $recipient_address ) {
                // If tx has a message, check that it matches
                $extraData = base64_decode( $tx->data );
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

    private function makeUrl( $path ) {
        return 'https://api.nimiq.cafe/' . $path . '?api_key=' . $this->api_key;
    }

    private function get_url_transactions_by_address( string $address, int $page = 1, int $limit = 10 ) {
        $path = 'account-transactions/' . $address . '/' . $limit . '/' . ( $page - 1 ) * self::API_TX_PER_PAGE;
        return $this->makeUrl( $path );
    }
}

$services['nim'] = new WC_Gateway_Nimiq_Service_NimiqX( $gateway );
