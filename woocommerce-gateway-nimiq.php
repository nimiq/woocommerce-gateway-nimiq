<?php
/**
 * Plugin Name: WooCommerce Nimiq Gateway
 * Plugin URI:
 * Description: Pay with Nimiq via the Nimiq Keyguard
 * Author: Nimiq
 * Author URI: http://www.nimiq.com/
 * Version: 1.10.0
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
			/**
			 * Data URLs need to be escaped like this:
			 * - all double quotes (") need to be single quotes (')
			 * - :// needs to be %3A%2F%2F
			 * - all slashes (/) need to be %2F
			 */
			$this->icon               = "data:image/svg+xml,<svg xmlns:svg='http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' xmlns='http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' height='72' width='72' version='1.1' viewBox='0 0 72 72'><defs><radialGradient gradientTransform='matrix(0.99996243,0,0,1,0.00384744,3.9999988)' gradientUnits='userSpaceOnUse' r='72.019997' cy='63.169998' cx='54.169998' id='radial-gradient'><stop id='stop4' stop-color='%23ec991c' offset='0' /><stop id='stop6' stop-color='%23e9b213' offset='1' /></radialGradient></defs><path fill='url(%23radial-gradient)' stroke-width='0.99998122' d='M 71.201173,32.999999 56.201736,6.9999988 a 5.9997746,6 0 0 0 -5.199804,-3 H 21.003059 a 5.9997746,6 0 0 0 -5.189805,3 L 0.80381738,32.999999 a 5.9997746,6 0 0 0 0,6 l 14.99943662,26 a 5.9997746,6 0 0 0 5.199805,3 h 29.998873 a 5.9997746,6 0 0 0 5.189805,-3 l 14.999436,-26 a 5.9997746,6 0 0 0 0.01,-6 z' /></svg>";
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
					'title'       => __( 'Shop NIM Address', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Your Nimiq address where customers will send their transactions to.', 'wc-gateway-nimiq' ),
					'default'     => '',
					'placeholder' => 'NQ...',
					'desc_tip'    => true,
				),

				'message' => array(
					'title'       => __( 'Transaction Message', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Enter a message that should be included in every transaction. 50 byte limit.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Thank you for shopping at shop.nimiq.com!', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'fee' => array(
					'title'       => __( 'Transaction Fee per Byte', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Nimtoshis per byte to be applied to transactions.', 'wc-gateway-nimiq' ),
					'default'     => 0,
					'desc_tip'    => true,
				),

				// FIXME: Becomes unecessary when API can retrieve mempool transactions
				'tx_wait_duration' => array(
					'title'       => __( 'Mempool Wait Limit', 'wc-gateway-nimiq' ),
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
					'title'       => __( 'Payment Method Title', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Pay with Nimiq', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Payment Method Description', 'wc-gateway-nimiq' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see during checkout.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Pay for your order with NIM via the Nimiq Keyguard.', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'instructions' => array(
					'title'       => __( 'Email Instructions', 'wc-gateway-nimiq' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank-you page and emails.', 'wc-gateway-nimiq' ),
					'default'     => __( 'You will receive email updates after your payment has been confirmed and when we sent your order.' ),
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
			wp_enqueue_script('KeyguardClient', plugin_dir_url( __FILE__ ) . 'js/keyguard-client.js');
			wp_enqueue_script('NetworkClient',  plugin_dir_url( __FILE__ ) . 'js/network-client.js');

			$order_total = 0;
			$order_hash = '';
			if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
				$order = wc_get_order( $order_id );
				$order_total = $order->get_total();

				$order_hash = $order->get_meta( 'order_hash' );
				if ( empty( $order_hash ) ) {
					$order_hash = $this->compute_order_hash( $order );
					update_post_meta( $order_id, 'order_hash', $order_hash );
				}
			}

			$tx_message = ( !empty( $this->get_option( 'message' ) ) ? $this->get_option( 'message' ) . ' ' : '' )
				. '(' . strtoupper( $this->get_short_order_hash( $order_hash ) ) . ')';

			$tx_message_bytes = unpack('C*', $tx_message);

			wp_register_script('NimiqCheckout', plugin_dir_url( __FILE__ ) . 'js/checkout.js');
			wp_localize_script('NimiqCheckout', 'CONFIG', array(
				'NETWORK'       => $this->get_option( 'network' ),
				'KEYGUARD_PATH' => $this->get_option( 'network' ) === 'main' ? 'https://keyguard.nimiq.com/' : 'https://keyguard.nimiq-testnet.com/',
				'API_PATH'      => $this->api_domain,
				'STORE_ADDRESS' => $this->get_option( 'nimiq_address' ),
				'ORDER_TOTAL'   => $order_total,
				'TX_FEE'        => ( 166 + strlen( $tx_message ) ) * ( intval( $this->get_option( 'fee' ) ) || 0 ) / 1e5,
				'TX_MESSAGE'    => '[' . implode(',', $tx_message_bytes) . ']',
			));
			wp_enqueue_script('NimiqCheckout', null, ['KeyguardClient']);

			?>

			<div id="nim_account_loading_block">
				Loading your accounts, please wait...

				<noscript><br><br><strong>Javascript is required to pay with NIM. Please activate Javascript to continue.</strong></noscript>
			</div>

            <div id="nim_no_account_block" class="hidden">
				<p class="form-row">
                    It seems that you don't have a Nimiq Account on this device. If you have an account created on another device, please import it in <a href='https://safe.nimiq.com' target='_blank'>Nimiq Safe</a> and reload this page afterwards.
				</p>
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

				<p class="form-row">
					The store does not currently check if the selected account has enough balance.
					Please make sure that your selected account has enough balance, otherwise the order cannot be fulfilled.
				</p>
			</div>

			<div id="nim_payment_complete_block" class="hidden">
				<i class="fas fa-check-circle" style="color: seagreen;"></i>
				Payment complete
			</div>

			<script>
				if (typeof fill_accounts_selector !== 'undefined' ) fill_accounts_selector();
			</script>
			<?php
		}

		protected function compute_order_hash( $order ) {
			$order_data = $order->get_data();

			$serialized_order_data = implode(',', [
				$order->get_id(),
				$order_data[ 'date_created' ]->getTimestamp(),
				$order_data[ 'currency' ],
				$order->get_total(),
				$order_data['billing']['first_name'],
				$order_data['billing']['last_name'],
				$order_data['billing']['address_1'],
				$order_data['billing']['city'],
				$order_data['billing']['state'],
				$order_data['billing']['postcode'],
				$order_data['billing']['country'],
				$order_data['billing']['email'],
			]);

			return sha1( $serialized_order_data );
		}

		public function get_short_order_hash( $long_hash ) {
			return substr( $long_hash, 0, 6 );
		}

		public function validate_fields() {
			if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
				return true;
			}

			$transaction_hash = sanitize_text_field( $_POST['transaction_hash'] );
			if ( ! $transaction_hash ) {
				wc_add_notice( __( 'You need to submit the Nimiq transaction first.' ), 'error' );
				return false;
			}

			if ( strlen( $transaction_hash) !== 64 ) {
				wc_add_notice( __( 'Invalid transaction hash (' . $transaction_hash . '). Please contact support with this error message.' ), 'error' );
				return false;
			}

			if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
				$this->update_order_meta_data( $order_id );
			}

			return true;
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
			if ( $this->instructions && ! $sent_to_admin && $order->get_payment_method() === $this->id && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}

			if ( ! $sent_to_admin && $order->get_payment_method() === $this->id && $order->has_status( 'completed' ) ) {
				$carrier = $order->get_meta( 'carrier' );
				$tracking_number = $order->get_meta( 'tracking_number' );
				$tracking_url = '';

				if ( $carrier === 'DHL' ) {
					$tracking_url = 'https://nolp.dhl.de/nextt-online-public/en/search?piececode=' . $tracking_number;
				}

				if ( $carrier === 'Deutsche Post' ) {
					$tracking_url = 'https://www.deutschepost.de/sendung/simpleQuery.html?locale=en_GB';
				}

				if ( $carrier && $tracking_number ) {
					echo '<p>Your order is being shipped with <strong>' . $carrier . '</strong> and your tracking number is:</p>' . PHP_EOL .
						 '<p><strong>' . $tracking_number . '</strong></p>' . PHP_EOL;

					if ( $tracking_url ) {
						echo '<p><a href="' . $tracking_url . '">Track your package here.</p>' . PHP_EOL;
					}
				}
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
					echo '<div class="error notice"><p>'. sprintf( __( 'You must fill in your store\'s Nimiq address to be able to take payments in NIM. <a href="%s">Set your Nimiq address here.</a>' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nimiq_gateway' ) ) .'</p></div>';
				}
			}
		}
	} // end \WC_Gateway_Nimiq class
}

include_once( plugin_dir_path( __FILE__ ) . 'includes/nimiq_currency.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/bulk_actions.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/validation_scheduler.php' );
