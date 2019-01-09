(async function() {
    'use strict';

    // Disable submit button until ready
	jQuery( 'input#terms' ).prop('checked', true);

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
        document.querySelector('button#place_order').setAttribute('disabled', 'disabled');

        // Generate transaction object
        var request = {
            appName: CONFIG.SITE_TITLE,
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
        document.querySelector('button#place_order').setAttribute('disabled', 'disabled');

        // Write transaction hash and sender address into the hidden inputs
        var transaction_hash_field = document.getElementById('transaction_hash');
        transaction_hash_field.value = base64ToHex(signed_transaction.hash);
        var customer_nim_address_field = document.getElementById('customer_nim_address');
        customer_nim_address_field.value = signed_transaction.sender;

        awaiting_transaction_signing = false;

        document.getElementById('nim_account_selector_block').classList.add('hidden');
        document.getElementById('nim_payment_complete_block').classList.remove('hidden');

        nim_payment_completed = true;

        checkout_form.submit();
    }

    var on_signing_error = function(e) {
        console.error(e);
        awaiting_transaction_signing = false;
        // Reenable checkout button
        document.querySelector('button#place_order').removeAttribute('disabled');
    }

    // Add submit event listener to form, preventDefault()
    var checkout_form = jQuery( 'form#order_review' );
    checkout_form.on( 'submit', checkout_pay_order_hook );

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
})();

function base64ToHex(str) {
    for (var i = 0, bin = atob(str.replace(/[ \r\n]+$/, "")), hex = []; i < bin.length; i++) {
        let tmp = bin.charCodeAt(i).toString(16);
        if (tmp.length === 1) tmp = "0" + tmp;
        hex[hex.length] = tmp;
    }
    return hex.join("");
}
