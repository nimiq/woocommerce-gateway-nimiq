<?php

interface WC_Gateway_Nimiq_Price_Service_Interface {
	/**
	 * Initializes the validation service
	 *
	 * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
	 *
	 * @return {void}
	 */
	public function __construct( $gateway );


	/**
	 * Retrieves the current nimiq price
	 *
	 * @param {string} The currency
	 *
	 * @return {float|WP_Error}
	 */
	public function getCurrentPrice( $currency );
}
