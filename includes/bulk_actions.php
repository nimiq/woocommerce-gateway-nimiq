<?php
/**
 * Register handlers for enabling the `Validate transactions' bulk action
 *
 * Displaying the admin notices for the bulk action's result is a bit tricky:
 * Because Wordpress is doing an automatic redirect after the bulk action has
 * completed, we loose every 'admin_notice' wp-action that we add before that.
 * Thus we followed WooCommerce's implementation of handing the variables
 * needed to display the notices to the redirected page via the $_REQUEST
 * object. This sets the variables in the redirect-URL sent back to the browser,
 * which then requests the page with these parameters, thus allowing our
 * admin_notices code to detect that and generate the notices.
 */

add_filter( 'bulk_actions-edit-shop_order', 'register_bulk_actions', 9);
add_filter( 'handle_bulk_actions-edit-shop_order', 'do_bulk_validate_transactions', 10, 3 );
add_action( 'admin_notices', 'handle_bulk_admin_notices_after_redirect' );

function register_bulk_actions( $actions ) {
	$actions[ 'validate_transactions' ] = __( 'Validate Transactions', 'wc-gateway-nimiq' );
	return $actions;
}

function do_bulk_validate_transactions( $redirect_to, $action, $ids ) {
	// Make sure that the correct action is submitted
	if ( $action !== 'validate_transactions' ) {
		return;
	}

	$gateway = new WC_Gateway_Nimiq();
	$validation_results = _do_bulk_validate_transactions( $gateway, $ids );

	$redirect_to = add_query_arg( 'bulk_action', 'validated_transactions', $redirect_to );
	$redirect_to = add_query_arg( 'changed', $validation_results[ 'changed' ], $redirect_to );

	if ( empty( $validation_results[ 'errors' ] ) ) {
		$redirect_to = remove_query_arg( 'errors', $redirect_to );
	}
	else {
		$redirect_to = add_query_arg( 'errors', implode( '|' , $validation_results[ 'errors' ] ), $redirect_to );
	}

	wp_redirect( esc_url_raw( $redirect_to ) );
}

