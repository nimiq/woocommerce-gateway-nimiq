// Globals
var nimiq_gateway_accounts_loaded = false;
var nimiq_gateway_accounts = null;

function fill_accounts_selector() {
    'use strict';

    if (nimiq_gateway_accounts === null) return;

    var customer_nim_address_field = document.getElementById('customer_nim_address');
    for (var i = 0; i < nimiq_gateway_accounts.length; i++) {
        var opt = document.createElement('option');
        opt.textContent = nimiq_gateway_accounts[i].address;
        customer_nim_address_field.appendChild(opt);
    }

    // Hide loading message
    document.getElementById('nim_account_loading_block').classList.add('hidden');

    if (nimiq_gateway_accounts.length === 0) {
        document.getElementById('nim_no_account_block').classList.remove('hidden');
        return;
    }

    if (nimiq_gateway_accounts.length === 1) {
        customer_nim_address_field.removeChild(customer_nim_address_field.getElementsByTagName('option')[0]);
    }

    document.getElementById('nim_account_selector_block').classList.remove('hidden');

    nimiq_gateway_accounts_loaded = true;
    jQuery( 'button#place_order' ).attr("disabled", false);
}

(async function() {
    'use strict';

    // Disable submit button until ready
    jQuery( 'button#place_order' ).attr('disabled', 'disabled');
	jQuery( 'input#terms' ).prop('checked', true);

    // Status variables
    var awaiting_keyguard_signing = false;
    var awaiting_network_relaying = false;
    var nim_payment_completed = false;
    var current_blockchain_height = CONFIG.HEIGHT;
    console.log('Got blockheight from nimiqx:', current_blockchain_height);

    var checkout_pay_order_hook = function(event) {
        if (nim_payment_completed) return true;

        event.preventDefault();

        if (!nimiq_gateway_accounts_loaded || awaiting_keyguard_signing || awaiting_network_relaying) return false;

        // Check if a sender NIM address is selected
        var sender_address = document.getElementById('customer_nim_address').value;
        if (!sender_address || sender_address === '') {
            alert('Please select which account you want to send from.');
            return false;
        }

        if (current_blockchain_height === 0) {
            var height = prompt("The current blockchain height could not be automatically determined, please enter the current blockchain height:");
            if (!height) return false;
            if (isNaN(height)) {
                alert('That was not a number.');
                return false;
            }
            current_blockchain_height = Math.round(height);
        }

        // Process NIM payment (async)
        do_payment(sender_address);

        // In parallel, initialize network iframe
        networkClient.launch();
    }

    var do_payment = async function(sender_address) {
        awaiting_keyguard_signing = true;

        // Generate transaction object
        var transaction = {
            sender: sender_address,
            recipient: CONFIG.STORE_ADDRESS,
            value: CONFIG.ORDER_TOTAL,
            fee: CONFIG.TX_FEE,
            validityStartHeight: current_blockchain_height,
            extraData: new Uint8Array(JSON.parse(CONFIG.TX_MESSAGE)),
            network: CONFIG.NETWORK
        };

        // Find account type
        var account = nimiq_gateway_accounts.find(function(a) {
            return a.address === sender_address;
        });

        // Start Keyguard action
        var sign_action;
        if (account.type === 'high') sign_action = keyguard.signSafe;
        if (account.type === 'low')  sign_action = keyguard.signWallet;
        var signed_transaction;
        try {
            signed_transaction = await sign_action(transaction);
        } catch (e) {
            console.error(e);
            awaiting_keyguard_signing = false;
            // Reenable checkout button
            document.querySelector('button#place_order').removeAttribute('disabled');
            return;
        }

        console.log("signed_transaction", signed_transaction);

        awaiting_network_relaying = true;
        awaiting_keyguard_signing = false;
        jQuery( '#payment' ).block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        // Await network and relay transaction
        window.network = await networkClient.rpcClient;
        window.network_events = await networkClient.eventClient;

        // Await "transaction-relayed" event from network and submit form, for real this time
        network_events.on('nimiq-transaction-relayed', function(relayed_transaction) {
            if (relayed_transaction.hash !== signed_transaction.hash) return;

            // This check stops the form from auto-submitting each time the tx is relayed to a new peer
            if (awaiting_network_relaying === false) return;
            awaiting_network_relaying = false;

            // When network returns, write transaction hash into the hidden input
            var transaction_hash_field = document.getElementById('transaction_hash');
            transaction_hash_field.value = base64ToHex(signed_transaction.hash);

            document.getElementById('nim_account_selector_block').classList.add('hidden');
            document.getElementById('nim_payment_complete_block').classList.remove('hidden');

            nim_payment_completed = true;

            // Submit form after short delay to allow more peers to request the transaction
            setTimeout(function() {
                checkout_form.submit();
            }, 2000);
        });

        try {
            await network.relayTransaction(signed_transaction);
        } catch (e) {
            jQuery( '#payment' ).unblock();
            alert(e.message || e);
        }
    }

    // Add submit event listener to form, preventDefault()
    var checkout_form = jQuery( 'form#order_review' );
    checkout_form.on( 'submit', checkout_pay_order_hook );

    // Await keyguard-client connection
    window.keyguard = await keyguardClient.create(
        CONFIG.KEYGUARD_PATH,
        new keyguardClient.Policies.ShopPolicy,
        function() { return {}; }
    );

    // Get accounts
    nimiq_gateway_accounts = await keyguard.list();

    // Fill select
    fill_accounts_selector();
})();

function base64ToHex(str) {
    for (var i = 0, bin = atob(str.replace(/[ \r\n]+$/, "")), hex = []; i < bin.length; i++) {
        let tmp = bin.charCodeAt(i).toString(16);
        if (tmp.length === 1) tmp = "0" + tmp;
        hex[hex.length] = tmp;
    }
    return hex.join("");
}
