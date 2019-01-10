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
	$actions[ 'validate_transactions' ] = 'Validate Transactions';
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

	// Init backend
	$backend_slug = $gateway->get_option( 'backend' );
	include_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'backends' . DIRECTORY_SEPARATOR . $backend_slug . '.php' );

	// Get current blockchain height
	$current_height = $backend->blockchain_height();
	if ( is_wp_error( $current_height ) ) {
		return [ 'changed' => $count_orders_updated, 'errors' => [ $current_height->get_error_message() ] ];
	}

	foreach ( $ids as $postID ) {
		if ( !is_numeric( $postID ) ) {
			continue;
		}

		$order = new WC_Order( (int) $postID );

		// Only continue if order status is currently 'on hold'
		if ( $order->get_status() !== 'on-hold' ) continue;

		$transaction_hash = $order->get_meta('transaction_hash');

		$is_loaded = $backend->load_transaction( $transaction_hash );
		if ( is_wp_error( $is_loaded ) ) {
			$errors[] = $is_loaded->get_error_message();
			continue;
		}

		// TODO: Obsolete when API returns mempool transactions
		if ( !$backend->transaction_found() ) {
			// Check if order date is earlier than setting(tx_wait_duration) ago
			$order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
			$time_limit = strtotime( '-' . $gateway->get_option( 'tx_wait_duration' ) . ' minutes' );
			if ( $order_date < $time_limit ) {
				// If order date is earlier, mark as failed
				fail_order( $order, 'Transaction not found within wait duration.', true );
				$count_orders_updated++;
			}

			continue;
		}
		elseif ( $backend->error ) {
			$errors[] = $backend->error;
			continue;
		}

		// If a tx is returned, validate it

		if ( $backend->sender_address !== $order->get_meta('customer_nim_address') ) {
			fail_order( $order, 'Transaction sender does not match.', true );
			$count_orders_updated++;
			continue;
		}

		if ( $backend->recipient_address !== $gateway->get_option( 'nimiq_address' ) ) {
			fail_order( $order, 'Transaction recipient does not match.', true );
			$count_orders_updated++;
			continue;
		}

		if ( $backend->value !== intval( $order->get_data()[ 'total' ] * 1e5 ) ) {
			fail_order( $order, 'Transaction value does not match.', true );
			$count_orders_updated++;
			continue;
		}

		// Validate transaction data to include correct shortened order hash
		$message = $backend->message;
		// Look for the last pair of round brackets in the tx message
		preg_match_all( '/.*\((.*?)\)/', $message, $matches, PREG_SET_ORDER );
		$tx_order_hash = end( $matches )[1];
		$order_hash = $order->get_meta('order_hash');
		$order_hash = strtoupper( $gateway->get_short_order_hash( $order_hash ) );
		if ( $tx_order_hash !== $order_hash ) {
			fail_order( $order, 'Transaction order hash does not match.', true );
			$count_orders_updated++;
			continue;
		}

		// Check if transaction is 'confirmed' yet according to confirmation setting
		if ( empty( $backend->block_height ) || $current_height - $backend->block_height < $gateway->get_option( 'confirmations' ) ) {
			// Transaction valid but not yet confirmed
			continue;
		}

		// Mark as 'processing' when confirmed
		$order->update_status( 'processing', 'Transaction validated and confirmed.', true );
		$count_orders_updated++;

	} // end foreach loop

	return [ 'changed' => $count_orders_updated, 'errors' => $errors ];

} // end _do_bulk_validate_transactions()

function fail_order($order, $reason, $is_manual_change) {
	$order->update_status( 'failed', $reason, $is_manual_change );

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

	echo '<div class="updated notice"><p>' . _n( $count_orders_updated . ' order updated.', $count_orders_updated . ' orders updated.', $count_orders_updated, 'woocommerce' ) . '</p></div>';
}
