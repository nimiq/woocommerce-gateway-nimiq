<?php
/**
 * Plugin Name: WooCommerce Nimiq Gateway
 * Plugin URI:
 * Description: Pay with Nimiq via the Nimiq Keyguard
 * Author: Nimiq
 * Author URI: http://www.nimiq.com/
 * Version: 1.1.0
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
			$this->icon               = apply_filters('woocommerce_nimiq_icon', '');
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

				'nimiq_address' => array(
					'title'       => __( 'Store NIM Address', 'wc-gateway-nimiq' ),
					'type'        => 'text',
					'description' => __( 'Your Nimiq address where customers will send their transactions to.', 'wc-gateway-nimiq' ),
					'default'     => '',
					'placeholder' => 'NQ...',
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
					'default'     => __( 'Pay for your swag with NIM via the Nimiq Keyguard.', 'wc-gateway-nimiq' ),
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
			// $description = $this->get_description();
			// if ( $description ) {
			// 	echo wpautop( wptexturize( $description ) );
			// }

			// These scripts are enqueued at the end of the page
			wp_enqueue_script('KeyguardClient', 'http://localhost:5000/libraries/account-manager/dist/keyguard-client.js');
			wp_enqueue_script('NetworkClient', 'http://localhost:5000/apps/safe/dist/network-client.js');
			wp_enqueue_script('NimiqCheckout', plugin_dir_url( __FILE__ ) . 'checkout.js', ['KeyguardClient']);

			?>

			<div id="nim_account_loading_block">
				Loading your accounts, please wait...
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
			</div>
			<script>
				var STORE_NIM_ADDRESS = '<?php echo $this->get_option( 'nimiq_address' ); ?>';
				var STORE_CART_TOTAL = <?php echo WC()->cart->get_total(false); ?>;
				if (typeof fill_accounts_selector !== 'undefined' ) fill_accounts_selector();
			</script>
			<?php
		}

		public function validate_fields() {
			// TODO Check validity of the hash (length)
			if ( ! sanitize_text_field( $_POST['transaction_hash'] ) ) {
				wc_add_notice( __( 'You need to submit the Nimiq transaction first.' ), 'error' );
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

			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting Nimiq payment', 'wc-gateway-nimiq' ) );

			// Reduce stock levels
			wc_reduce_stock_levels($order_id);

			// Remove cart
			WC()->cart->empty_cart();

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

	} // end \WC_Gateway_Nimiq class
}

include_once(plugin_dir_path( __FILE__ ) . 'extras.php');
