(function($) {
    'use strict';

    // When behavior is redirect, don't do anything, as redirect is triggered from server-side on form-submit
    if (CONFIG.RPC_BEHAVIOR === 'redirect') return;

    var request;
    try {
        request = JSON.parse(CONFIG.REQUEST);
    } catch (error) {
        alert('Could not decode CONFIG JSON: ' + error.message);
        return;
    }

    // Status variables
    var awaiting_transaction_signing = false;
    var nim_payment_received = false;

    var checkout_pay_order_hook = function(event) {
        if (nim_payment_received) return true;

        event.preventDefault();

        if (awaiting_transaction_signing) return false;

        // Process crypto payment (async)
        do_payment();
    }

    var do_payment = async function() {
        awaiting_transaction_signing = true;
        $pay_button.prop('disabled', true);

        // Start Hub action
        try {
            var signed_transaction = await hubApi.checkout(request);
            on_signed_transaction(signed_transaction);
        } catch (e) {
            on_signing_error(e);
            return;
        }
    }

    var on_signed_transaction = function(signed_transaction) {
        console.debug("signed_transaction", signed_transaction);

        $pay_button.prop('disabled', true);

        // Write result into the hidden inputs
        $('#status').val('OK'); // Required to pass validate_fields()
        $('#result').val(JSON.stringify(signed_transaction));

        awaiting_transaction_signing = false;

        $('#nim_gateway_info_block').addClass('hidden');
        $('#nim_payment_received_block').removeClass('hidden');

        nim_payment_received = true;

        $checkout_form.submit();
    }

    var on_signing_error = function(e) {
        console.error(e);
        if (e.message !== 'CANCELED' && e.message !== 'Connection was closed' && e !== 'Connection was closed') {
            alert('Error: ' + e.message);
        }
        awaiting_transaction_signing = false;
        // Reenable checkout button
        $pay_button.prop('disabled', false);
    }

    // Store reference to payment button
    var $pay_button = $('#nim_pay_button');

    // Add submit event listener to form
    var $checkout_form = $('form#pay_with_nimiq');
    $checkout_form.on('submit', checkout_pay_order_hook);

    // Initialize HubApi
    var hubApi = new HubApi(CONFIG.HUB_URL);
})(jQuery);
