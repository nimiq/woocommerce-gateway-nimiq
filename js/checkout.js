(async function($) {
    'use strict';

    // Disable submit button until ready
	$('input#terms').prop('checked', true);

    // Status variables
    var awaiting_transaction_signing = false;
    var nim_payment_completed = false;

    var checkout_pay_order_hook = function(event) {
        if (nim_payment_completed) return true;

        event.preventDefault();

        if (awaiting_transaction_signing) return false;

        // Process NIM payment (async)
        do_payment();
    }

    var do_payment = async function() {
        awaiting_transaction_signing = true;
        $('button#place_order').prop('disabled', true);

        // Generate transaction object
        var request = {
            appName: CONFIG.SITE_TITLE,
            shopLogoUrl: CONFIG.SHOP_LOGO_URL || undefined,
            recipient: CONFIG.STORE_ADDRESS,
            value: parseFloat(CONFIG.ORDER_TOTAL),
            fee: parseFloat(CONFIG.TX_FEE),
            extraData: new Uint8Array(JSON.parse(CONFIG.TX_MESSAGE)),
        };

        // Start Accounts action
        try {
            var signed_transaction = await accountsClient.checkout(request);
            on_signed_transaction(signed_transaction);
        } catch (e) {
            on_signing_error(e);
            return;
        }
    }

    var on_signed_transaction = function(signed_transaction) {
        console.log("signed_transaction", signed_transaction);

        // Make sure payment button is disabled when receiving a redirect response
        $('button#place_order').prop('disabled', true);

        // Write transaction hash and sender address into the hidden inputs
        $('#transaction_hash').val(signed_transaction.hash);
        $('#customer_nim_address').val(signed_transaction.raw.sender);

        awaiting_transaction_signing = false;

        $('#nim_account_selector_block').addClass('hidden');
        $('#nim_payment_complete_block').removeClass('hidden');

        nim_payment_completed = true;

        checkout_form.submit();
    }

    var on_signing_error = function(e) {
        console.error(e);
        awaiting_transaction_signing = false;
        // Reenable checkout button
        $('button#place_order').prop('disabled', false);
    }

    // Add submit event listener to form, preventDefault()
    var checkout_form = $('form#order_review');
    checkout_form.on('submit', checkout_pay_order_hook);

    let redirectBehavior = null;
    if (CONFIG.RPC_BEHAVIOR === 'redirect') {
        redirectBehavior = new AccountsClient.RedirectRequestBehavior(window.location.href);
    }

    // Initialize AccountsClient
    window.accountsClient = new AccountsClient(CONFIG.ACCOUNTS_URL, redirectBehavior);

    if (CONFIG.RPC_BEHAVIOR === 'redirect') {
        // Check for a redirect response
        accountsClient.on(AccountsClient.RequestType.CHECKOUT, on_signed_transaction, on_signing_error);
        accountsClient.checkRedirectResponse();
    }
})(jQuery);
