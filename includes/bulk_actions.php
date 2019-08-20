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
		$redirect_to = add_query_arg( 'errors', implode( '--' , $validation_results[ 'errors' ] ), $redirect_to );
	}

	wp_redirect( esc_url_raw( $redirect_to ) );
}

function _do_bulk_validate_transactions( $gateway, $ids ) {

	$count_orders_updated = 0;
	$errors = array();

	// Init validation service
	$service_slug = $gateway->get_option( 'validation_service' );
	include_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'validation_services' . DIRECTORY_SEPARATOR . $service_slug . '.php' );

	// Get current blockchain height
	$current_height = $service->blockchain_height();
	if ( is_wp_error( $current_height ) ) {
		return [ 'changed' => $count_orders_updated, 'errors' => [ $current_height->get_error_message() ] ];
	}

	foreach ( $ids as $postID ) {
		if ( !is_numeric( $postID ) ) {
			continue;
		}

		$order = new WC_Order( (int) $postID );

		// Only continue if payment method is this plugin
		if ( $order->get_payment_method() !== $gateway->id ) continue;

		// Only continue if order status is currently 'on hold'
		if ( $order->get_status() !== 'on-hold' ) continue;

		$transaction_hash = $order->get_meta('transaction_hash');

		$is_loaded = $service->load_transaction( $transaction_hash, $order, $gateway );
		if ( is_wp_error( $is_loaded ) ) {
			$errors[] = $is_loaded->get_error_message();
			continue;
		}

		// TODO: Obsolete when API returns mempool transactions
		if ( !$service->transaction_found() ) {
			// Check if order date is earlier than setting(tx_wait_duration) ago
			$order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
			$time_limit = strtotime( '-' . $gateway->get_option( 'tx_wait_duration' ) . ' minutes' );
			if ( $order_date < $time_limit ) {
				// If order date is earlier, mark as failed
				fail_order( $order, __( 'Transaction not found within mempool wait duration.', 'wc-gateway-nimiq' ) );
				$count_orders_updated++;
			}

			continue;
		}
		elseif ( $service->error() ) {
			$errors[] = $service->error();
			continue;
		}

		// If a tx is returned, validate it

		if ( $service->sender_address() !== $order->get_meta('customer_nim_address') ) {
			fail_order( $order, __( 'Transaction sender does not match.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		if ( $service->recipient_address() !== $gateway->get_option( 'nimiq_address' ) ) {
			fail_order( $order, __( 'Transaction recipient does not match.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		$order_total_crypto = get_order_total_crypto( $order );
		if ( $service->value() !== $order_total_crypto ) {
			fail_order( $order, __( 'Transaction value does not match.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		// Validate transaction data to include correct shortened order hash
		$message = $service->message();
		// Look for the last pair of round brackets in the tx message
		preg_match_all( '/.*\((.*?)\)/', $message, $matches, PREG_SET_ORDER );
		$tx_order_hash = end( $matches )[1];
		$order_hash = $order->get_meta('order_hash');
		$order_hash = strtoupper( $gateway->get_short_order_hash( $order_hash ) );
		if ( $tx_order_hash !== $order_hash ) {
			fail_order( $order, __( 'Transaction order hash does not match.', 'wc-gateway-nimiq' ) );
			$count_orders_updated++;
			continue;
		}

		// Check if transaction is 'confirmed' yet according to confirmation setting
		if ( empty( $service->block_height() ) || $service->confirmations() < $gateway->get_option( 'confirmations' ) ) {
			// Transaction valid but not yet confirmed
			continue;
		}

		// Mark as 'processing' when confirmed
		$order->update_status( 'processing', __( 'Transaction validated and confirmed.', 'wc-gateway-nimiq' ), false );
		$count_orders_updated++;

	} // end foreach loop

	return [ 'changed' => $count_orders_updated, 'errors' => $errors ];

} // end _do_bulk_validate_transactions()

function get_order_currency( $order ) {
	return $order->get_meta('order_crypto_currency') || 'nim';
}

function get_order_total_crypto( $order ) {
	// 1. Get order crypto currency
	$currency = get_order_currency( $order );

	// 2. Get order crypto total
	$order_total = $order->get_meta( 'order_total' . $currency );

	// 3. Convert to smallest unit string
	// 3.1. Split by decimal dot
	$split = explode( '.', $order_total, 2 );
	$integers = $split[0];
	$decimals = $split[1] || '';

	// 3.2. Extend decimals with 0s until crypto-specific decimals is reached
	$pad_length = [
		'nim' => 5,
		'btc' => 8,
		'eth' => 18,
	][ $currency ];
	$decimals = str_pad( $decimals, $pad_length, '0', STR_PAD_RIGHT );

	// 3.3. Join integers with decimals to create value string
	return implode( '', [ $integers, $decimals ] );
}

function fail_order($order, $reason) {
	$order->update_status( 'failed', $reason, false );

	// Restock inventory
	$line_items = $order->get_items();
	foreach ( $line_items as $item_id => $item ) {
		$product = $item->get_product();
		if ( $product && $product->managing_stock() ) {
			$old_stock = $product->get_stock_quantity();
			$new_stock = wc_update_product_stock( $product, $item['qty'], 'increase' );
			$order->add_order_note( sprintf(
				__( '%1$s (%2$s) stock increased from %3$s to %4$s.', 'woocommerce' ),
				$product->get_name(),
				$product->get_sku(),
				$old_stock,
				$new_stock
			) );
		}
	}
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

	$count_orders_updated = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;

	$errors = isset( $_REQUEST['errors'] ) ? explode( '--', wc_clean( $_REQUEST['errors'] ) ) : [];
	$errors = array_filter( $errors );

	if ( count( $errors ) > 0 ) {
		foreach( $errors as $error ) {
			echo '<div class="error notice"><p><strong>ERROR:</strong> ' . $error . '</p></div>';
		}
	}

	echo '<div class="updated notice"><p>' . sprintf( _n( 'Updated %s order', 'Updated %s orders', $count_orders_updated, 'wc-gateway-nimiq' ), $count_orders_updated ) . '.</p></div>';
}
