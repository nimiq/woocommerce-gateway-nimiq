(async function() {
    var customer_nim_address_field = document.getElementById('customer_nim_address');
    var transaction_hash_field = document.getElementById('transaction_hash');

    var accounts_loaded = false;
    var network_loaded = false;
    var awaiting_keyguard_signing = false;
    var nim_payment_completed = false;
    var current_blockchain_height = 0;

    // Add submit event listener to form, preventDefault()
    var checkout_form = jQuery('form.checkout');
    checkout_form.on( 'checkout_place_order_nimiq_gateway', function() {
        if (nim_payment_completed) return true;
        if (!accounts_loaded || awaiting_keyguard_signing) return false;
        // TODO Disable submit button until ready

        // Check if a sender NIM address is selected

        // Generate transaction object

        // Start Keyguard action

        // In parallel, initialize network iframe and connect

        return false;
    });

    // Disable submit button until accounts are loaded
    // jQuery(document).on('update_checkout', function() {
    //     console.log("disabling");
    //     setTimeout(() => document.getElementById('place_order').disabled = true);
    // });

    // Start interval to fetch block height every 30 minutes
    // (Make sure it's executed immediately as well)

    // Await keyguard-client connection
    window.keyguard = await keyguardClient.create(
        'http://keyguard.localhost:5000/libraries/keyguard/src',
        new keyguardClient.Policies.ShopPolicy,
        function() { return {}; }
    );

    // Get accounts
    var accounts = await keyguard.list();

    // Fill select

    // Hide loading message, unhide select, enable payment button

    // When keyguard returns, write transaction hash into the hidden input

    // Await network and relay transaction

    // Await "transaction-relayed" event from network and submit form, for real this time

    // Let the user handle potential validation errors
    // (DEBUG: check that the transaction_hash is still filled out on validation error)
})();
