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

	// Get current blockchain height
	$current_height = wp_remote_get( $gateway->api_domain . '/latest/1' );
	if ( is_wp_error( $current_height ) ) {
		$errors[] = $current_height->errors[ 0 ];
		return [ 'changed' => $count_orders_updated, 'errors' => $errors ];
	}
	$current_height = json_decode( $current_height[ 'body' ] );
	if ( $current_height->error ) {
		$errors[] = $current_height->error;
		return [ 'changed' => $count_orders_updated, 'errors' => $errors ];
	}
	$current_height = $current_height[ 0 ]->height;

	if ( empty( $current_height ) ) {
		$errors[] = 'Could not get the current blockchain height from the API.';
		return [ 'changed' => $count_orders_updated, 'errors' => $errors ];
	}
	// echo "Current height: " . $current_height . "\n";

	foreach ( $ids as $postID ) {
		if ( !is_numeric( $postID ) ) {
			continue;
		}

		// echo "Post ID: " . $postID . "\n";

		$order = new WC_Order( (int) $postID );

		// echo "Post status: " . $order->get_status() . "\n";
		// Only continue if order status is currently 'on hold'
		if ( $order->get_status() !== 'on-hold' ) continue;

		// Convert HEX tx hash into base64
		$transaction_hash = $order->get_meta('transaction_hash');
		$transaction_hash = urlencode( base64_encode( pack( 'H*', $transaction_hash ) ) );
		// echo "Hash conversion: " . $order->get_meta('transaction_hash') . ' => ' . $transaction_hash . "\n";

		// Retrieve tx data from API
		$url = $gateway->api_domain . '/transaction/' . $transaction_hash;
		// echo "API URL: " . $url . "\n";
		$transaction = wp_remote_get( $url );
		if ( is_wp_error( $transaction ) ) {
			$errors[] = $transaction->errors[ 0 ];
			continue;
		}
		$transaction = json_decode( $transaction[ 'body' ] );

		// var_dump($transaction);

		// FIXME: Obsolete when API returns mempool transactions
		if ( $transaction->error === 'Transaction not found' ) {
			// echo "ERROR: Transaction not found\n";
			// Check if order date is earlier than setting(tx_wait_duration) ago
			$order_date = $order->get_data()[ 'date_created' ]->getTimestamp();
			// echo "Order date: " . $order_date . "\n";

			$time_limit = strtotime( '-' . $gateway->get_option( 'tx_wait_duration' ) . ' minutes' );
			// echo "Time limit: " . $time_limit . "\n";
			if ( $order_date < $time_limit ) {
				// If order date is earlier, mark as failed
				// echo "Tx not found => order failed\n";
				fail_order( $order, 'Transaction not found within wait duration.', true );
				$count_orders_updated++;
			}

			continue;
		}
		elseif ( $transaction->error ) {
			$errors[] = $transaction->error . " ($url)";
			continue;
		}
		elseif ( empty( $transaction ) ) {
			$errors[] = 'Could not retrieve transaction information ' . "($url)";
			continue;
		}

		// If tx is returned, validate it

		// echo "Transaction sender: " . $transaction->sender_address . "\n";
		// echo "Order sender:       " . $order->get_meta('customer_nim_address') . "\n";
		if ( $transaction->sender_address !== $order->get_meta('customer_nim_address') ) {
			// echo "Transaction sender not equal order customer NIM address\n";
			fail_order( $order, 'Transaction sender does not match.', true );
			$count_orders_updated++;
			continue;
		}
		// echo "OK Transaction sender matches\n";

		// echo "Transaction recipient: " . $transaction->receiver_address . "\n";
		// echo "Store address:         " . $gateway->get_option( 'nimiq_address' ) . "\n";
		if ( $transaction->receiver_address !== $gateway->get_option( 'nimiq_address' ) ) {
			// echo "Transaction recipient not equal store NIM address\n";
			fail_order( $order, 'Transaction recipient does not match.', true );
			$count_orders_updated++;
			continue;
		}
		// echo "OK Transaction recipient matches\n";

		// echo "Transaction value: " . $transaction->value . "\n";
		// echo "Order value:       " . intval( $order->get_data()[ 'total' ] * 1e5 ) . "\n";
		if ( $transaction->value !== intval( $order->get_data()[ 'total' ] * 1e5 ) ) {
			// echo "Transaction value and order value are not equal\n";
			fail_order( $order, 'Transaction value does not match.', true );
			$count_orders_updated++;
			continue;
		}
		// echo "OK Transaction value matches\n";

		// echo "Transaction data: " . $transaction->data . "\n";
		$extraData = base64_decode( $transaction->data );
		$message = mb_convert_encoding( $extraData, 'UTF-8' );
		// echo "Transaction message: " . $message . "\n";
		preg_match_all( '/.*\((.*?)\)/', $message, $matches, PREG_SET_ORDER );
		$tx_order_hash = end( $matches )[1];
		// echo "Transaction order hash: " . $tx_order_hash . "\n";
		$order_hash = $order->get_meta('order_hash');
		$order_hash = strtoupper( $gateway->get_short_order_hash( $order_hash ) );
		// echo "Order hash: " . $order_hash . "\n";
		if ( $tx_order_hash !== $order_hash ) {
			// echo "Transaction order hash and order hash are not equal\n";
			fail_order( $order, 'Transaction order hash does not match.', true );
			$count_orders_updated++;
			continue;
		}
		// echo "OK Transaction order hash matches\n";

		// Mark as 'processing' if confirmed
		// echo "Transaction height: " . $transaction->block_height . "\n";
		// echo "Confirmations setting: " . $gateway->get_option( 'confirmations' ) . "\n";
		// echo "Transaction confirmations: " . ($current_height - $transaction->block_height) . "\n";
		if ( empty( $transaction->block_height ) || $current_height - $transaction->block_height < $gateway->get_option( 'confirmations' ) ) {
			// echo "Transaction valid but not yet confirmed\n";
			continue;
		}
		// echo "OK Transaction confirmed\n";

		$order->update_status( 'processing', 'Transaction validated and confirmed.', true );
		$count_orders_updated++;
	} // end for loop

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
