<?php
/**
 * Nimiq currency and currency symbol
 */

function add_nimiq_currency( $currencies ) {
	$currencies['NIM'] = __( 'Nimiq', 'woocommerce' );
	return $currencies;
}

add_filter( 'woocommerce_currencies', 'add_nimiq_currency' );

function add_nimiq_currency_symbol( $currency_symbol, $currency ) {
	switch( $currency ) {
		 case 'NIM': $currency_symbol = 'NIM&nbsp;'; break;
	}
	return $currency_symbol;
}

add_filter('woocommerce_currency_symbol', 'add_nimiq_currency_symbol', 10, 2);
