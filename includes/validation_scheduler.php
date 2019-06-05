<?php

$plugin_main_file = dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'woocommerce-gateway-nimiq.php';
require_once( $plugin_main_file );

register_activation_hook( $plugin_main_file, 'wc_nimiq_start_validation_schedule' );
register_deactivation_hook( $plugin_main_file, 'wc_nimiq_end_validation_schedule' );
add_action( 'wc_nimiq_scheduled_validation', 'wc_nimiq_validate_orders' );

function wc_nimiq_start_validation_schedule() {
    if ( ! as_next_scheduled_action( 'wc_nimiq_scheduled_validation' ) ) {
        $next_quarter_hour = ceil(time() / (15 * 60)) * (15 * 60);

        wc_nimiq_gateway_init();
        $gateway = new WC_Gateway_Nimiq();

        $interval_minutes = intval( $gateway->get_option( 'validation_interval' ) ) ?: 30;
        $interval = $interval_minutes * 60; // Convert to seconds

        as_schedule_recurring_action( $next_quarter_hour, $interval, 'wc_nimiq_scheduled_validation' );
    }
}

function wc_nimiq_end_validation_schedule() {
    as_unschedule_action( 'wc_nimiq_scheduled_validation' );
}

function wc_nimiq_validate_orders() {
    $logger = wc_get_logger();
    $log_context = array( 'source' => 'wc-gateway-nimiq' );

    // Get all orders that are on-hold
    $orders = get_posts( [
        'post_type'   => 'shop_order',
        'post_status' => 'wc-on-hold',
    ] );

    $logger->info( 'Processing ' . _n( count( $orders ) . ' order', count( $orders ) . ' orders', count( $orders ), 'woocommerce' ) . ' for validation.', $log_context );

    if ( empty( $orders ) ) return;

    $ids = array_reduce( $orders, function( $acc, $order ) {
        $acc[] = $order->ID;
        return $acc;
    }, [] );

    // $logger->info( 'Processing IDs [' . implode( ', ', $ids ) . ']', $log_context );

    $gateway = new WC_Gateway_Nimiq();
    $validation_results = _do_bulk_validate_transactions( $gateway, $ids );

    if ( ! empty( $validation_results[ 'errors' ] ) ) {
        foreach ( $validation_results[ 'errors' ] as $error ) {
            $logger->error( $error, $log_context );
        }
        // TODO: Send error email to admin?
    }

    $count_orders_updated = $validation_results[ 'changed' ] ?: 0;
    $logger->info( 'Updated ' . _n( $count_orders_updated . ' order', $count_orders_updated . ' orders', $count_orders_updated, 'woocommerce' ) . '.', $log_context );
}
