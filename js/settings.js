(function($) {
    'use strict';

    function on_validation_service_change(service_slug) {
        console.debug('Validation service selected:', service_slug);

        // Disable all conditional fields
        var conditional_fields = [
            '#woocommerce_nimiq_gateway_nimiqx_api_key',
            // '#conditional_field_id',
        ];
        $(conditional_fields.join(',')).closest('tr').addClass('hidden');

        // Enable service-specific fields
        switch (service_slug) {
            case 'nimiq_watch': break;
            case 'nimiqx':
                $('#woocommerce_nimiq_gateway_nimiqx_api_key').closest('tr').removeClass('hidden');
                break;
            // case '': $('#conditional_field_id').closest('tr').removeClass('hidden'); break;
        }
    }

    $('#woocommerce_nimiq_gateway_validation_service').on('change', function(event) {
        on_validation_service_change(event.target.value);
    }).change();
})(jQuery);
