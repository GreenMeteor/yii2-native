/**
 * Yii2 Native JavaScript client (ES module; also exposes `window.Native`).
 *
 *   import { Native } from './native.js';
 *   await Native.device.vibrate({ duration: 100 });   // -> Device.vibrate
 *   Native.on('nativeCameraPhotoTaken', e => console.log(e.path));
 *
 * Endpoints come from `window.yii2NativeConfig` (set by NativeAsset) or
 * `Native.configure()`. Requests carry Yii's CSRF token.
 */
const config = {
    bridge: '/_native/bridge',
    events: '/_native/events',
    secret: null,
    timeout: 30000,
    csrfToken: null,
    ...((typeof window !== 'undefined' && window.yii2NativeConfig) || {}),
};

export class NativeError extends Error {
    constructor(message, { type = 'error', status = 0 } = {}) {
        super(message);
        this.name = 'NativeError';
        this.type = type;
        this.status = status;
    }
}

export function configure(options = {}) {
    Object.assign(config, options);
}

function csrfToken() {
    if (config.csrfToken) return config.csrfToken;
    if (typeof window !== 'undefined' && window.yii && typeof window.yii.getCsrfToken === 'function') {
        const token = window.yii.getCsrfToken();
        if (token) return token;
    }
    if (typeof document !== 'undefined') {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
    }
    return null;
}

async function post(url, body) {
    const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };
    const token = csrfToken();
    if (token) headers['X-CSRF-Token'] = token;
    if (config.secret) headers['X-Native-Secret'] = config.secret;

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timer = controller && config.timeout ? setTimeout(() => controller.abort(), config.timeout) : null;

    let response;
    try {
        response = await fetch(url, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify(body),
            signal: controller ? controller.signal : undefined,
        });
    } catch (e) {
        throw new NativeError(e.name === 'AbortError' ? 'Native bridge request timed out' : `Native bridge request failed: ${e.message}`, { type: 'network' });
    } finally {
        if (timer) clearTimeout(timer);
    }

    let data = null;
    try {
        data = await response.json();
    } catch (e) {
        /* non-JSON body */
    }

    if (!response.ok || !data || data.ok === false) {
        const error = (data && data.error) || {};
        throw new NativeError(error.message || `Native bridge request failed: ${response.status}`, {
            type: error.type || 'http',
            status: response.status,
        });
    }
    return data.result;
}

/** Call a bridge function, e.g. call('Device.vibrate', { duration: 100 }). */
export function call(method, params = {}) {
    return post(config.bridge, { method, params });
}

/** Send an event to PHP; resolves with the rendered UI frame if a handler replied with one. */
export function emit(event, payload = {}, id = null) {
    return post(config.events, id === null ? { event, payload } : { event, payload, id });
}

const listeners = new Map();

export function on(event, handler) {
    if (!listeners.has(event)) listeners.set(event, new Set());
    listeners.get(event).add(handler);
    return () => off(event, handler);
}

export function off(event, handler) {
    const set = listeners.get(event);
    if (set) set.delete(handler);
}

export function once(event, handler) {
    const stop = on(event, (payload) => {
        stop();
        handler(payload);
    });
    return stop;
}

function dispatch(event, payload) {
    for (const name of [event, '*']) {
        for (const handler of listeners.get(name) || []) {
            try {
                handler(payload, event);
            } catch (e) {
                console.error('Native event handler failed', e);
            }
        }
    }
}

// The native shell delivers events with:
//   window.dispatchEvent(new CustomEvent('native:event', { detail: { name, payload } }))
if (typeof window !== 'undefined') {
    window.addEventListener('native:event', (e) => {
        if (e.detail && typeof e.detail.name === 'string') dispatch(e.detail.name, e.detail.payload || {});
    });
}

const core = { call, emit, on, off, once, configure, NativeError };

/**
 * `Native.<api>.<method>(params)` maps to the bridge function `Api.method`,
 * e.g. Native.secureStorage.get({ key: 'token' }) -> SecureStorage.get.
 */
export const Native = new Proxy(core, {
    get(target, prop) {
        if (prop in target) return target[prop];
        // Avoid being thenable (`await Native`) and breaking Symbol lookups.
        if (typeof prop !== 'string' || prop === 'then') return undefined;
        const ns = prop.charAt(0).toUpperCase() + prop.slice(1);
        return new Proxy({}, {
            get: (_, fn) => (typeof fn === 'string' && fn !== 'then' ? (params = {}) => call(`${ns}.${fn}`, params) : undefined),
        });
    },
});

if (typeof window !== 'undefined') window.Native = Native;

export default Native;
