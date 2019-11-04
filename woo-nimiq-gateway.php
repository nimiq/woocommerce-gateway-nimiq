<?php
/**
 * Plugin Name: Nimiq Checkout for WooCommerce
 * Plugin URI: https://github.com/nimiq/woocommerce-gateway-nimiq
 * Description: Let customers pay with their Nimiq account directly in the browser
 * Author: Nimiq
 * Author URI: https://nimiq.com
 * Version: 2.7.4
 * Text Domain: wc-gateway-nimiq
 * Domain Path: /i18n/languages/
 * Requires at least: 4.9
 * Tested up to: 5.2
 * WC requires at least: 3.5
 * WC tested up to: 3.6
 *
 * Copyright: (c) 2018-2019 Nimiq Network Ltd., 2015-2016 SkyVerge, Inc. and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Nimiq
 * @author    Nimiq
 * @category  Admin
 * @copyright Copyright (c) 2018-2019 Nimiq Network Ltd., 2015-2016 SkyVerge, Inc. and WooCommerce
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


// We load the plugin later to ensure WC is loaded first since we're extending it.
add_action( 'plugins_loaded', 'wc_nimiq_gateway_init', 11 );

/**
 * Initializes plugin
 *
 * @since 1.0.0
 */
function wc_nimiq_gateway_init() {

	class WC_Gateway_Nimiq extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'nimiq_gateway';
			$this->has_fields         = true;
			$this->method_title       = 'Nimiq';
			$this->method_description = __( 'Allows Nimiq payments. Orders are marked as "on-hold" when received.', 'wc-gateway-nimiq' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'admin_notices', array( $this, 'do_store_nim_address_check' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_settings_script' ) );

			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

			// Add style, so it can be loaded in the header of the page
			wp_enqueue_style('NimiqPayment', plugin_dir_url( __FILE__ ) . 'styles.css');
		}

		/**
		 * Returns current plugin version
		 */
		public function version() {
			return get_file_data( __FILE__, [ 'Version' ], 'plugin')[ 0 ];
		}

		public function get_icon() {
			/**
			 * Data URLs need to be escaped like this:
			 * - all # must be %23
			 * - all double quotes (") must be single quotes (')
			 * - :// must be %3A%2F%2F
			 * - all slashes (/) must be %2F
			 */
			$icon_src = "data:image/svg+xml,<svg xmlns='http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' height='26' width='26' version='1.1' viewBox='0 0 72 72'><defs><radialGradient gradientTransform='matrix(0.99996243,0,0,1,0.00384744,3.9999988)' gradientUnits='userSpaceOnUse' r='72.019997' cy='63.169998' cx='54.169998' id='radial-gradient'><stop id='stop4' stop-color='%23ec991c' offset='0' /><stop id='stop6' stop-color='%23e9b213' offset='1' /></radialGradient></defs><path fill='url(%23radial-gradient)' stroke-width='0.99998122' d='M 71.201173,32.999999 56.201736,6.9999988 a 5.9997746,6 0 0 0 -5.199804,-3 H 21.003059 a 5.9997746,6 0 0 0 -5.189805,3 L 0.80381738,32.999999 a 5.9997746,6 0 0 0 0,6 l 14.99943662,26 a 5.9997746,6 0 0 0 5.199805,3 h 29.998873 a 5.9997746,6 0 0 0 5.189805,-3 l 14.999436,-26 a 5.9997746,6 0 0 0 0.01,-6 z' /></svg>";

			return '<img src="' . $icon_src . '" alt="' . esc_attr__( 'Nimiq logo', 'wc-gateway-nimiq' ) . '">';
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {

			$redirect_behaviour_options = [
				'popup' => 'Popup'
			];


			if ( $_SERVER['HTTPS'] === 'on' ) {
				$redirect_behaviour_options['redirect'] = 'Redirect';
			}

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
					'default'     => 'main',
					'options'     => array( 'main' => 'Mainnet', 'test' => 'Testnet' ),
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

				'price_service' => array(
					'title'       => __( 'Price Service', 'wc-gateway-nimiq' ),
					'type'        => 'select',
					'description' => __( 'Which service to use for fetching price information for automatic currency conversion.', 'wc-gateway-nimiq' ),
					'default'     => 'coingecko',
					'options'     => array(
						// List available price services here. The option value must match the file name.
						'coingecko' => 'Coingecko',
						'nimiqx'    => 'NimiqX',
					),
					'desc_tip'    => true,
				),

				'validation_service' => array(
					'title'       => __( 'Validation Service', 'wc-gateway-nimiq' ),
					'type'        => 'select',
					'description' => __( 'Which service to use for transaction validation.', 'wc-gateway-nimiq' ),
					'default'     => 'nimiq_watch',
					'options'     => array(
						// List available validation services here. The option value must match the file name.
						'nimiq_watch' => 'NIMIQ.WATCH (testnet & mainnet)',
						'json_rpc'    => 'Nimiq JSON-RPC API',
						'nimiqx'      => 'NimiqX (mainnet)',
					),
					'desc_tip'    => true,
				),

				'jsonrpc_url' => array(
					'title'       => __( 'JSON-RPC URL', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'URL (including port) of the JSON-RPC server used to verify transactions.', 'wc-gateway-nimiq' ),
					'default'     => 'http://localhost:8648',
					'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'jsonrpc_username' => array(
					'title'       => __( 'JSON-RPC Username', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( '(Optional) Username for the protected JSON-RPC service', 'wc-gateway-nimiq' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'jsonrpc_password' => array(
					'title'       => __( 'JSON-RPC Password', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( '(Optional) Password for the protected JSON-RPC service', 'wc-gateway-nimiq' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'nimiqx_api_key' => array(
					'title'       => __( 'NimiqX API Key', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Token for accessing the NimiqX price and validation service.', 'wc-gateway-nimiq' ),
					'default'     => '',
					'placeholder' => __( 'This field is required.', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'validation_interval' => array(
					'title'       => __( 'Validation Interval', 'wc-gateway-nimiq' ),
					'type'        => 'number',
					'description' => __( 'Interval in minutes to validate transactions. If you change this, disable and enable this plugin to put the change into effect.', 'wc-gateway-nimiq' ),
					'default'     => 30,
					'placeholder' => 'Default: 30',
					'desc_tip'    => true,
				),

				'rpc_behavior' => array(
					'title'       => __( 'Behavior', 'wc-gateway-nimiq' ),
					'type'        => 'select',
					'description' => __( 'How the user should visit the Nimiq Checkout.', 'wc-gateway-nimiq' ),
					'default'     => 'popup',
					'options'     => $redirect_behaviour_options,
					'desc_tip'    => true,
				),

				'shop_logo_url' => array(
					'title'       => __( 'Shop Logo URL (optional)', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'An image that should be displayed instead of the shop\'s identicon. ' .
										 'The URL must be under the same domain as the webshop. ' .
										 'Should be quadratic for best results.', 'wc-gateway-nimiq' ),
					'default'     => '',
					'placeholder' => 'No image set',
					'desc_tip'    => true,
				),

				'message' => array(
					'title'       => __( 'Transaction Message', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Enter a message that should be included in every transaction. 50 byte limit.', 'wc-gateway-nimiq' ),
					'default'     => __( 'Thank you for shopping with us!', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'fee' => array(
					'title'       => __( 'Transaction Fee per Byte', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Luna per byte to be applied to transactions.', 'wc-gateway-nimiq' ),
					'default'     => 0,
					'desc_tip'    => true,
				),

				// TODO: Becomes unecessary when API can retrieve mempool transactions
				'tx_wait_duration' => array(
					'title'       => __( 'Mempool Wait Limit', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'How many minutes to wait for a transaction to be mined, before marking the order as failed.', 'wc-gateway-nimiq' ),
					'default'     => 150, // 120 minutes (Nimiq tx validity window) + 30 min buffer
					'desc_tip'    => true,
				),

				'confirmations' => array(
					'title'       => __( 'Required Confirmations', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'The number of confirmations required to accept a transaction.', 'wc-gateway-nimiq' ),
					'default'     => 30,
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
					'default'     => __( 'Pay with your Nimiq Account directly in the browser.', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),

				'instructions' => array(
					'title'       => __( 'Email Instructions', 'wc-gateway-nimiq' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank-you page and emails.', 'wc-gateway-nimiq' ),
					'default'     => __( 'You will receive email updates after your payment has been confirmed and when we sent your order.', 'wc-gateway-nimiq' ),
					'desc_tip'    => true,
				),
			) );
		}

		public function payment_fields() {

			if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
				$description = $this->get_description();
				if ( $description ) {
					echo wpautop( wptexturize( $description ) );
					echo '<p><a href="https://nimiq.com" class="about_nimiq" target="_blank">' . esc_html__( 'What is Nimiq?', 'wc-gateway-nimiq' ) . '</a></p>';
				}
				return;
			}

			// These scripts are enqueued at the end of the page
			wp_enqueue_script('HubApi', plugin_dir_url( __FILE__ ) . 'js/HubApi.standalone.umd.js', [], $this->version(), true );

			$order_total_nim = 0;
			$nim_price = 0;
			$order_currency = '';
			$order_hash = '';
			$order = null;
			if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
				$order = wc_get_order( $order_id );
				$order_total = $order->get_total();
				$order_currency = $order->get_currency();
				$price_service = $this->get_option( 'price_service' );

				if ( $order_currency === 'NIM') {
					$order_total_nim = $order_total;
					update_post_meta( $order_id, 'order_total_nim', $order_total_nim );
				} else {
					include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'price_services' . DIRECTORY_SEPARATOR . $price_service . '.php' );
					$class = 'WC_Gateway_Nimiq_Price_Service_' . ucfirst( $price_service );

					$price_service = new $class( $this );
					$nim_price = $price_service->getCurrentPrice( $order_currency );

					if ( is_wp_error( $nim_price ) ) {
						update_post_meta( $order_id, 'conversion_error', $nim_price->get_error_message() );
					} else {
						// Round up to full NIM
						$order_total_nim = ceil( $order_total / $nim_price );

						update_post_meta( $order_id, 'nim_price', $nim_price );
						update_post_meta( $order_id, 'nim_price_currency', $order_currency );
						update_post_meta( $order_id, 'order_total_nim', $order_total_nim );
					}
				}

				$order_hash = $order->get_meta( 'order_hash' );
				if ( empty( $order_hash ) ) {
					$order_hash = $this->compute_order_hash( $order );
					update_post_meta( $order_id, 'order_hash', $order_hash );
				}
			}

			$message = '';

			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				$meta_msg = get_post_meta( $product_id, 'wc-nimiq-transaction-message', true);
				if (!empty($meta_msg)) {
					$quantity = $item->get_quantity();
					$message = sprintf($meta_msg, $quantity);
					break;
				}
			}

			if (empty($message)) $message = $this->get_option( 'message' );

			if (!empty($message)) $message .= ' '; // Add a space to the end

			// To uniquely identify the payment transaction, we add a shortened hash of
			// the order details to the transaction message.
			$message = $message . '(' . strtoupper( $this->get_short_order_hash( $order_hash ) ) . ')';

			$message_bytes = unpack('C*', $message); // Convert to byte array

			wp_register_script( 'NimiqCheckout', plugin_dir_url( __FILE__ ) . 'js/checkout.js', [ 'jquery', 'HubApi' ], $this->version(), true );
			wp_localize_script( 'NimiqCheckout', 'CONFIG', array(
				'SITE_TITLE'     => get_bloginfo( 'name' ),
				'HUB_URL'        => $this->get_option( 'network' ) === 'main' ? 'https://hub.nimiq.com/' : 'https://hub.nimiq-testnet.com/',
				'SHOP_LOGO_URL'  => $this->get_option( 'shop_logo_url' ),
				'STORE_ADDRESS'  => $this->get_option( 'nimiq_address' ),
				'ORDER_TOTAL'    => intval( $order_total_nim * 1e5 ),
				'TX_FEE'         => ( 166 + count( $message_bytes ) ) * ( intval( $this->get_option( 'fee' ) ) ?: 0 ),
				'TX_MESSAGE'     => '[' . implode( ',', $message_bytes ) . ']',
				'RPC_BEHAVIOR'   => $this->get_option( 'rpc_behavior' ),
			) );
			wp_enqueue_script( 'NimiqCheckout' );

			?>

			<div id="nim_gateway_info_block">
				<noscript>
					<strong>
						<?php _e( 'Javascript is required to pay with Nimiq. Please activate Javascript to continue.', 'wc-gateway-nimiq' ); ?>
					</strong>
				</noscript>

				<input type="hidden" name="transaction_hash" id="transaction_hash" value="<?php sanitize_text_field( $_POST['transaction_hash'] ) ?>">
				<input type="hidden" name="customer_nim_address" id="customer_nim_address" value="">

				<p class="form-row">
					<?php _e( 'Order amount:', 'wc-gateway-nimiq' ); ?>
					<strong><?php echo( number_format( $order_total_nim, 0, '.', ' ' ) ); ?> NIM</strong>
				</p>

				<?php if ( is_wp_error( $nim_price ) ) { ?>
					<p class="form-row" style="color: red; font-style: italic;">
						<?php _e( 'Could not get a NIM conversion rate:', 'wc-gateway-nimiq' ); ?><br>
						<?php echo( $nim_price->get_error_message() ); ?>
					</p>
				<?php } elseif ( $nim_price > 0 ) { ?>
					<p class="form-row">
						<?php _e( 'Rate:', 'wc-gateway-nimiq' ); ?>
						1 NIM = <?php echo( wc_price( $nim_price, [ 'decimals' => 6, 'currency' => $order_currency ] ) ); ?>
					</p>
				<?php } ?>

				<?php if ( ! is_wp_error( $nim_price ) && $nim_price > 0 || $order_currency === 'NIM') { ?>
					<p class="form-row">
						<?php _e( 'Please click the big button below to pay with Nimiq.', 'wc-gateway-nimiq' ); ?>
					</p>
				<?php } ?>
			</div>

			<div id="nim_payment_complete_block" class="hidden">
				<i class="fas fa-check-circle" style="color: seagreen;"></i>
				<?php _e( 'Payment complete', 'wc-gateway-nimiq' ); ?>
			</div>
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
				wc_add_notice( __( 'You need to submit the Nimiq transaction first.', 'wc-gateway-nimiq' ), 'error' );
				return false;
			}

			if ( strlen( $transaction_hash) !== 64 ) {
				wc_add_notice( __( 'Invalid transaction hash (' . $transaction_hash . '). Please contact support with this error message.', 'wc-gateway-nimiq' ), 'error' );
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

			// Return thank-you redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}

		// Check if the store NIM address is set and show admin notice otherwise
		// Custom function not required by the Gateway
		public function do_store_nim_address_check() {
			if( $this->enabled == "yes" ) {
				if( $this->get_option( 'nimiq_address' ) == "" ) {
					echo '<div class="error notice"><p>'. sprintf( __( 'You must fill in your store\'s Nimiq address to be able to take payments in NIM. <a href="%s">Set your Nimiq address here.</a>', 'wc-gateway-nimiq' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nimiq_gateway' ) ) .'</p></div>';
				}
			}
		}

		/**
		 * Enqueue script on WooCommerce settings pages
		 *
		 * @since 2.2.1
		 * @param string $hook - Name of the current admin page.
		 */
		public function enqueue_admin_settings_script( $hook ) {
			if ( $hook !== 'woocommerce_page_wc-settings' ) return;
			wp_enqueue_script( 'NimiqSettings', plugin_dir_url( __FILE__ ) . 'js/settings.js', [ 'jquery' ], $this->version(), true );
		}

	} // end WC_Gateway_Nimiq class

} // end wc_nimiq_gateway_init()

include_once( plugin_dir_path( __FILE__ ) . 'includes/nimiq_currency.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/bulk_actions.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/validation_scheduler.php' );


/**
 * Display the custom text field
 * @since 1.0.0
 */
function wc_nimiq_add_custom_product_meta_field() {
	$args = array(
		'id' => 'wc-nimiq-transaction-message',
		'label' => __( 'Payment message', 'wc-gateway-nimiq' ),
		'class' => 'wc-nq-tx-message',
		'desc_tip' => true,
		'description' => __( 'Set a custom payment message when this product is bought. Use %d to write the bought quantity into the message.', 'wc-gateway-nimiq' ),
	);
	woocommerce_wp_text_input( $args );
}
add_action( 'woocommerce_product_options_general_product_data', 'wc_nimiq_add_custom_product_meta_field' );

/**
 * Save the custom field
 * @since 1.0.0
 */
function wc_nimiq_save_custom_product_field( $post_id ) {
	$product = wc_get_product( $post_id );
	$title = isset( $_POST['wc-nimiq-transaction-message'] ) ? $_POST['wc-nimiq-transaction-message'] : '';
	$product->update_meta_data( 'wc-nimiq-transaction-message', sanitize_text_field( $title ) );
	$product->save();
}
add_action( 'woocommerce_process_product_meta', 'wc_nimiq_save_custom_product_field' );
