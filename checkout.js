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
    if (nimiq_gateway_accounts.length === 1) customer_nim_address_field.removeChild(customer_nim_address_field.getElementsByTagName('option')[0]);

    // Hide loading message
    document.getElementById('nim_account_loading_block').classList.add('hidden');

	// Check if tx hash is already set at the end of the event loop, to give woocommerce time to re-fill the field
	setTimeout(function() {
		if (document.getElementById('transaction_hash').value !== '') {
			// Show success message
			document.getElementById('nim_payment_complete_block').classList.remove('hidden');
		} else {
        // Show account selector
        document.getElementById('nim_account_selector_block').classList.remove('hidden');
		}
	});

    nimiq_gateway_accounts_loaded = true;
}

(async function() {
    'use strict';

    // Status variables
    var awaiting_keyguard_signing = false;
    var awaiting_network_relaying = false;
    var nim_payment_completed = false;
    var current_blockchain_height = 0;

    var checkout_pay_order_hook = function(event) {
        if (nim_payment_completed) return true;
        // TODO Disable submit button until ready
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
        process_payment(sender_address);

        // In parallel, initialize network iframe
        networkClient.launch();

        // Return false to prevent form submission
        return false;
    }

    var process_payment = async function(sender_address) {
        awaiting_keyguard_signing = true;

        // Generate transaction object
        var transaction = {
            sender: sender_address,
            recipient: CONFIG.STORE_ADDRESS,
            value: ORDER_TOTAL,
            fee: 0,
            validityStartHeight: current_blockchain_height,
            extraData: CONFIG.TX_MESSAGE + ' [' + ORDER_ID + ']',
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

			// TODO Add a delay here to allow the tx to get relayed to more peers?

            jQuery( 'form.checkout' ).removeClass( 'processing' ).submit();

            // Let the user handle potential validation errors
            // (DEBUG: check that the transaction_hash is still filled out on validation error)
        });

        try {
            await network.relayTransaction(signed_transaction);
        } catch (e) {
            jQuery( '#payment' ).unblock();
            alert(e.message || e);
        }
    }

    // Add submit event listener to form, preventDefault()

    // Disable submit button until accounts are loaded
    // jQuery(document).on('update_checkout', function() {
    //     console.log("disabling");
    //     setTimeout(() => document.getElementById('place_order').disabled = true);
    // });

    var checkout_form = jQuery('form#order_review');
    checkout_form.on( 'submit', checkout_pay_order_hook );

    // Fetch block height now and every 30 minutes
    var get_current_block_height = function() {
        var request = new XMLHttpRequest();
        request.open('GET', CONFIG.API_PATH + '/latest/1', true);

        request.onload = function() {
            if (this.status >= 200 && this.status < 400) {
                // Success!
                var data = JSON.parse(this.response);
                current_blockchain_height = data[0].height;
                console.log("Got blockheight from nimiq.watch:", current_blockchain_height);

                setTimeout(get_current_block_height, 30 * 60 * 1000); // Update again in 30 minutes
            } else {
                // We reached our target server, but it returned an error
                setTimeout(get_current_block_height, 5 * 1000); // Retry in 5 seconds
            }
        };
        request.onerror = function() {
            // There was a connection error of some sort
            setTimeout(get_current_block_height, 5 * 1000); // Retry in 5 seconds
        };
        request.send();
    }
    get_current_block_height();

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
