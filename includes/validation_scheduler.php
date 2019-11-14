<?php

$plugin_main_file = dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'woo-nimiq-gateway.php';
require_once( $plugin_main_file );

register_activation_hook( $plugin_main_file, 'wc_nimiq_start_validation_schedule' );
register_deactivation_hook( $plugin_main_file, 'wc_nimiq_end_validation_schedule' );
add_action( 'wc_nimiq_scheduled_validation', 'wc_nimiq_validate_orders' );

function wc_nimiq_start_validation_schedule() {
    if ( ! as_next_scheduled_action( 'wc_nimiq_scheduled_validation' ) ) {
        $next_quarter_hour = ceil(time() / (15 * 60)) * (15 * 60);

        wc_nimiq_gateway_init();
        $gateway = new WC_Gateway_Nimiq();

        $interval_minutes = intval( $gateway->get_setting( 'validation_interval' ) ) ?: 30;
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

    // Get all orders that are on-hold or pending, and have a crypto currency set
    $posts = get_posts( [
        'post_type'   => 'shop_order',
        'post_status' => [ 'wc-on-hold', 'wc-pending' ],
        'meta_key' => 'order_crypto_currency',
        'meta_compare' => '!=',
        'meta_value' => '',
    ] );

    $logger->info( sprintf( _n( 'Processing %s order', 'Processing %s orders', count( $posts ), 'wc-gateway-nimiq' ), count( $posts ) ), $log_context );

    if ( empty( $posts ) ) return;

    $ids = array_reduce( $posts, function( $acc, $post ) {
        $acc[] = $post->ID;
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
    $logger->info( sprintf( _n( 'Updated %s order', 'Updated %s orders', $count_orders_updated, 'wc-gateway-nimiq' ), $count_orders_updated ) . '.', $log_context );
}
