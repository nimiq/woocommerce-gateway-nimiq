<?php

interface WC_Gateway_Nimiq_Service_Interface {
    /**
     * Initializes the validation service
     * @param {WC_Gateway_Nimiq} $gateway - A WC_Gateway_Nimiq class instance
     * @return {void}
     */
    public function __construct( $gateway );

    /**
     * Loads a transaction from the service
     * @param {string} $transaction_hash - Transaction hash as HEX string
     * @return {void|WP_Error}
     */
    public function load_transaction( $transaction_hash );

    /**
     * Returns if transaction was found or not
     * @return {boolean}
     */
    public function transaction_found();

    /**
     * Returns any error that the service returned
     * @return {string|false}
     */
    public function error();

    /**
     * Returns the userfriendly address of the transaction sender
     * @return {string}
     */
    public function sender_address();

    /**
     * Returns the userfriendly address of the transaction recipient
     * @return {string}
     */
    public function recipient_address();

    /**
     * Returns the value of the transaction in Luna
     * @return {number}
     */
    public function value();

    /**
     * Returns the data (message) of the transaction in plain text
     * @return {string}
     */
    public function message();

    /**
     * Returns the height of the block containing the transaction
     * @return {number}
     */
    public function block_height();

    /**
     * Returns the confirmations of the transaction
     * @return {number}
     */
    public function confirmations();
}
