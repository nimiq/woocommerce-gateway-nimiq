<?php

include_once( dirname( dirname( __FILE__ ) ) . '/nimiq-xpub/vendor/autoload.php' );

use Nimiq\XPub;

class Address_Deriver {
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }

    public function get_next_address( $currency ) {
        if ( $currency === 'nim' ) return null; // TODO: Throw here instead?

        $reuse_eth_addresses = $this->gateway->get_option( 'reuse_eth_addresses', 'no' );

        // Derive a new address for BTC and, if the re-use setting is disabled, for ETH
        if ( $currency === 'btc' || ( $currency === 'eth' && $reuse_eth_addresses == 'no' ) ) {
            return $this->derive_new_address( $currency );
        }

        // Search for a re-usable address

        // 1. Find all orders that
        //      - have an 'order_eth_address' (an ETH address was already assigned)
        //      - have no 'transaction_hash' (no transaction was matched yet)
        //      - are still 'pending' (order is still waiting for a matching transaction, e.g. was not cancelled)
        $posts = get_posts( [
            'post_type'   => 'shop_order',
            'post_status' => [ 'wc-pending' ],
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'order_eth_address',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => 'transaction_hash',
                    'compare' => 'NOT EXISTS',
                    'value' => 'bug #23268', // For Wordpress < 3.9, just in case
                ],
            ],
        ] );

        if ( empty( $posts ) ) {
            // Use the first address
            return $this->derive_new_address( $currency, 0 );
        };

        // 2. Find the first derived address that is not used in any of the above orders
        $index = 0;
        $numderive = 5;
        while (true) {
            $indices = range($index, $index + $numderive - 1, 1); // Track indices of the derived addresses
            $addresses = $this->derive_addresses( $currency, $index, $numderive );

            // Remove all currently used addresses from the derived $addresses
            foreach ( $posts as $post ) {
                $order = new WC_Order( (int) $post->ID );
                $i = array_search( $order->get_meta( 'order_eth_address' ), $addresses );
                if ( $i !== false ) {
                    array_splice( $indices, $i, 1 );
                    array_splice( $addresses, $i, 1 );
                }
            }

            // If unused addresses are found, return the first
            if ( count( $addresses ) > 0 ) {
                $this->maybe_update_address_index( $currency, $indices[ 0 ] );
                return $addresses[ 0 ];
            }

            // If no address of this batch is unused, increase the start index and rerun the loop
            $index += $numderive;
        }
    }

    private function maybe_update_address_index( $currency, $index, $current_index = null ) {
        $current_index = is_int( $current_index )
            ? $current_index
            : $this->gateway->get_option( 'current_address_index_' . $currency, -1 );

        if ( $index > $current_index ) {
            $this->gateway->update_option( 'current_address_index_' . $currency, $index );
        }
    }

    public function derive_new_address( $currency, $index = null ) {
        $current_index = null;

        if ( !is_int( $index ) ) {
            $current_index = $this->gateway->get_option( 'current_address_index_' . $currency, -1 );
            $index = $current_index + 1;
        }

        $address = $this->derive_addresses( $currency, $index, 1 )[ 0 ];

        if ( !empty( $address ) ) {
            $this->maybe_update_address_index( $currency, $index, $current_index );
        }

        return $address;
    }

    public function derive_addresses( $currency, $startindex, $numderive ) {
        $qualified_currency_name = Crypto_Manager::iso_to_name( $currency );

        $xpub = $this->gateway->get_option( $qualified_currency_name . '_xpub' );

        if ( empty( $xpub ) ) {
            return null;
        }

        // Path 'm/0' from account xpub to external address space (BIP-44 for both BTC and ETH)
        $xpub_0 = XPub::fromString( $xpub )->derive( 0 );

        $addresses = [];

        for ($i = $startindex; $i < $startindex + $numderive; $i++) {
            $addresses[] = $xpub_0->derive( $i )->toAddress( $currency );
        }

        return $addresses;
    }
}
