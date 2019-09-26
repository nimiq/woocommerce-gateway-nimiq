<?php
/**
 * Plugin Name: Nimiq Checkout for WooCommerce
 * Plugin URI: https://github.com/nimiq/woocommerce-gateway-nimiq
 * Description: Let customers pay with Nimiq, Bitcoin and Ethereum
 * Author: Nimiq
 * Author URI: https://nimiq.com
 * Version: 3.0.0-alpha
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

// Un-hide Wordpress' regular Custom Fields
// TODO: Integrate with ACF to display plugin data instead
add_filter('acf/settings/remove_wp_meta_box', '__return_false');


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
			$this->method_title       = 'Nimiq Crypto Checkout';
			$this->method_description = __( 'Allows crypto payments. Orders are marked as "on-hold" when received.', 'wc-gateway-nimiq' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = __( 'Nimiq Crypto Checkout', 'wc-gateway-nimiq' );
			$this->description  = __( 'You will be redirected to Nimiq to complete your purchase securely.', 'wc-gateway-nimiq' );
			$this->instructions = $this->get_option( 'instructions' );

			// Instantiate utility classes
			$this->crypto_manager = new Crypto_Manager( $this );

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_api_wc_gateway_nimiq', array( $this, 'handle_redirect_response' ) );
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
			 * - all slashes in attributes (/) must be %2F
			 */

			$currencies = $this->crypto_manager->get_accepted_cryptos();

			// Widths of the source icons
			$WIDTHS = [
				'nim' => 26,
				'btc' => 26,
				'eth' => 18,
			];

			$SPACING = 12;

			$image_width = array_reduce( array_map( function( $crypto ) use ( $WIDTHS ) {
				return $WIDTHS[ $crypto ];
			}, $currencies ), function( $acc, $width ) {
				return $acc + $width;
			}, 0 ) + $SPACING * ( count( $currencies ) - 1 );

			$offsets = [
				'nim' => 0,
				'btc' => $WIDTHS[ 'nim' ] + $SPACING,
				'eth' => in_array( 'btc', $currencies )
					? $WIDTHS[ 'nim' ] + $WIDTHS[ 'btc' ] + $SPACING * 2
					: $WIDTHS[ 'nim' ] + $SPACING,
			];

			$alt = implode( ', ', array_map( function( $crypto ) {
				return ucfirst( Crypto_Manager::iso_to_name( $crypto ) );
			}, $currencies ) );

			$defs = "<radialGradient id='nimiq-radial-gradient' cx='-166.58' cy='275.96' r='1.06' gradientTransform='matrix(-24.62, 0, 0, 21.78, -4075.39, -5984.84)' gradientUnits='userSpaceOnUse'><stop offset='0' stop-color='%23ec991c'/><stop offset='1' stop-color='%23e9b213'/></radialGradient>";

			$logo_nimiq = "<path fill='url(%23nimiq-radial-gradient)' d='M25.71,12.92,20.29,3.58A2.16,2.16,0,0,0,18.42,2.5H7.58A2.15,2.15,0,0,0,5.71,3.58L.29,12.92a2.14,2.14,0,0,0,0,2.16l5.42,9.34A2.15,2.15,0,0,0,7.58,25.5H18.42a2.16,2.16,0,0,0,1.87-1.08l5.42-9.34A2.14,2.14,0,0,0,25.71,12.92Z'/>";

			$logo_bitcoin = in_array( 'btc', $currencies ) ? "<g transform='translate(" . $offsets[ 'btc' ] . " 0)'><path fill='%23f7931a' d='M25.61,17.15A13,13,0,1,1,16.14,1.39,13,13,0,0,1,25.61,17.15Z'/><path fill='%23fff' d='M18.73,12.15c.26-1.73-1.06-2.66-2.86-3.28l.59-2.35L15,6.17l-.57,2.28-1.14-.27.57-2.3-1.42-.35-.59,2.34L11,7.66h0L9,7.16,8.63,8.68s1,.24,1,.26a.77.77,0,0,1,.67.83l-.67,2.67s0,0,0,0l-.94,3.74a.52.52,0,0,1-.65.34s-1-.26-1-.26l-.7,1.63,1.85.46,1,.27L8.61,21l1.42.35L10.62,19l1.14.29-.59,2.34L12.6,22l.59-2.36c2.43.46,4.26.27,5-1.93a2.5,2.5,0,0,0-1.31-3.46A2.26,2.26,0,0,0,18.73,12.15Zm-3.26,4.57c-.44,1.77-3.42.81-4.39.57l.79-3.14C12.83,14.39,15.93,14.87,15.47,16.72Zm.44-4.6c-.4,1.61-2.88.79-3.69.59l.71-2.84C13.74,10.07,16.33,10.44,15.91,12.12Z'/></g>" : "";

			$logo_ethereum = in_array( 'eth', $currencies ) ? "<g transform='translate(" . $offsets[ 'eth' ] . " 0)'><path class='cls-1' d='M9,21v7l9-12.08Z'/><path d='M9,10.36v9l9-5.09Z'/><path fill='%232f3030' d='M9,0V10.37l9,3.9Z'/><path fill='%23828384' d='M9,21v7L0,15.92Z'/><path fill='%23343535' d='M9,10.36v9L0,14.27Z'/><path fill='%23828384' d='M9,0V10.37l-9,3.9Z'/></g>" : "";

			$icon_src = "data:image/svg+xml,<svg xmlns='http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg' width='" . $image_width . "' height='28' viewBox='0 0 " . $image_width . " 28'><defs>" . $defs . "</defs>" . $logo_nimiq . $logo_bitcoin . $logo_ethereum . "</svg>";

			$img  = '<img src="' . $icon_src . '" alt="' . $alt . '">';
			// $link = '<a href="https://nimiq.com" class="about_nimiq" target="_blank">' . esc_html__( 'What is Nimiq?', 'wc-gateway-nimiq' ) . '</a>';

			return $img/* . $link */;
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			// include_once() does not work here, as when saving the settings the file needs to be included twice
			include( plugin_dir_path( __FILE__ ) . 'settings.php' );
			$this->form_fields = $woo_nimiq_checkout_settings;
		}

		public function get_payment_request( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order->get_status() !== 'pending' ) {
				return new WP_Error( 'order', 'Order is not in pending state and thus cannot be paid.' );
			}

			$order_total = floatval( $order->get_total() );
			$order_currency = $order->get_currency();

			$order_hash = $order->get_meta( 'order_hash' );
			if ( empty( $order_hash ) ) {
				$order_hash = $this->compute_order_hash( $order );
				$order->update_meta_data( 'order_hash', $order_hash );
			}

			// To uniquely identify the payment transaction, we add a shortened hash of
			// the order details to the transaction message.
			$tx_message = ( !empty( $this->get_option( 'message' ) ) ? $this->get_option( 'message' ) . ' ' : '' )
				. '(' . strtoupper( $this->get_short_order_hash( $order_hash ) ) . ')';

			$tx_message_bytes = unpack('C*', $tx_message); // Convert to byte array

			$fees = $this->crypto_manager->get_fees( count( $tx_message_bytes ) );

			// Collect common request properties used in both request types
			$request = [
				'appName' => get_bloginfo( 'name' ),
				'shopLogoUrl' => $this->get_option( 'shop_logo_url' ),
				'extraData' => $tx_message,
			];

			if ( $order_currency === 'NIM') {
				$order->update_meta_data( 'order_total_nim', $order_total );

				// Use NimiqCheckoutRequest (version 1)
				$request = array_merge( $request, [
					'version' => 1,
					'recipient' => Order_Utils::get_order_recipient_address( $order, $this ),
					'value' => intval( Order_Utils::get_order_total_crypto( $order ) ),
					'fee' => $fees[ 'nim' ],
				] );
			} else {
				// Check if the order already has a payment or an unexpired quote
				$transaction_hash = $order->get_meta( 'transaction_hash' ) ?: null;
				$expires = $order->get_meta( 'crypto_rate_expires' ) ?: 0;
				$stored_request = $order->get_meta( 'nc_payment_request' ) ?: null;
				// Send the old quote if the expiry is still more than 3 minutes (180 seconds) away
				if ( ( $transaction_hash || ( $expires - 180 ) > time() ) && $stored_request ) {
					// Send stored request (with updated server time)
					$request = json_decode( $stored_request, true );
					$request[ 'time' ] = time();
				} else {
					$price_service = $this->get_option( 'price_service' );
					include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'price_services' . DIRECTORY_SEPARATOR . $price_service . '.php' );
					$class = 'WC_Gateway_Nimiq_Price_Service_' . ucfirst( $price_service );
					$price_service = new $class( $this );

					$accepted_cryptos = $this->crypto_manager->get_accepted_cryptos();

					// Apply margin to order value
					$margin = floatval( $this->get_option( 'margin', '0' ) ) / 100;
					$effective_order_total = $order_total * ( 1 + $margin );

					// Get pricing info from price service
					$pricing_info = $price_service->get_prices( $accepted_cryptos, $order_currency, $effective_order_total );

					if ( is_wp_error( $pricing_info ) ) {
						$order->update_meta_data( 'conversion_error', $pricing_info->get_error_message() );
						return $pricing_info;
					}

					// Set quote expirery
					$expires = strtotime( '+15 minutes' );
					$order_expiry = Order_Utils::get_order_hold_expiry( $order );
					if ( $order_expiry ) $expires = min( $expires, $order_expiry );
					$order->update_meta_data( 'crypto_rate_expires', $expires );

					// Process result from price service
					// Price service can either return prices or quotes, requiring different handling
					$order_totals_crypto = [];
					if ( array_key_exists( 'prices', $pricing_info ) ) {
						$prices = $pricing_info[ 'prices' ];
						$order_totals_crypto = Crypto_Manager::calculate_quotes( $effective_order_total, $prices );
					} else if ( array_key_exists( 'quotes', $pricing_info ) ) {
						$quotes = $pricing_info[ 'quotes' ];
						$order_totals_crypto = Crypto_Manager::format_quotes( $effective_order_total, $quotes );
					} else {
						return new WP_Error( 'service', 'Price service did not return any pricing information.' );
					}

					$fees = array_key_exists( 'fees', $pricing_info )
						? $pricing_info[ 'fees' ]
						: $fees;

					foreach ( $accepted_cryptos as $crypto ) {
						$order->update_meta_data( 'crypto_fee_' . $crypto, $crypto === 'eth'
							? $fees[ $crypto ][ 'gas_price' ]
							: $fees[ $crypto ]
						);
						$order->update_meta_data( 'order_total_' . $crypto, $order_totals_crypto[ $crypto ] );
					}

					// Convert coins into smallest units
					$order_totals_unit = Crypto_Manager::coins_to_units( $order_totals_crypto );

					// Generate CSRF token
					$csrf_token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
					$order->update_meta_data( 'checkout_csrf_token', $csrf_token );

					// Generate callback URL
					$callback_url = get_site_url() . '/wc-api/nimiq_checkout_callback?id=' . $order_id;

					// Use MultiCurrencyCheckoutRequest (version 2)
					$payment_options = [];
					foreach ( $accepted_cryptos as $crypto ) {
						$amount = $order_totals_unit[ $crypto ];
						$fee = $fees[ $crypto ];

						$protocolSpecific = $crypto === 'eth' ? [
							'gasLimit' => $fee[ 'gas_limit' ],
							'gasPrice' => strval( $fee[ 'gas_price' ] ),
						] : [
							'fee' => $fee,
						];

						if ( $crypto === 'nim' ) {
							$protocolSpecific[ 'recipient' ] = Order_Utils::get_order_recipient_addresses( $order, $this )[ 'nim' ];
						}

						$payment_options[] = [
							'type' => 0, // 0 = DIRECT
							'currency' => $crypto,
							'expires' => $expires,
							'amount' => $amount,
							'protocolSpecific' => $protocolSpecific,
						];
					};

					$request = array_merge( $request, [
						'version' => 2,
						'callbackUrl' => $callback_url,
						'csrf' => $csrf_token,
						'time' => time(),
						'fiatAmount' => $order_total,
						'fiatCurrency' => $order_currency,
						'paymentOptions' => $payment_options,
					] );

					$order->update_meta_data( 'nc_payment_request', json_encode( $request ) );
				}
			}

			$order->save();

			return $request;
		}

		private function handle_redirect_response() {
			$order_id = $this->get_param( 'id' );

			if ( empty( $order_id ) ) {
				// Redirect to main site
				wp_redirect( get_site_url() );
				exit;
			}

			$is_valid = $this->validate_fields( $order_id );

			if ( !$is_valid ) {
				// Redirect to payment page
				$order = wc_get_order( $order_id );
				wp_redirect( $order->get_checkout_payment_url( $on_checkout = false ) );
				exit;
			}

			$redirect = $this->process_payment( $order_id );

			wp_redirect( $redirect[ 'redirect' ] );
			exit;
		}

		public function payment_fields() {
			if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
				$description = $this->get_description();
				if ( $description ) {
					echo wpautop( wptexturize( $description ) );
				}
				return;
			}

			if ( !isset( $_GET['pay_for_order'] ) || !isset( $_GET['key'] ) ) {
				return;
			}

			$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
			$request = $this->get_option( 'rpc_behavior' ) === 'popup' ? $this->get_payment_request( $order_id ) : [];

			if ( is_wp_error( $request ) ) {
				?>
				<div id="nim_gateway_info_block">
					<p class="form-row" style="color: red; font-style: italic;">
						<?php _e( 'ERROR:', 'wc-gateway-nimiq' ); ?><br>
						<?php echo( $request->get_error_message() ); ?>
					</p>
				</div>
				<?php
				return;
			}

			// These scripts are enqueued at the end of the page
			wp_enqueue_script('HubApi', plugin_dir_url( __FILE__ ) . 'js/HubApi.standalone.umd.js', [], $this->version(), true );

			wp_register_script( 'NimiqCheckout', plugin_dir_url( __FILE__ ) . 'js/checkout.js', [ 'jquery', 'HubApi' ], $this->version(), true );
			wp_localize_script( 'NimiqCheckout', 'CONFIG', array(
				'HUB_URL'      => $this->get_option( 'network' ) === 'main' ? 'https://hub.nimiq.com' : 'https://hub.nimiq-testnet.com',
				'RPC_BEHAVIOR' => $this->get_option( 'rpc_behavior' ),
				'REQUEST'      => json_encode( $request ),
			) );
			wp_enqueue_script( 'NimiqCheckout' );

			?>

			<div id="nim_gateway_info_block">
				<?php if ( $this->get_option( 'rpc_behavior' ) === 'popup' ) { ?>
					<noscript>
						<strong>
							<?php _e( 'Javascript is required to use Nimiq Checkout. Please activate Javascript to continue.', 'wc-gateway-nimiq' ); ?>
						</strong>
					</noscript>

					<input type="hidden" name="rpcId" id="rpcId" value="">
					<input type="hidden" name="status" id="status" value="">
					<input type="hidden" name="result" id="result" value="">
				<?php } ?>

				<p class="form-row">
					<?php _e( 'Please click the big button below to pay.', 'wc-gateway-nimiq' ); ?>
				</p>
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

		private function get_param( $key, $method = 'get' ) {
			$data = $method === 'get'
				? $_GET
				: ( $method === 'post'
					? $_POST
					: $method
				);

			if ( !isset( $data[ $key ] ) ) return null;
			return sanitize_text_field( $data[ $key ] );
		}

		/**
		 * This is a required method for WC gateways, to be used by WC internal processing
		 *
		 * DO NOT USE FOR CUSTOM INTEGRATION!
		 * Use `validate_response` instead!
		 */
		public function validate_fields( $order_id = null ) {
			if ( !isset( $_POST[ 'rpcId' ] ) ) return true;

			return $this->validate_response( $order_id, $_POST );
		}

		/**
		 * Response validation to be used by custom integrations
		 */
		public function validate_response( $order_id = null, $response ) {
			$status = $this->get_param( 'status', $response );
			$result = $this->get_param( 'result', $response );

			if ( $status !== 'OK' || empty( $result ) ) return false;

			$result = str_replace( "\\", "", $result ); // JSON string may be "sanitized" with backslashes, which is a JSON syntax error
			$result = json_decode( $result );

			// Get order_id from GET param (for when RPC behavior is native 'popup')
			if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
			}

			if ( empty( $order_id ) ) return false;

			$order = wc_get_order( $order_id );

			$currency = Order_Utils::get_order_currency( $order, false );

			if ( $currency === 'nim' ) {
				$transaction_hash = $result->hash;
				$customer_nim_address = $result->raw->sender;

				if ( !$transaction_hash ) {
					wc_add_notice( __( 'You must submit the Nimiq transaction first.', 'wc-gateway-nimiq' ), 'error' );
					return false;
				}

				if ( strlen( $transaction_hash) !== 64 ) {
					wc_add_notice( __( 'Invalid transaction hash (' . $transaction_hash . '). Please contact support with this error message.', 'wc-gateway-nimiq' ), 'error' );
					return false;
				}

				$order->update_meta_data( 'transaction_hash', $transaction_hash );
				$order->update_meta_data( 'customer_nim_address', $customer_nim_address );
				$order->delete_meta_data( 'checkout_csrf_token' );
				$order->save();

				return true;
			} else {
				return $result->success ?: false;
			}
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

			if ( !isset( $_POST[ 'rpcId' ] ) ) {
				// Remove cart
				WC()->cart->empty_cart();

				$order->update_status( 'pending-payment', __( 'Awaiting payment.', 'wc-gateway-nimiq' ) );

				if ( $this->get_option( 'rpc_behavior' ) === 'redirect' ) {
					// Redirect to Hub for payment

					$target = $this->get_option( 'network' ) === 'main' ? 'https://hub.nimiq.com' : 'https://hub.nimiq-testnet.com';
					$id = 42;
					$returnUrl = get_site_url() . '/wc-api/WC_Gateway_Nimiq?id=' . $order_id;
					$command = 'checkout';
					$args = [ $this->get_payment_request( $order_id ) ];
					$responseMethod = 'post';

					include_once( plugin_dir_path( __FILE__ ) . 'nimiq-utils/RpcUtils.php' );

					$url = Nimiq\Utils\RpcUtils::prepareRedirectInvocation(
						$target,
						$id,
						$returnUrl,
						$command,
						$args,
						$responseMethod
					);

					return [
						'result'   => 'success',
						'redirect' => $url
					];
				}

				// Return payment-page redirect from where the Hub popup is opened
				return array(
					'result' 	=> 'success',
					'redirect'	=> $order->get_checkout_payment_url( $on_checkout = false )
				);
			}

			// Mark as on-hold (we're awaiting transaction validation)
			$order->update_status( 'on-hold', __( 'Awaiting transaction validation.', 'wc-gateway-nimiq' ) );

			// Reduce stock levels
			wc_reduce_stock_levels( $order_id );

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
				if( empty( $this->get_option( 'nimiq_address' ) ) ) {
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

// Includes that register actions and filters and are thus self-calling
include_once( plugin_dir_path( __FILE__ ) . 'includes/nimiq_currency.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/bulk_actions.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/validation_scheduler.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/webhook.php' );

// Utility classes called from other code
include_once( plugin_dir_path( __FILE__ ) . 'includes/order_utils.php' );
include_once( plugin_dir_path( __FILE__ ) . 'includes/crypto_manager.php' );
