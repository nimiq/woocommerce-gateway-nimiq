!function(e,t){"object"==typeof exports&&"undefined"!=typeof module?module.exports=t():"function"==typeof define&&define.amd?define(t):e.AccountsClient=t()}(this,function(){"use strict";class e{static generateRandomId(){const e=new Uint32Array(1);return crypto.getRandomValues(e),e[0]}}var t;!function(e){e.OK="ok",e.ERROR="error"}(t||(t={}));const s="<postMessage>";class r{static byteLength(e){const[t,s]=r._getLengths(e);return r._byteLength(t,s)}static decode(e){r._initRevLookup();const[t,s]=r._getLengths(e),n=new Uint8Array(r._byteLength(t,s));let i=0;const o=s>0?t-4:t;let a=0;for(;a<o;a+=4){const t=r._revLookup[e.charCodeAt(a)]<<18|r._revLookup[e.charCodeAt(a+1)]<<12|r._revLookup[e.charCodeAt(a+2)]<<6|r._revLookup[e.charCodeAt(a+3)];n[i++]=t>>16&255,n[i++]=t>>8&255,n[i++]=255&t}if(2===s){const t=r._revLookup[e.charCodeAt(a)]<<2|r._revLookup[e.charCodeAt(a+1)]>>4;n[i++]=255&t}if(1===s){const t=r._revLookup[e.charCodeAt(a)]<<10|r._revLookup[e.charCodeAt(a+1)]<<4|r._revLookup[e.charCodeAt(a+2)]>>2;n[i++]=t>>8&255,n[i]=255&t}return n}static encode(e){const t=e.length,s=t%3,n=[];for(let i=0,o=t-s;i<o;i+=16383)n.push(r._encodeChunk(e,i,i+16383>o?o:i+16383));if(1===s){const s=e[t-1];n.push(r._lookup[s>>2]+r._lookup[s<<4&63]+"==")}else if(2===s){const s=(e[t-2]<<8)+e[t-1];n.push(r._lookup[s>>10]+r._lookup[s>>4&63]+r._lookup[s<<2&63]+"=")}return n.join("")}static encodeUrl(e){return r.encode(e).replace(/\//g,"_").replace(/\+/g,"-").replace(/=/g,".")}static decodeUrl(e){return r.decode(e.replace(/_/g,"/").replace(/-/g,"+").replace(/\./g,"="))}static _initRevLookup(){if(0===r._revLookup.length){r._revLookup=[];for(let e=0,t=r._lookup.length;e<t;e++)r._revLookup[r._lookup.charCodeAt(e)]=e;r._revLookup["-".charCodeAt(0)]=62,r._revLookup["_".charCodeAt(0)]=63}}static _getLengths(e){const t=e.length;if(t%4>0)throw new Error("Invalid string. Length must be a multiple of 4");let s=e.indexOf("=");return-1===s&&(s=t),[s,s===t?0:4-s%4]}static _byteLength(e,t){return 3*(e+t)/4-t}static _tripletToBase64(e){return r._lookup[e>>18&63]+r._lookup[e>>12&63]+r._lookup[e>>6&63]+r._lookup[63&e]}static _encodeChunk(e,t,s){const n=[];for(let i=t;i<s;i+=3){const t=(e[i]<<16&16711680)+(e[i+1]<<8&65280)+(255&e[i+2]);n.push(r._tripletToBase64(t))}return n.join("")}}var n,i,o;r._lookup="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",r._revLookup=[],function(e){e[e.UINT8_ARRAY=0]="UINT8_ARRAY"}(n||(n={}));class a{static stringify(e){return JSON.stringify(e,a._jsonifyType)}static parse(e){return JSON.parse(e,a._parseType)}static _parseType(e,t){if(t&&t.hasOwnProperty&&t.hasOwnProperty(a.TYPE_SYMBOL)&&t.hasOwnProperty(a.VALUE_SYMBOL))switch(t[a.TYPE_SYMBOL]){case n.UINT8_ARRAY:return r.decode(t[a.VALUE_SYMBOL])}return t}static _jsonifyType(e,t){return t instanceof Uint8Array?a._typedObject(n.UINT8_ARRAY,r.encode(t)):t}static _typedObject(e,t){const s={};return s[a.TYPE_SYMBOL]=e,s[a.VALUE_SYMBOL]=t,s}}a.TYPE_SYMBOL="__",a.VALUE_SYMBOL="v";class c{constructor(e=!0){this._store=e?window.sessionStorage:null,this._validIds=new Map,e&&this._restoreIds()}static _decodeIds(e){const t=a.parse(e),s=new Map;for(const e of Object.keys(t)){const r=parseInt(e,10);s.set(isNaN(r)?e:r,t[e])}return s}has(e){return this._validIds.has(e)}getCommand(e){const t=this._validIds.get(e);return t?t[0]:null}getState(e){const t=this._validIds.get(e);return t?t[1]:null}add(e,t,s=null){this._validIds.set(e,[t,s]),this._storeIds()}remove(e){this._validIds.delete(e),this._storeIds()}clear(){this._validIds.clear(),this._store&&this._store.removeItem(c.KEY)}_encodeIds(){const e=Object.create(null);for(const[t,s]of this._validIds)e[t]=s;return a.stringify(e)}_restoreIds(){const e=this._store.getItem(c.KEY);e&&(this._validIds=c._decodeIds(e))}_storeIds(){this._store&&this._store.setItem(c.KEY,this._encodeIds())}}c.KEY="rpcRequests";class d{static receiveRedirectCommand(e){if(!document.referrer)return null;const t=new URLSearchParams(e.search),r=new URL(document.referrer);if(!t.has("command"))return null;if(!t.has("id"))return null;if(!t.has("returnURL"))return null;const n=t.get("returnURL")===s&&window.opener;if(!n){if(new URL(t.get("returnURL")).origin!==r.origin)return null}let i=[];if(t.has("args"))try{i=a.parse(t.get("args"))}catch(e){}return i=Array.isArray(i)?i:[],{origin:r.origin,data:{id:parseInt(t.get("id"),10),command:t.get("command"),args:i},returnURL:t.get("returnURL"),source:n?window.opener:null}}static receiveRedirectResponse(e){if(!document.referrer)return null;const s=new URLSearchParams(e.search),r=new URL(document.referrer);if(!s.has("status"))return null;if(!s.has("id"))return null;if(!s.has("result"))return null;const n=a.parse(s.get("result")),i=s.get("status")===t.OK?t.OK:t.ERROR;return{origin:r.origin,data:{id:parseInt(s.get("id"),10),status:i,result:n}}}static prepareRedirectReply(e,t,s){const r=new URLSearchParams;return r.set("status",t),r.set("result",a.stringify(s)),r.set("id",e.id.toString()),`${e.returnURL}${new URL(e.returnURL).search.length>0?"&":"?"}${r.toString()}`}static prepareRedirectInvocation(e,t,s,r,n){const i=new URLSearchParams;return i.set("id",t.toString()),i.set("returnURL",s),i.set("command",r),Array.isArray(n)&&i.set("args",a.stringify(n)),`${e}?${i.toString()}`}}class l{constructor(e,t=!1){this._allowedOrigin=e,this._waitingRequests=new c(t),this._responseHandlers=new Map}onResponse(e,t,s){this._responseHandlers.set(e,{resolve:t,reject:s})}_receive(e){if(!e.data||!e.data.status||!e.data.id||"*"!==this._allowedOrigin&&e.origin!==this._allowedOrigin)return;const s=e.data,r=this._getCallback(s.id),n=this._waitingRequests.getState(s.id);if(r){if(console.debug("RpcClient RECEIVE",s),s.status===t.OK)r.resolve(s.result,s.id,n);else if(s.status===t.ERROR){const e=new Error(s.result.message);s.result.stack&&(e.stack=s.result.stack),s.result.name&&(e.name=s.result.name),r.reject(e,s.id,n)}}else console.warn("Unknown RPC response:",s)}_getCallback(e){if(this._responseHandlers.has(e))return this._responseHandlers.get(e);{const t=this._waitingRequests.getCommand(e);if(t)return this._responseHandlers.get(t)}}}class u extends l{constructor(e,t){super(t),this._target=e,this._connected=!1,this._receiveListener=this._receive.bind(this)}async init(){await this._connect(),window.addEventListener("message",this._receiveListener)}async call(t,...s){if(!this._connected)throw new Error("Client is not connected, call init first");return new Promise((r,n)=>{const i={command:t,args:s,id:e.generateRandomId()};this._responseHandlers.set(i.id,{resolve:r,reject:n}),this._waitingRequests.add(i.id,t);const o=()=>{this._target.closed&&n(new Error("Window was closed")),setTimeout(o,500)};setTimeout(o,500),console.debug("RpcClient REQUEST",t,s),this._target.postMessage(i,this._allowedOrigin)})}close(){window.removeEventListener("message",this._receiveListener),this._connected=!1}_connect(){return new Promise((e,s)=>{const r=s=>{const{source:n,origin:i,data:o}=s;if(n===this._target&&o.status===t.OK&&"pong"===o.result&&1===o.id&&("*"===this._allowedOrigin||i===this._allowedOrigin)){if(o.result.stack){const e=new Error(o.result.message);e.stack=o.result.stack,o.result.name&&(e.name=o.result.name),console.error(e)}window.removeEventListener("message",r),this._connected=!0,console.log("RpcClient: Connection established"),window.addEventListener("message",this._receiveListener),e(!0)}};window.addEventListener("message",r);let n=0;const i=setTimeout(()=>{window.removeEventListener("message",r),clearTimeout(n),s(new Error("Connection timeout"))},1e4),o=()=>{if(this._connected)clearTimeout(i);else{try{this._target.postMessage({command:"ping",id:1},this._allowedOrigin)}catch(e){console.error(`postMessage failed: ${e}`)}n=setTimeout(o,1e3)}};n=setTimeout(o,100)})}}class h extends u{constructor(e,t,s){super(e,t),this._requestId=s}async init(){window.addEventListener("message",this._receiveListener)}async listenFor(e){return new Promise((t,s)=>{this._responseHandlers.set(this._requestId,{resolve:t,reject:s}),this._waitingRequests.add(this._requestId,e);const r=()=>{this._target.closed&&s(new Error("Window was closed")),setTimeout(r,500)};setTimeout(r,500)})}}class _ extends l{constructor(e,t){super(t,!0),this._target=e}async init(){const e=d.receiveRedirectResponse(window.location);e?this._receive(e):d.receiveRedirectCommand(window.location)||this._rejectOnBack()}close(){}call(e,t,...s){this.callAndSaveLocalState(e,null,t,...s)}callAndSaveLocalState(t,s,r,...n){const i=e.generateRandomId(),o=d.prepareRedirectInvocation(this._target,i,t,r,n);this._waitingRequests.add(i,r,s),history.replaceState({rpcRequestId:i},document.title),console.debug("RpcClient REQUEST",r,n),window.location.href=o}_rejectOnBack(){if(history.state&&history.state.rpcRequestId){const e=history.state.rpcRequestId,t=this._getCallback(e),s=this._waitingRequests.getState(e);if(t){console.debug("RpcClient BACK");const r=new Error("Request aborted");t.reject(r,e,s)}}}}class p{static getAllowedOrigin(e){return new URL(e).origin}constructor(e){this._type=e}async request(e,t,s){throw new Error("Not implemented")}get type(){return this._type}}!function(e){e[e.REDIRECT=0]="REDIRECT",e[e.POPUP=1]="POPUP",e[e.IFRAME=2]="IFRAME"}(i||(i={}));class g extends p{static withLocalState(e){return new g(void 0,e)}constructor(e,t){super(i.REDIRECT);const s=window.location;if(this._returnUrl=e||`${s.origin}${s.pathname}`,this._localState=t||{},void 0!==this._localState.__command)throw new Error("Invalid localState: Property '__command' is reserved")}async request(e,t,s){const r=p.getAllowedOrigin(e),n=new _(e,r);await n.init();const i=Object.assign({},this._localState,{__command:t});console.log("state",i),n.callAndSaveLocalState(this._returnUrl,JSON.stringify(i),t,...s)}}class w extends p{constructor(e=w.DEFAULT_OPTIONS){super(i.POPUP),this._options=e}async request(t,s,r){const n=p.getAllowedOrigin(t),i=e.generateRandomId(),o=d.prepareRedirectInvocation(t,i,"<postMessage>",s,r),a=this.createPopup(o),c=new h(a,n,i);await c.init();try{const e=await c.listenFor(s);return c.close(),a.close(),e}catch(e){throw c.close(),a.close(),e}}createPopup(e){const t=window.open(e,"NimiqAccounts",this._options);if(!t)throw new Error("Failed to open popup");return t}}w.DEFAULT_OPTIONS="";class m extends p{constructor(){super(i.IFRAME),this._iframe=null,this._client=null}async request(e,t,s){if(this._iframe&&this._iframe.src!==`${e}${m.IFRAME_PATH_SUFFIX}`)throw new Error("Accounts Manager iframe is already opened with another endpoint");const r=p.getAllowedOrigin(e);if(this._iframe||(this._iframe=await this.createIFrame(e)),!this._iframe.contentWindow)throw new Error(`IFrame contentWindow is ${typeof this._iframe.contentWindow}`);return this._client||(this._client=new u(this._iframe.contentWindow,r),await this._client.init()),await this._client.call(t,...s)}async createIFrame(e){return new Promise((t,s)=>{const r=document.createElement("iframe");r.name="NimiqAccountsIFrame",r.style.display="none",document.body.appendChild(r),r.src=`${e}${m.IFRAME_PATH_SUFFIX}`,r.onload=(()=>t(r)),r.onerror=s})}}m.IFRAME_PATH_SUFFIX="/iframe.html",function(e){e.LIST="list",e.MIGRATE="migrate",e.CHECKOUT="checkout",e.SIGN_MESSAGE="sign-message",e.SIGN_TRANSACTION="sign-transaction",e.ONBOARD="onboard",e.SIGNUP="signup",e.LOGIN="login",e.EXPORT="export",e.CHANGE_PASSPHRASE="change-passphrase",e.LOGOUT="logout",e.ADD_ACCOUNT="add-account",e.RENAME="rename",e.CHOOSE_ADDRESS="choose-address"}(o||(o={}));class R{constructor(e=R.DEFAULT_ENDPOINT,t){this._endpoint=e,this._defaultBehavior=t||new w(`left=${window.innerWidth/2-500},top=50,width=1000,height=900,location=yes,dependent=yes`),this._iframeBehavior=new m,this._redirectClient=new _("",p.getAllowedOrigin(this._endpoint))}checkRedirectResponse(){return this._redirectClient.init()}on(e,t,s){this._redirectClient.onResponse(e,(e,s,r)=>t(e,JSON.parse(r)),(e,t,r)=>s&&s(e,JSON.parse(r)))}onboard(e,t=this._defaultBehavior){return this._request(t,o.ONBOARD,[e])}signup(e,t=this._defaultBehavior){return this._request(t,o.SIGNUP,[e])}login(e,t=this._defaultBehavior){return this._request(t,o.LOGIN,[e])}chooseAddress(e,t=this._defaultBehavior){return this._request(t,o.CHOOSE_ADDRESS,[e])}signTransaction(e,t=this._defaultBehavior){return this._request(t,o.SIGN_TRANSACTION,[e])}checkout(e,t=this._defaultBehavior){return this._request(t,o.CHECKOUT,[e])}logout(e,t=this._defaultBehavior){return this._request(t,o.LOGOUT,[e])}export(e,t=this._defaultBehavior){return this._request(t,o.EXPORT,[e])}changePassphrase(e,t=this._defaultBehavior){return this._request(t,o.CHANGE_PASSPHRASE,[e])}addAccount(e,t=this._defaultBehavior){return this._request(t,o.ADD_ACCOUNT,[e])}rename(e,t=this._defaultBehavior){return this._request(t,o.RENAME,[e])}signMessage(e,t=this._defaultBehavior){return this._request(t,o.SIGN_MESSAGE,[e])}migrate(e=this._defaultBehavior){return this._request(e,o.MIGRATE,[{}])}list(e=this._iframeBehavior){return this._request(e,o.LIST,[])}_request(e,t,s){return e.request(this._endpoint,t,s)}}return R.RequestType=o,R.RedirectRequestBehavior=g,R.DEFAULT_ENDPOINT="https://safe-next.nimiq.com"===window.location.origin?"https://accounts.nimiq.com":"https://safe-next.nimiq-testnet.com"===window.location.origin?"https://accounts.nimiq-testnet.com":"http://localhost:8080",R});