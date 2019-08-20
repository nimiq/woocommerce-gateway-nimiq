(function($) {
    'use strict';

    /**
     * @param {string} service_slug
     * @param {boolean} is_setup
     */
    function on_price_service_change(service_slug, is_setup){
        console.debug('Price service selected:', service_slug);

        // Disable all non-common conditional fields
        var conditional_fields = [
            // '#conditional_field_id',
        ];
        // $(conditional_fields.join(',')).closest('tr').addClass('hidden');

        // Enable service-specific fields
        switch (service_slug) {
            case 'coingecko':
                break;
            // case '':
            //    $('#conditional_field_id').closest('tr').removeClass('hidden'); break;
        }

        price_service = service_slug;
        if (!is_setup) toggle_common_fields();
    }

    /**
     * @param {string} service_slug
     * @param {boolean} is_setup
     */
    function on_validation_service_change(service_slug, is_setup) {
        console.debug('Validation service selected:', service_slug);

        // Disable all non-common conditional fields
        var conditional_fields = [
            '#woocommerce_nimiq_gateway_jsonrpc_nimiq_url',
            '#woocommerce_nimiq_gateway_jsonrpc_nimiq_username',
            '#woocommerce_nimiq_gateway_jsonrpc_nimiq_password',
            // '#conditional_field_id',
        ];
        $(conditional_fields.join(',')).closest('tr').addClass('hidden');

        // Enable service-specific fields
        switch (service_slug) {
            case 'nimiq_watch':
                break;
            case 'json_rpc_nim':
                $('#woocommerce_nimiq_gateway_jsonrpc_nimiq_url, ' +
                  '#woocommerce_nimiq_gateway_jsonrpc_nimiq_username, ' +
                  '#woocommerce_nimiq_gateway_jsonrpc_nimiq_password')
                    .closest('tr').removeClass('hidden');
                break;
            // case '':
            //    $('#conditional_field_id').closest('tr').removeClass('hidden'); break;
        }

        validation_service = service_slug;
        if (!is_setup) toggle_common_fields();
    }

    function toggle_common_fields() {
        // Disable all conditional fields
        var common_fields = [
            '#woocommerce_nimiq_gateway_nimiqx_api_key',
            // '#conditional_field_id',
        ];
        $(common_fields.join(',')).closest('tr').addClass('hidden');

        // Enable required fields
        if (price_service === 'nimiqx' || validation_service === 'nimiqx') {
            $('#woocommerce_nimiq_gateway_nimiqx_api_key')
                .closest('tr').removeClass('hidden');
        }
    }

    // Set up event handlers
    const $price_service_select = $('#woocommerce_nimiq_gateway_price_service');
    const $validation_service_select = $('#woocommerce_nimiq_gateway_validation_service_nim');

    let price_service = $price_service_select.val();
    let validation_service = $validation_service_select.val();

    $price_service_select.on('change', function(event) {
        on_price_service_change(event.target.value);
    });

    $validation_service_select.on('change', function(event) {
        on_validation_service_change(event.target.value);
    });

    on_price_service_change(price_service, true);
    on_validation_service_change(validation_service, true);
    toggle_common_fields();
})(jQuery);
