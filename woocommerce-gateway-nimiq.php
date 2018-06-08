<?php
/**
 * Plugin Name: WooCommerce Nimiq Gateway
 * Plugin URI:
 * Description: Pay with Nimiq via the Nimiq Keyguard
 * Author: Nimiq
 * Author URI: http://www.nimiq.com/
 * Version: 1.5.0
 * Text Domain: wc-gateway-nimiq
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2015-2016 SkyVerge, Inc. (info@skyverge.com) and WooCommerce, Nimiq Network Ltd.
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Nimiq
 * @author    Nimiq
 * @category  Admin
 * @copyright Copyright (c) 2015-2016, SkyVerge, Inc. and WooCommerce, Nimiq Network Ltd.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This Nimiq gateway forks the WooCommerce core "Cheque" payment gateway to create another payment method.
 */

defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + Nimiq gateway
 */
function wc_nimiq_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Nimiq';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_nimiq_add_to_gateways' );


/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_nimiq_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nimiq_gateway' ) . '">' . __( 'Configure', 'wc-gateway-nimiq' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_nimiq_gateway_plugin_links' );


/**
 * Nimiq Payment Gateway
 *
 * Provides a Nimiq Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Nimiq
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Nimiq
 */
add_action( 'plugins_loaded', 'wc_nimiq_gateway_init', 11 );

