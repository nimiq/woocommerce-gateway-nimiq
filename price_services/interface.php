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
     * @return {{[iso: string]: number]}}
     */
    public function get_prices( $crypto_currencies, $shop_currency, $order_amount );
}
