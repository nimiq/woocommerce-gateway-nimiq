<?php

include_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'hd-wallet-derive/vendor/autoload.php' );

use App as HDWalletDerive;

class Address_Deriver {
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }

    public function get_next_address( $currency ) {
        $last_index = $this->gateway->get_option( 'current_address_index_' . $currency, -1 );
        $new_index = $last_index + 1;

        $qualified_currency_name = Crypto_Manager::iso_to_name( $currency );

        $xpub = $this->gateway->get_option( $qualified_currency_name . '_xpub' );

        if ( empty( $xpub ) ) {
            return null;
        }

        $coin = $currency;
        if ( $currency === 'btc' && $this->gateway->get_option( 'network_btc_eth' ) === 'test' ) {
            $coin .= '-test';
        }

        // TODO: Add condition for ETH path
        $path = 'm/0'; // Electrum path (BTC)

        $params = [
            'path' => $path,
            'startindex' => $new_index,
            'numderive' => 1,
            'coin' => $coin,

            // Required defaults
            'addr-type' => 'auto',
            'includeroot' => false,
        ];

        $walletDerive = new HDWalletDerive\WalletDerive( $params );
        $address = $walletDerive->derive_keys( $xpub )[ 0 ][ 'address' ];

        $this->gateway->update_option( 'current_address_index_' . $currency, $new_index );

        return $address;
    }
}
