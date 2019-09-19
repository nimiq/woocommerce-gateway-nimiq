<?php

interface WC_Gateway_Nimiq_Price_Service_Interface {
    /**
     * Initializes the validation service
     *
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway );

    /**
     * @param {string[]} $crypto_currencies
     * @param {string} $shop_currency
     * @param {number} $order_amount
     * @return {[
     *     'prices'? => [[iso: string]: number]],
     *     'quotes'? => [[iso: string]: number]],
     *     'fees'? => [[iso: string]: number | ['gas_limit' => number, 'gas_price' => number]],
     * ]} - Must include either prices or quotes, may include fees
     */
    public function get_prices( $crypto_currencies, $shop_currency, $order_amount );
}
