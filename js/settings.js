(function($) {
    'use strict';

    /**
     * @param {string} service_slug
     * @param {boolean} is_setup
     */
    function on_price_service_change(service_slug, is_setup) {
        console.debug('Price service selected:', service_slug);

        // Disable all non-common conditional fields
        var conditional_fields = [
            '#woocommerce_nimiq_gateway_fee_nim',
            '#woocommerce_nimiq_gateway_fee_btc',
            '#woocommerce_nimiq_gateway_fee_eth',
            // '#conditional_field_id',
        ];
        $(conditional_fields.join(',')).closest('tr').addClass('hidden');

        // Enable service-specific fields
        switch (service_slug) {
            case 'coingecko':
                $('#woocommerce_nimiq_gateway_fee_nim, ' +
                  '#woocommerce_nimiq_gateway_fee_btc, ' +
                  '#woocommerce_nimiq_gateway_fee_eth')
                    .closest('tr').removeClass('hidden');
                break;
            case 'fastspot':
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

    // Set up field toggle event handlers
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

    // Add click listener to toggle advanced section
    $('#woocommerce_nimiq_gateway_section_advanced').click(function() {
        console.log('boop')
        $('#woocommerce_nimiq_gateway_section_advanced + p + table').toggle('fast');
    });

    // Add image preview to shop logo field
    const $shop_logo_url = $('#woocommerce_nimiq_gateway_shop_logo_url');
    function update_shop_logo_preview() {
        const src = $shop_logo_url.val() || $shop_logo_url.data('site-icon');
        console.log(src);
        const $preview = $('#nimiq_shop_logo_preview');
        if ($preview.length) {
            $preview.attr('src', src);
        } else {
            $shop_logo_url.after('<img id="nimiq_shop_logo_preview" src="' + src + '">');
        }
    }
    $shop_logo_url.on('input', update_shop_logo_preview);
    update_shop_logo_preview();

    // Add change listener for Bitcoin xpub
    const $bitcoin_xpub = $('#woocommerce_nimiq_gateway_bitcoin_xpub');
    $bitcoin_xpub.on('input', function() {
        const xpub = $bitcoin_xpub.val();
        if (!xpub) return;
        let type;
        switch (xpub.substr(0, 4)) {
            case 'xpub':
            case 'tpub':
                type = 'bip-44';
                break;
            case 'zpub':
            case 'vpub':
                type = 'bip-84';
                break;
            default: break; // TODO Show error feedback to user
        }
        $('#woocommerce_nimiq_gateway_bitcoin_xpub_type').val(type);
    });

    // Add asterix to required fields
    $('.required').after('<span>*</span>');

})(jQuery);