function wc_nimiq_gateway_init() {

	class WC_Gateway_Nimiq extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'nimiq_gateway';
			$this->icon               = "data:image/svg+xml,<svg width='499' height='440' xmlns='http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg'><path d='M389.33 20.73C382.65 9.28 366.48 0 353.24 0h-208c-13.26 0-29.4 9.28-36.09 20.73L5.33 198.87c-6.68 11.45-6.68 30.02 0 41.47l103.82 178.14c6.68 11.45 22.85 20.74 36.09 20.74h208c13.26 0 29.4-9.28 36.09-20.74l103.82-178.14c6.68-11.45 6.68-30.01 0-41.47L389.33 20.73zM272.6 347.45v41.1h-38.74v-39.53c-23.53-2.82-51.45-13.02-70.28-30.12l25.57-39.06c20.4 15.06 37.96 22.9 57.1 22.9 22.43 0 32.31-9.1 32.31-27.29 0-40.63-105.4-39.84-105.4-111.06 0-38.74 23.2-65.57 60.7-73.1V50.51h38.74v40c25.57 3.6 43.92 16.16 59.45 32.47l-29.49 33.26c-14.43-13.02-26.66-19.77-43.45-19.77-19.13 0-29.49 7.53-29.49 25.26 0 37.49 105.41 34.35 105.41 109.96-.15 37.8-21.33 67.13-62.43 75.76z' fill='%23FFC107' fill-rule='evenodd'/></svg>";
			$this->has_fields         = true;
			$this->method_title       = __( 'Nimiq', 'wc-gateway-nimiq' );
			$this->method_description = __( 'Allows Nimiq payments. Orders are marked as "on-hold" when received.', 'wc-gateway-nimiq' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			$this->api_domain   = $this->get_option( 'network' ) === 'main' ? 'https://api.nimiq.watch' : 'https://test-api.nimiq.watch';

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'admin_notices', array( $this, 'do_store_nim_address_check' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_data' ) );

			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

			// Add style, so it can be loaded in the header of the page
			wp_enqueue_style('NimiqPayment', plugin_dir_url( __FILE__ ) . 'styles.css');
		}


		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {

			$this->form_fields = apply_filters( 'wc_nimiq_form_fields', array(

				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-nimiq' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Nimiq payments', 'wc-gateway-nimiq' ),
					'default' => 'yes'
				),

				'network' => array(
					'title'       => __( 'Network', 'wc-gateway-nimiq' ),
					'type'        => 'select',
					'description' => __( 'Which network to use. Use the Testnet for testing.', 'wc-gateway-nimiq' ),
					'default'     => 'test',
					'options'     => array( 'test' => 'test', 'main' => 'main' ),
					'desc_tip'    => true,
				),

				'nimiq_address' => array(
					'title'       => __( 'Store NIM Address', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Your Nimiq address where customers will send their transactions to.', 'wc-gateway-nimiq' ),
					'default'     => '',
					'placeholder' => 'NQ...',
					'desc_tip'    => true,
				),

				'message' => array(
					'title'       => __( 'Transaction Message', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Enter a message that should be included in every transaction. 64 byte limit.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Thank you for shopping at shop.nimiq.com!', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				// FIXME: Becomes unecessary when API can retrieve mempool transactions
				'tx_wait_duration' => array(
					'title'       => __( 'Mempool Wait', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'How many minutes to wait for a transaction to be mined, before marking the order as failed.', 'wc-gateway-nimiq' ),
					'default'     => 15,
					'desc_tip'    => true,
				),

				'confirmations' => array(
					'title'       => __( 'Required Confirmations', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'The number of confirmations required to accept a transaction.', 'wc-gateway-nimiq' ),
					'default'     => 10,
					'desc_tip'    => true,
				),

				'title' => array(
					'title'       => __( 'Payment Title', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Pay with Nimiq', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-nimiq' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see during checkout.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Pay for your order with NIM via the Nimiq Keyguard.', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-nimiq' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank-you page and emails.', 'wc-gateway-nimiq' ),
					'default'     => __( 'You will receive another email after your payment has been confirmed and we sent your order.' ),
					'desc_tip'    => true,
				),
			) );
		}

		public function payment_fields() {

			if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
				$description = $this->get_description();
				if ( $description ) {
					echo wpautop( wptexturize( $description ) );
				}
				return;
			}

			// These scripts are enqueued at the end of the page
			wp_enqueue_script('KeyguardClient', plugin_dir_url( __FILE__ ) . 'keyguard-client.js');
			wp_enqueue_script('NetworkClient',  plugin_dir_url( __FILE__ ) . 'network-client.js');

			wp_register_script('NimiqCheckout', plugin_dir_url( __FILE__ ) . 'checkout.js');
			wp_localize_script('NimiqCheckout', 'CONFIG', array(
				'NETWORK'       => $this->get_option( 'network' ),
				'KEYGUARD_PATH' => $this->get_option( 'network' ) === 'main' ? 'https://keyguard.nimiq.com' : 'https://keyguard.nimiq-testnet.com',
				'API_PATH'      => $this->api_domain,
				'STORE_ADDRESS' => $this->get_option( 'nimiq_address' ),
				'TX_MESSAGE'    => $this->get_option( 'message' )
			));
			wp_enqueue_script('NimiqCheckout', null, ['KeyguardClient']);

			?>

			<div id="nim_account_loading_block">
				Loading your accounts, please wait...

				<noscript><br><br><strong>Javascript is required to pay with NIM. Please activate Javascript to continue.</strong></noscript>
			</div>

			<div id="nim_account_selector_block" class="hidden">
				<?php

					$select_options = array( '' => 'Please select' );
					if ( sanitize_text_field( $_POST['customer_nim_address'] ) ) {
						$select_options[] = sanitize_text_field( $_POST['customer_nim_address'] );
					}

					woocommerce_form_field(
						'customer_nim_address',
						array(
							'type'          => 'select', // text, textarea, select, radio, checkbox, password, about custom validation a little later
							'required'      => true, // actually this parameter just adds "*" to the field
							'id'            => 'customer_nim_address',
							'class'         => array(), // array only, read more about classes and styling in the previous step
							'label'         => 'Please select the account you want to pay with:',
							'options'       => $select_options,
						), sanitize_text_field( $_POST['customer_nim_address'] )
					);
				?>
				<input type="hidden" name="transaction_hash" id="transaction_hash" value="<?php sanitize_text_field( $_POST['transaction_hash'] ) ?>">

				<small>The store does not currently check if the selected account has enough balance. Please make sure that your selected account has enough balance, otherwise the order cannot be fulfilled.</small>
			</div>

			<div id="nim_payment_complete_block" class="hidden">
				<i class="fas fa-check-circle" style="color: seagreen;"></i>
				Payment complete
				<small>It is safe to place your order now.</small>
			</div>

			<script>
				var STORE_CART_TOTAL = <?php echo WC()->cart->get_total(false); ?>;
				if (typeof fill_accounts_selector !== 'undefined' ) fill_accounts_selector();
			</script>
			<?php
		}

		public function validate_fields() {
			if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
				return true;
			}

			$transaction_hash = sanitize_text_field( $_POST['transaction_hash'] );
			if ( ! $transaction_hash ) {
				wc_add_notice( __( 'You need to submit the Nimiq transaction first.' ), 'error' );
			}
			elseif ( strlen( $transaction_hash) !== 64 ) {
				wc_add_notice( __( 'Invalid transaction hash (' . $transaction_hash . '). Please contact support with this error message.' ), 'error' );
			}
		}

		public function update_order_meta_data( $order_id ) {
			update_post_meta( $order_id, 'customer_nim_address', sanitize_text_field( $_POST['customer_nim_address'] ) );
			update_post_meta( $order_id, 'transaction_hash', sanitize_text_field( $_POST['transaction_hash'] ) );
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}


		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}


		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
				// Remove cart
				WC()->cart->empty_cart();

				$order->update_status( 'pending-payment', __( 'Awaiting payment.', 'wc-gateway-nimiq' ) );

				// Return payment-page redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $order->get_checkout_payment_url( $on_checkout = false )
				);
			}

			// Mark as on-hold (we're awaiting transaction validation)
			$order->update_status( 'on-hold', __( 'Awaiting transaction validation.', 'wc-gateway-nimiq' ) );

			// Reduce stock levels
			wc_reduce_stock_levels($order_id);

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}

		// Check if the store NIM address is set
		// Custom function not required by the Gateway
		public function do_store_nim_address_check() {
			if( $this->enabled == "yes" ) {
				if( $this->get_option( 'nimiq_address' ) == "" ) {
					echo "<div class=\"error\"><p>". sprintf( __( "You must fill in your store's Nimiq address to be able to take payments in NIM. <a href=\"%s\">Set your Nimiq address here.</a>" ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nimiq_gateway' ) ) ."</p></div>";
				}
			}
		}

		public function do_bulk_validate_transactions() {
			// Get current blockchain height
			$current_height = wp_remote_get( $this->api_domain . '/latest/1' );
			if ( $current_height instanceof WP_Error ) {
				echo "ERROR: ";
				var_dump( $current_height );
				return;
			}
			$current_height = json_decode( $current_height[ 'body' ] );
			if ( $current_height->error ) {
				echo "ERROR: " . $current_height->error;
				return;
			}
			$current_height = $current_height[ 0 ]->height;
			// echo "Current height: " . $current_height . "\n";

			$posts = $_GET[ 'post' ];

			foreach ( $posts as $postID ) {
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
				$url = $this->api_domain . '/transaction/' . $transaction_hash;
				// echo "API URL: " . $url . "\n";
				$transaction = wp_remote_get( $url);
				if ( $transaction instanceof WP_Error ) {
					echo "ERROR: ";
					var_dump( $transaction );
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

					$time_limit = strtotime( '-' . $this->get_option( 'tx_wait_duration' ) . ' minutes' );
					// echo "Time limit: " . $time_limit . "\n";
					if ( $order_date < $time_limit ) {
						// If order date is earlier, mark as failed
						// echo "Tx not found => order failed\n";
						$order->update_status( 'failed', 'Transaction not found within wait duration.', true );
					}

					continue;
				} elseif ( $transaction->error ) {
					echo "ERROR: " . $transaction->error . "($url)\n";
					continue;
				}

				// If tx is returned, validate it

				// echo "Transaction sender: " . $transaction->sender_address . "\n";
				// echo "Order sender:       " . $order->get_meta('customer_nim_address') . "\n";
				if ( $transaction->sender_address !== $order->get_meta('customer_nim_address') ) {
					// echo "Transaction sender not equal order customer NIM address\n";
					$order->update_status( 'failed', 'Transaction sender does not match.', true );
					continue;
				}
				// echo "OK Transaction sender matches\n";

				// echo "Transaction recipient: " . $transaction->receiver_address . "\n";
				// echo "Store address:         " . $this->get_option( 'nimiq_address' ) . "\n";
				if ( $transaction->receiver_address !== $this->get_option( 'nimiq_address' ) ) {
					// echo "Transaction recipient not equal store NIM address\n";
					$order->update_status( 'failed', 'Transaction recipient does not match.', true );
					continue;
				}
				// echo "OK Transaction recipient matches\n";

				// echo "Transaction value: " . $transaction->value . "\n";
				// echo "Order value:       " . intval( $order->get_data()[ 'total' ] * 1e5 ) . "\n";
				if ( $transaction->value !== intval( $order->get_data()[ 'total' ] * 1e5 ) ) {
					// echo "Transaction value and order value are not equal\n";
					$order->update_status( 'failed', 'Transaction value does not match.', true );
					continue;
				}
				// echo "OK Transaction value matches\n";

				// and mark as 'processing' if confirmed
				// echo "Transaction height: " . $transaction->block_height . "\n";
				// echo "Confirmations setting: " . $this->get_option( 'confirmations' ) . "\n";
				// echo "Transaction confirmations: " . ($current_height - $transaction->block_height) . "\n";
				if ( empty( $transaction->block_height ) || $current_height - $transaction->block_height < $this->get_option( 'confirmations' ) ) {
					// echo "Transaction valid but not yet confirmed\n";
					continue;
				}
				// echo "OK Transaction confirmed\n";

				$order->update_status( 'processing', 'Transaction validated and confirmed.', true );
			}

			wp_redirect( wp_get_referer() );
		}

	} // end \WC_Gateway_Nimiq class
}


function register_bulk_actions( $actions ) {
	$actions[ 'validate-transactions' ] = 'Validate Transactions';
	return $actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'register_bulk_actions', 9);

function do_bulk_validate_transactions() {
	// Make sure that we on "Woocomerce orders list" page
	if ( !isset($_GET['post_type']) || $_GET['post_type'] !== 'shop_order' ) {
		return;
	}

	// Make sure that the correct action is submitted
	if ( !isset($_GET['action']) || $_GET['action'] !== 'validate-transactions' ) {
		return;
	}

	// Check nonce
	if ( !check_admin_referer( 'bulk-posts' ) ) {
		return;
	}

	$foo = new WC_Gateway_Nimiq();
	$foo->do_bulk_validate_transactions();
}
add_action( 'admin_init', 'do_bulk_validate_transactions', 0 );

include_once( plugin_dir_path( __FILE__ ) . 'nimiq_currency.php' );
