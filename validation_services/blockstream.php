<?php
include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'interface.php' );

class WC_Gateway_Nimiq_Validation_Service_Blockstream implements WC_Gateway_Nimiq_Validation_Service_Interface {
    // Constants
    const API_TX_PER_PAGE = 25;

    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway ) {
        $this->transaction = null;
        $this->output = null;
        $this->transactions = [];
        $this->head_height = null;
        $this->last_seen_txid = null;

        $this->api_url = $gateway->get_option( 'network_btc_eth' ) === 'main'
            ? 'https://blockstream.info/api'
            : 'https://blockstream.info/testnet/api';
    }

    /**
     * Retrieves the current blockchain head height
     * @return {number|WP_Error}
     */
    public function blockchain_height() {
        if ( !empty( $this->head_height ) ) {
            return $this->head_height;
        }

        $api_response = wp_remote_get( $this->makeUrl( '/blocks/tip/height' ) );

        if ( is_wp_error( $api_response ) ) {
            return $api_response;
        }

        $latest_block = $api_response[ 'body' ];

        if ( !is_numeric( $latest_block ) ) {
            return new WP_Error( 'service', $latest_block );
        }

        if ( empty( $latest_block ) ) {
            return new WP_Error( 'service', __( 'Could not get the current blockchain height from Blockstream.', 'wc-gateway-nimiq' ) );
        }

        $this->head_height = intval( $latest_block );
        return $this->head_height;
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
        $this->output = null;

        $recipient_address = null;

        if ( !empty( $transaction_hash ) ) {
            if ( !ctype_xdigit( $transaction_hash ) ) {
                return new WP_Error('service', __( 'Invalid transaction hash.', 'wc-gateway-nimiq' ) );
            }
        }

        $recipient_address = Order_Utils::get_order_recipient_address( $order, $gateway );
        // TODO: Use https://github.com/LinusU/php-bitcoin-address-validator for Bitcoin address validation
        // if ( !AddressValidator::isValid( $recipient_address, AddressValidator::TESTNET_PUBKEY ) ) {
        //     return new WP_Error('service', __( 'Invalid merchant address.', 'wc-gateway-nimiq' ) );
        // }

        $head_height = $this->blockchain_height();
        if ( is_wp_error( $head_height ) ) {
            return $head_height;
        }

        // Fake result for the first while loop iteration
        $response = [];
        $last_seen_txid = null;

        if ( !$this->has_unique_order_address( $order ) ) {
            $response = $this->transactions;
            $last_seen_txid = $this->last_seen_txid;
        }

        while ( !$this->transaction ) {
            $transaction = $this->find_transaction( $transaction_hash, $recipient_address, $order, $response );
            if ( $transaction ) {
                $this->transaction = $transaction;
                if ( empty( $transaction_hash ) ) {
                    // Store tx hash in order
                    update_post_meta( $order->get_id(), 'transaction_hash', $transaction->txid );
                }
                foreach ( $this->transaction->vout as $output ) {
                    if ( $output->scriptpubkey_address === $recipient_address ) $this->output = $output;
                }
                return true;
            }

            // Stop when no more transactions are available
            // ($last_seen_txid is set to EOL below, when a paged API call returns less than API_TX_PER_PAGE transactions)
            if ( $last_seen_txid === 'EOL' ) {
                return false;
            }

            // Stop when earlierst transaction is earlier than the order date
            $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
            // The first call to Blockstream returns up to 50 mempool tx AND up to 25 mined tx.
            // So if the last returned tx is not mined (confirmed), there are no mined tx at all.
            if ( end( $response ) && ( !end( $response )->status->confirmed || end( $response )->status->block_time < $order_date ) ) {
                return false;
            }

            $api_response = wp_remote_get( $this->get_url_transactions_by_address( $recipient_address, $last_seen_txid ) );

            if ( is_wp_error( $api_response ) ) {
                // Protect against returning a temporary error that would trigger a request cancellation.
                if ( substr( $api_response->get_error_message(), 0, 14) === 'cURL error 28:' ) {
                    sleep(1);
                    continue; // Retry
                }

                return $api_response;
            }

            try {
                // Blockstream returns a plain text error message upon failure, so JSON parsing fails
                $response = json_decode( $api_response[ 'body' ] );
            } catch (Exception $error) {
                return new WP_Error( 'service', $api_response[ 'body' ] );
            }

            // Check number of returned transactions
            if ( count( $response ) < self::API_TX_PER_PAGE ) {
                $last_seen_txid = 'EOL'; // Signal that all transactions have been fetched
            } else {
                $last_seen_txid = end( $response )->txid;
            }

            if ( !$this->has_unique_order_address( $order ) ) {
                // Cache API results
                $this->add_transactions( $response );
                $this->last_seen_txid = $last_seen_txid;
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
            return __( 'Could not retrieve transaction information from Blockstream.', 'wc-gateway-nimiq' );
        }
        return $this->transaction->error || false;
    }

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address() {
        // $sender_addresses = [];
        // foreach ( $this->transaction->vin as $input ) {
        //     if ( $this->is_pubkey_address( $input->prevout->scriptpubkey_address ) ) {
        //         $sender_addresses[] = $input->prevout->scriptpubkey_address;
        //     }
        // }
        // return $sender_addresses;
        return '';
    }

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address() {
        return $this->output->scriptpubkey_address;
    }

    /**
     * Returns the value of the transaction in the smallest unit
     * @return {string}
     */
    public function value() {
        return strval( $this->output->value );
    }

    /**
     * Returns the data (message) of the transaction in plain text
     * @return {string}
     */
    public function message() {
        return '';
    }

    /**
     * Returns the height of the block containing the transaction
     * @return {number}
     */
    public function block_height() {
        return $this->transaction->status->block_height;
    }

    /**
     * Returns the confirmations of the transaction
     * @return {number}
     */
    public function confirmations() {
        if ( empty( $this->block_height() ) ) return 0;
        return $this->blockchain_height() + 1 - $this->block_height();
    }

    private function has_unique_order_address( $order ) {
        return !empty( $order->get_meta( 'order_btc_address' ) );
    }

    private function is_pubkey_address( $address ) {
        return strlen( $address ) === 34;
    }

    private function find_transaction( $transaction_hash, $recipient_address, $order, $transactions ) {
        $order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
        foreach ( $transactions as $tx ) {
            if ( $tx->txid === $transaction_hash ) {
                return $tx;
            }
            if ( empty( $transaction_hash ) ) {
                // Check that tx is not too old
                if ($tx->status->confirmed && $tx->status->block_time < $order_date) continue;
                // Search outputs
                foreach ( $tx->vout as $output ) {
                    if (
                        $output->scriptpubkey_address === $recipient_address &&
                        Crypto_Manager::unit_compare( strval( $output->value ), Order_Utils::get_order_total_crypto( $order ) ) >= 0
                    ) {
                        return $tx;
                    }
                }
            }
        }
        return null;
    }

    private function add_transactions( $transactions ) {
        foreach ( $transactions as $tx ) {
            $this->transactions[ $tx->txid ] = $tx;
        }
    }

    private function get_url_transactions_by_address( string $address, int $last_seen_txid = null ) {
        $path = '/address/' . $address . '/txs';
        if ( $last_seen_txid ) {
            $path .= '/chain/' . $last_seen_txid;
        }
        return $this->makeUrl( $path );
    }

    private function makeUrl( $path ) {
        return $this->api_url . $path;
    }
}

$services['btc'] = new WC_Gateway_Nimiq_Validation_Service_Blockstream( $gateway );
