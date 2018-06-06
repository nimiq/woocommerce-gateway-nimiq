var keyguardClient = (function () {
'use strict';

class Reflection {
    /** @param {Object} proto
     *
     * @returns {Set<string>}
     */
    static userFunctions(proto) {
        return new Set(Reflection._deepFunctions(proto).filter(name => {
            return name !== 'constructor'
                && name !== 'fire'
                && name[0] !== '_';
        }));
    }

    /** @param {Object} proto
     *
     * @returns {string[]}
     */
    static _deepFunctions(proto) {
        if (!proto || proto === Object.prototype) return [];

        const ownProps = Object.getOwnPropertyNames(proto);

        const ownFunctions = ownProps.filter(name => {
            const desc = Object.getOwnPropertyDescriptor(proto, name);
            return !!desc && typeof desc.value === 'function';
        });

        const deepFunctions = Reflection._deepFunctions(Object.getPrototypeOf(proto));

        return [...ownFunctions, ...deepFunctions];
    }
}

class Random {
    static getRandomId() {
        let array = new Uint32Array(1);
        crypto.getRandomValues(array);
        return array[0];
    }

    static pickRandom(array = []) {
        if (array.length < 1) return null;
        return array[Math.floor(Math.random() * array.length)];
    }
}

class RPC {
    /**
     * @param {Window} targetWindow
     * @param {string} interfaceName
     * @param {string} [targetOrigin]
     * @returns {Promise}
     */
    static async Client(targetWindow, interfaceName, targetOrigin = '*') {
        return new Promise((resolve, reject) => {
            let connected = false;

            const interfaceListener = (message) => {
                if (message.source !== targetWindow
                    || message.data.status !== 'OK'
                    || message.data.interfaceName !== interfaceName
                    || (targetOrigin !== '*' && message.origin !== targetOrigin)) return;

                self.removeEventListener('message', interfaceListener);

                connected = true;

                resolve( new (RPC._Client(targetWindow, targetOrigin, interfaceName, message.data.result))() );
            };

            self.addEventListener('message', interfaceListener);


            let connectTimer;
            const timeoutTimer = setTimeout(() => {
                reject(new Error('Connection timeout'));
                clearTimeout(connectTimer);
            }, 30000);

            const tryToConnect = () => {
                if (connected) {
                    clearTimeout(timeoutTimer);
                    return;
                }

                try {
                    targetWindow.postMessage({ command: 'getRpcInterface', interfaceName, id: 0 }, targetOrigin);
                } catch (e){
                    console.log('postMessage failed:' + e);
                }
                connectTimer = setTimeout(tryToConnect, 1000);
            };

            connectTimer = setTimeout(tryToConnect, 100);
        });
    }


    /**
     * @param {Window} targetWindow
     * @param {string} interfaceName
     * @param {array} functionNames
     * @returns {Class}
     * @private
     */
    static _Client(targetWindow, targetOrigin, interfaceName, functionNames) {
        const Client = class {
            constructor() {
                this.availableMethods = functionNames;
                // Svub: Code smell that _targetWindow and _waiting are visible outside. Todo later!
                /** @private
                 *  @type {Window} */
                this._targetWindow = targetWindow;
                this._targetOrigin = targetOrigin;
                /** @private
                 *  @type {Map.<number,{resolve:Function,error:Function}>} */
                this._waiting = new Map();
                self.addEventListener('message', this._receive.bind(this));
            }

            close() {
                self.removeEventListener('message', this._receive.bind(this));
            }

            _receive({ source, origin, data }) {
                // Discard all messages from unwanted sources
                // or which are not replies
                // or which are not from the correct interface
                if (source !== this._targetWindow
                    || !data.status
                    || data.interfaceName !== interfaceName
                    || (this._targetOrigin !== '*' && origin !== this._targetOrigin)) return;

                const callback = this._waiting.get(data.id);

                if (!callback) {
                    console.log('Unknown reply', data);
                } else {
                    this._waiting.delete(data.id);

                    if (data.status === 'OK') {
                        callback.resolve(data.result);
                    } else if (data.status === 'error') {
                        const { message, stack, code } = data.result;
                        const error = new Error(message);
                        error.code = code;
                        error.stack = stack;
                        callback.error(error);
                    }
                }
            }

            /**
             * @param {string} command
             * @param {object[]} [args]
             * @returns {Promise}
             * @private
             */
            _invoke(command, args = []) {
                return new Promise((resolve, error) => {
                    const obj = { command, interfaceName, args, id: Random.getRandomId() };
                    this._waiting.set(obj.id, { resolve, error });
                    this._targetWindow.postMessage(obj, '*');
                    // no timeout for now, as some actions require user interactions
                    // todo maybe set timeout via parameter?
                    //setTimeout(() => error(new Error ('request timeout')), 10000);
                });
            }
        };

        for (const functionName of functionNames) {
            Client.prototype[functionName] = function (...args) {
                return this._invoke(functionName, args);
            };
        }

        return Client;
    }

    /**
     * @param {Class} clazz The class whose methods will be made available via postMessage RPC
     * @param {boolean} [useAccessControl] If set, an object containing callingWindow and callingOrigin will be passed as first arguments to each method
     * @param {string[]} [rpcInterface] A whitelist of function names that are made available by the server
     * @return {T extends clazz}
     */
    static Server(clazz, useAccessControl, rpcInterface) {
        return new (RPC._Server(clazz, useAccessControl, rpcInterface))();
    }

    static _Server(clazz, useAccessControl, rpcInterface) {
        const Server = class extends clazz {
            constructor() {
                super();
                this._name = Server.prototype.__proto__.constructor.name;
                self.addEventListener('message', this._receive.bind(this));
            }

            close() {
                self.removeEventListener('message', this._receive.bind(this));
            }

            _replyTo(message, status, result) {
                message.source.postMessage({ status, result, interfaceName: this._name, id: message.data.id }, message.origin);
            }

            _receive(message) {
                try {
                    if (message.data.interfaceName !== this._name) return;
                    if (!this._rpcInterface.includes(message.data.command)) throw new Error('Unknown command');

                    let args = message.data.args || [];

                    if (useAccessControl && message.data.command !== 'getRpcInterface') {
                        // Inject calling origin to function args
                        args = [{ callingWindow: message.source, callingOrigin: message.origin }, ...args];
                    }

                    /* deactivate this since there is no security issue and by wrapping in acl length info gets lost
                    // Test if request calls an existing method with the right number of arguments
                    const calledMethod = this[message.data.command];
                    if (!calledMethod) {
                        throw `Non-existing method ${message.data.command} called: ${message}`;
                    }

                    if (calledMethod.length < args.length) {
                        throw `Too many arguments passed: ${message}`;
                    }*/

                    const result = this._invoke(message.data.command, args);

                    if (result instanceof Promise) {
                        result
                            .then((finalResult) => this._replyTo(message, 'OK', finalResult))
                            .catch(e => this._replyTo(message, 'error',
                                e.message ? { message: e.message, stack: e.stack, code: e.code } : { message: e } ));
                    } else {
                        this._replyTo(message, 'OK', result);
                    }
                } catch (e) {
                    this._replyTo(message, 'error',
                        e.message ? { message: e.message, stack: e.stack, code: e.code } : { message: e } );
                }
            }

            _invoke(command, args) {
                return this[command].apply(this, args);
            }
        };

        if (rpcInterface !== undefined) {
            Server.prototype._rpcInterface = rpcInterface;
        } else {
            console.warn('No function whitelist as third parameter to Server() found, public functions are automatically determined!');

            // Collect function names of the Server's interface
            Server.prototype._rpcInterface = [];
            for (const functionName of Reflection.userFunctions(clazz.prototype)) {
                Server.prototype._rpcInterface.push(functionName);
            }
        }

        Server.prototype._rpcInterface.push('getRpcInterface');

        // Add function to retrieve the interface
        Server.prototype['getRpcInterface'] = function() {
            if(this.onConnected) this.onConnected.call(this);
            return Server.prototype._rpcInterface;
        };

        return Server;
    }
}

// TODO: Handle unload/load events (how?)

class BasePolicy {
   constructor() {
      this.name = this.constructor.name;
   }

    equals(otherPolicy) {
        return otherPolicy && this.name === otherPolicy.name;
    }

    serialize() {
        const serialized = {};

        for (const prop in this)
            if (!(this[prop] instanceof Function)) serialized[prop] = this[prop];

        return serialized;
    }

    allows(method, args) {
        throw 'Make your own policy by extending Policy and overwrite me'
    }

    needsUi(method, args) {
        throw 'Make your own policy by extending Policy and overwrite me'
    }
}

const KeyType =  {
    HIGH: 'high',
    LOW: 'low'
};

class WalletPolicy extends BasePolicy {
    constructor(limit) {
        super('wallet');
        this.limit = limit;
    }

    equals(otherPolicy) {
        return super.equals(otherPolicy) && this.limit === otherPolicy.limit;
    }

    allows(method, args, state) {
        switch (method) {
            case 'triggerImport':
            case 'persist':
            case 'list':
            case 'createWallet':
                return true;
            case 'sign':
                const { accountNumber, recipient, value, fee } = args;
                const key = state.keys.get(accountNumber);
                if (key && key.type === KeyType.LOW) return true;
                return false;
            default:
                throw new Error(`Unhandled method: ${method}`);
        }
    }

    needsUi(method, args, state) {
        switch (method) {
            case 'triggerImport':
            case 'persist':
            case 'list':
            case 'createVolatile':
                return false;
            case 'sign':
            case 'createWallet':
                return false;
            default:
                throw new Error(`Unhandled method: ${method}`);
        }
    }
}

// todo update

class MinerPolicy extends BasePolicy {
    allows(method, args, state) {
        switch (method) {
            case 'list':
            case 'getMinerAccount':
            case 'createWallet':
                return true;
            default:
                throw new Error(`Unhandled method: ${method}`);
        }
    }

    needsUi(method, args, state) {
        switch (method) {
            case 'list':
            case 'getMinerAccount':
                return false;
            case 'createWallet':
                return true;
            default:
                throw new Error(`Unhandled method: ${method}`);
        }
    }
}

class SafePolicy extends BasePolicy {
    allows(method, args, state) {
        switch (method) {
            case 'importFromFile':
            case 'importFromWords':
            case 'backupFile':
            case 'backupWords':
            case 'list':
            case 'createVolatile':
            case 'createSafe':
            case 'upgrade':
                // todo remove
            case 'createWallet':
                // todo remove
            case 'getMinerAccount':
            case 'rename':
                return true;
            case 'signSafe':
            case 'signWallet':
                // for now, assume there are only keys we are able to use in safe app
                return true;
                /*const [ userFriendlyAddress, recipient, value, fee ] = args;
                const key = (state.keys || state.accounts.entries).get(userFriendlyAddress);
                if (key.type === Keytype.HIGH) return true; */
            default:
                throw new Error(`Unhandled method: ${method}`);
        }
    }

    needsUi(method, args, state) {
        switch (method) {
            case 'list':
            case 'createVolatile':
            // todo remove
            case 'getMinerAccount':
                return false;
            case 'createSafe':
            // todo remove
            case 'createWallet':
            case 'upgrade':
            case 'importFromFile':
            case 'importFromWords':
            case 'backupFile':
            case 'backupWords':
            case 'signSafe':
            case 'signWallet':
            case 'rename':
                return true;
            default:
                throw new Error(`Unhandled method: ${method}`);
        }
    }
}

class ShopPolicy extends BasePolicy {
    allows(method, args, state) {
        switch (method) {
            case 'list':
            case 'signSafe':
            case 'signWallet':
                return true;
            default:
                return false;
        }
    }

    needsUi(method, args, state) {
        switch (method) {
            case 'list':
                return false;
            case 'signSafe':
            case 'signWallet':
                return true;
            default:
                return false;
        }
    }
}

// import GiveawayPolicy from './giveaway-policy.js';
class Policy {

    static parse(serialized) {
        if (!serialized) return null;
        const policy = Policy.get(serialized.name);
        for (const prop in serialized) policy[prop] = serialized[prop];
        return policy;
    }

    static get(name, ...args) {
        if (!Policy.predefined.hasOwnProperty(name)) throw `Policy "${name} does not exist."`
        return new Policy.predefined[name](...args);
    }
}

Policy.predefined = {};
for (const policy of [WalletPolicy, SafePolicy, MinerPolicy, /*GiveawayPolicy,*/ ShopPolicy]) {
    Policy.predefined[policy.name] = policy;
}

class NoUIError extends Error {

    static get code() {
        return 'K2';
    }

    constructor(method) {
        super(`Method ${method} needs user interface`);
    }
}

// import MixinRedux from '/secure-elements/mixin-redux/mixin-redux.js';
// import { setKeyguardConnection } from './connection-redux.js';

class KeyguardClient {
	static async create(src, assumedPolicy, getState, needUiCallback, usePopup = true) {
		const client = new KeyguardClient(src, getState, needUiCallback, usePopup);
		this._wrappedApi = await client._wrapApi();
		await client._authorize.bind(client)(assumedPolicy);
		// if (MixinRedux.store && MixinRedux.store.dispatch) MixinRedux.store.dispatch(setKeyguardConnection(true));
		return this._wrappedApi;
	}

	/**
	 * @private
	 *
	 * @param {string} src URI of secure origin aka key guard aka key dude.
	 * @param {() => StateObject} getState function which returns the state
	 * @param {function} needUiCallback
	 * @param {boolean} usePopup
	 */
	constructor(src, getState, needUiCallback, usePopup = true) {
		this._keyguardSrc = src;
		this._keyguardOrigin = new URL(src).origin;
		this.popup = usePopup;
		this.$iframe = this._createIframe();
		this.needUiCallback = needUiCallback;
		this.publicApi = {};
		this.policy = null;
		this.getState = getState;
	}

	async _wrapApi() {
 		this.embeddedApi = await this._getApi((await this.$iframe).contentWindow);

		for (const methodName of this.embeddedApi.availableMethods) {
			const normalMethod = this._proxyMethod(methodName);
			const secureMethod = this._proxySecureMethod(methodName);
			this.publicApi[methodName] = this._bindMethods(methodName, normalMethod, secureMethod);
		}

		// intercepting "authorize" and "getPolicy" for keeping an instance of the latest authorized policy
		// to predict if user interaction will be needed when calling API methods.
		const apiAuthorize = this.publicApi.authorize.secure;
		this.publicApi.authorize = async requiredPolicy => {
			const success = await apiAuthorize(requiredPolicy);
			this.policy = success ? requiredPolicy : null;
			return success;
		};

		const apiGetPolicy = this.publicApi.getPolicy;
		this.publicApi.getPolicy = async () => {
			return this.policy = Policy.parse(await apiGetPolicy());
		};

		return this.publicApi;
	}

	/** @param {string} methodName
	 *
	 * @returns {function} Trying to call this method in the iframe and open a window if user interaction is required.
	 * */
	_proxyMethod(methodName) {
		const proxy = async (...args) => {
			if (this.policy && !this.policy.allows(methodName, args, this.getState()))
				throw new Error(`Not allowed to call ${methodName}.`)

			try {
				// if we know that user interaction is needed, we'll do a secure request right away, i.e. a redirect/popup
				if (this.policy && this.policy.needsUi(methodName, args, this.getState()))
					return await proxy.secure(...args);

				return await this.embeddedApi[methodName](...args);
			}
			catch (error) {
				if (error.code === NoUIError.code) {
					if (this.needUiCallback instanceof Function) {
						return await new Promise((resolve, reject) => {
							this.needUiCallback(methodName, confirmed => {
								if (!confirmed) reject(new Error('Denied by user.'));
								resolve(proxy.secure.call(args));
							});
						});
					} else throw new Error(`User interaction is required to call "${ methodName }". You need to call this method from an event handler, e.g. a click event.`);
				}
				else throw error;
			}
		};

		return proxy;
	}

	/** @param {string} methodName
	 *
	 * @returns {function} Call this method in a new window
	 * */
	_proxySecureMethod(methodName) {
		return async (...args) => {
			if (this.popup) {
				const apiWindow = window.open(this._keyguardSrc, 'NimiqKeyguard', `left=${window.innerWidth / 2 - 250},top=100,width=500,height=820,location=yes,dependent=yes`);

				if (!apiWindow) {
					throw new Error('Keyguard window could not be opened.');
				}

				const secureApi = await this._getApi(apiWindow);
				const result = await secureApi[methodName](...args);

				apiWindow.close();

				return result;
			} else {
				// top level navigation
				throw new Error('Top level navigation not implemented. Use a popup.');
			}
		}
	}

	_bindMethods(methodName, normalMethod, secureMethod) {
		const method = normalMethod;
		method.secure = secureMethod;
		method.isAllowed = () => (this.policy && this.policy.allows(methodName, arguments, this.getState()));
		return method;
	}

	async _authorize(assumedPolicy) {
		let grantedPolicy = await this.publicApi.getPolicy();
		grantedPolicy = grantedPolicy && Policy.parse(grantedPolicy);
		console.log('Got policy:', grantedPolicy);

		if (!assumedPolicy.equals(grantedPolicy)) {
			const authorized = await this.publicApi.authorize(assumedPolicy);
			if (!authorized) {
				throw new Error('Authorization failed.');
			}
		}


	}

	// _defaultUi(methodName) {
	// 	return new Promise((resolve, reject) => { resolve(window.confirm("You will be forwarded to securely confirm this action.")); });
	// }

	async _getApi(targetWindow) {
		return await RPC.Client(targetWindow, 'KeyguardApi', this._keyguardOrigin);
	}

	/**
	 * @return {Promise}
	 */
	_createIframe() {
		const $iframe = document.createElement('iframe');

		const readyListener = (resolve) => function readyResolver({source, data}) {
			if (source === $iframe.contentWindow && data === 'ready') {
				self.removeEventListener('message', readyResolver);
				resolve($iframe);
			}
		};

		const promise = new Promise(resolve => self.addEventListener('message', readyListener(resolve)));

		$iframe.src = this._keyguardSrc + '/iframe.html';
		$iframe.name = 'keyguard';
		document.body.appendChild($iframe);
		return promise;
	}
}

KeyguardClient.Policies = Policy.predefined;

return KeyguardClient;

}());

//# sourceMappingURL=keyguard-client.js.map
