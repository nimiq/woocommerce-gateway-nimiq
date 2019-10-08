(async function($) {
    'use strict';

    // Check the T&C box, which was already checked on the page before
    $('input#terms').prop('checked', true);

    // When behavior is redirect, don't do anything, as redirect is triggered from server-side on form-submit
    if (CONFIG.RPC_BEHAVIOR === 'redirect') return;

    var request;
    try {
        request = JSON.parse(CONFIG.REQUEST);
    } catch (error) {
        alert('Could not decode JSON: ' + error.message);
        return;
    }

    // Status variables
    var awaiting_transaction_signing = false;
    var nim_payment_completed = false;

    var checkout_pay_order_hook = function(event) {
        if (nim_payment_completed) return true;

        event.preventDefault();

        if (awaiting_transaction_signing) return false;

        // Process crypto payment (async)
        do_payment();
    }

    var do_payment = async function() {
        awaiting_transaction_signing = true;
        $('button#place_order').prop('disabled', true);

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
        console.log("signed_transaction", signed_transaction);

        $('button#place_order').prop('disabled', true);

        // Write result into the hidden inputs
        $('#rpcId').val(42); // Required to trigger validate_fields()
        $('#status').val('OK'); // Required to pass validate_fields()
        $('#result').val(JSON.stringify(signed_transaction));

        awaiting_transaction_signing = false;

        $('#nim_gateway_info_block').addClass('hidden');
        $('#nim_payment_complete_block').removeClass('hidden');

        nim_payment_completed = true;

        checkout_form.submit();
    }

    var on_signing_error = function(e) {
        console.error(e);
        // if (e.message !== 'CANCELED' && e.message !== 'Connection was closed') alert('Error: ' + e.message);
        if (e.message !== 'CANCELED' && e.message !== 'Connection was closed' && e !== 'Connection was closed' && e.message !== 'Request aborted') {
            alert('Error: ' + e.message);
        }
        awaiting_transaction_signing = false;
        // Reenable checkout button
        $('button#place_order').prop('disabled', false);
        jQuery('#order_review').unblock();
    }

    // Add submit event listener to form
    var checkout_form = $('form#order_review');
    checkout_form.on('submit', checkout_pay_order_hook);

    // Initialize HubApi
    window.hubApi = new HubApi(CONFIG.HUB_URL);
})(jQuery);