function _do_bulk_validate_transactions( $gateway, $ids ) {

	$count_orders_updated = 0;
	$errors = array();

	// Init validation services
	$services = [];
	$validation_options = ['validation_service_nim', 'validation_service_btc', 'validation_service_eth'];
	foreach ($validation_options as $option) {
		$service_slug = $gateway->get_option( $option );
		include_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'validation_services' . DIRECTORY_SEPARATOR . $service_slug . '.php' );
	}

	foreach ( $ids as $postID ) {
		if ( !is_numeric( $postID ) ) {
			continue;
		}

		$order = new WC_Order( (int) $postID );

		// Only continue if payment method is this plugin
		if ( $order->get_payment_method() !== $gateway->id ) continue;

		// Only continue if order status is currently 'on hold' or 'pending'
		if ( !in_array( $order->get_status(), [ 'on-hold', 'pending' ] ) ) continue;

		$currency = Order_Utils::get_order_currency( $order, false );

		// Do not continue when no currency selected
		if ( !$currency ) continue;

		// Get currency-specific validation service
		$service = $services[ $currency ];

		$transaction_hash = $order->get_meta('transaction_hash');

		$is_loaded = $service->load_transaction( $transaction_hash, $order, $gateway );
		if ( is_wp_error( $is_loaded ) ) {
			$errors[] = $is_loaded->get_error_message();
			continue;
		}

		if ( !$service->transaction_found() || $service->confirmations() === 0 ) {
			// Check if order date is earlier than tx_wait_duration ago
			$order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
			$time_limit = strtotime( '-' . $gateway->get_setting( 'tx_wait_duration' ) . ' minutes' );
			if ( $order_date < $time_limit ) {
				// If order date is earlier, mark as failed
				fail_order( $order, __( 'Transaction was not found.', 'wc-gateway-nimiq' ) );
				$count_orders_updated++;
			}

			continue;
		}
		elseif ( $service->error() ) {
			$errors[] = $service->error();
			continue;
		}

		// If a tx is returned, validate it

		// If this is the first time we see this tx, check if the quote was already expired during the last run.
		// Since this monitoring only runs on a scheduled interval, that gives a transaction some leeway,
		// even after quote expiry.
		// Note that in the regular case, when the Hub returns successfully, a valid transaction hash is already stored
		// in the order and this case is not relevant.
		$expires = $order->get_meta( 'crypto_rate_expires' );
		$interval = $gateway->get_setting( 'validation_interval' );
		if ( $expires && empty( $transaction_hash ) && $expires < strtotime( '-' . $interval . ' minutes' ) ) {
			fail_order( $order, __( 'Transaction only found after quote expired.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		$order_sender_address = Order_Utils::get_order_sender_address( $order );
		if ( !empty( $order_sender_address ) && $service->sender_address() !== $order_sender_address ) {
			fail_order( $order, __( 'Transaction sender does not match.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		if ( $service->recipient_address() !== Order_Utils::get_order_recipient_address( $order, $gateway ) ) {
			fail_order( $order, __( 'Transaction recipient does not match.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		$order_total_crypto = Order_Utils::get_order_total_crypto( $order );
		if ( Crypto_Manager::unit_compare( $service->value(), $order_total_crypto ) < 0 ) {
			fail_order( $order, __( 'Transaction value is too small.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		if ( $currency === 'nim' ) {
			// Validate transaction data to include correct shortened order hash
			$message = $service->message();
			// Look for the last pair of round brackets in the tx message
			preg_match_all( '/.*\((.*?)\)/', $message, $matches, PREG_SET_ORDER );
			$tx_order_key = end( $matches )[1];
			if ( $tx_order_key !== $gateway->get_short_order_key( $order->get_order_key() ) ) {
				fail_order( $order, __( 'Transaction order hash does not match.', 'wc-gateway-nimiq' ) );
				$count_orders_updated++;
				continue;
			}
		}

		// Check if transaction is 'confirmed' yet according to confirmation setting
		if ( $service->confirmations() < $gateway->get_setting( 'confirmations_' . $currency ) ) {
			// Transaction valid but not yet confirmed

			if ( $order->get_status() === 'pending' ) {
				// Set order to 'on-hold', if order expires (hold_stock) before next scheduled run
				$order_expiry = Order_Utils::get_order_hold_expiry( $order );
				$interval = $gateway->get_setting( 'validation_interval' );
				if ( $order_expiry && $order_expiry < strtotime( '+' . $interval . ' minutes' ) ) {
					$order->update_status( 'on-hold', __( 'Valid transaction found, awaiting confirmation.', 'wc-gateway-nimiq' ) );
				}
			}

			continue;
		}

		// Mark payment as complete when confirmed
		$order->add_order_note( __( 'Transaction validated and confirmed.', 'wc-gateway-nimiq' ) );
		$order->payment_complete();
		$count_orders_updated++;

	} // end foreach loop

	return [ 'changed' => $count_orders_updated, 'errors' => $errors ];

} // end _do_bulk_validate_transactions()

function fail_order($order, $reason) {
	if ( $order->get_status() === 'on-hold' || $order->get_meta( 'nc_payment_state' ) === 'UNDERPAID' || !empty( $order->get_meta( 'transaction_hash' ) ) ) {
		$order->update_status( 'failed', $reason );
	} else {
		$order->update_status( 'cancelled', $reason );
	}

	// Restock inventory
	wc_maybe_increase_stock_levels( $order->get_id() );

} // end fail_order()

function handle_bulk_admin_notices_after_redirect() {
	global $pagenow, $post_type;

	if ( 'edit.php' !== $pagenow || 'shop_order' !== $post_type || ! isset( $_REQUEST['bulk_action'] ) ) {
		return;
	}

	$bulk_action = wc_clean( wp_unslash( $_REQUEST['bulk_action'] ) );

	if ( $bulk_action !== 'validated_transactions' ) {
		return;
	}

	$count_orders_updated = intval( isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0 );

	$errors = isset( $_REQUEST['errors'] ) ? explode( '|', wc_clean( $_REQUEST['errors'] ) ) : [];
	$errors = array_filter( $errors );

	if ( count( $errors ) > 0 ) {
		foreach( $errors as $error ) {
			echo '<div class="error notice"><p><strong>ERROR:</strong> ' . $error . '</p></div>';
		}
	}

	/* translators: %d: Number of updated orders */
	echo '<div class="updated notice"><p>' . sprintf( _n( 'Updated %d order', 'Updated %d orders', $count_orders_updated, 'wc-gateway-nimiq' ), $count_orders_updated ) . '.</p></div>';
}
