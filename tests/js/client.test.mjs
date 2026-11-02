import assert from 'node:assert/strict';

const handlers = {};
globalThis.window = globalThis;
globalThis.addEventListener = (n, h) => { handlers[n] = h; };
globalThis.document = { querySelector: () => ({ getAttribute: () => 'tok123' }) };
const calls = [];
globalThis.fetch = async (url, opts) => {
  calls.push({ url, body: JSON.parse(opts.body), headers: opts.headers });
  return { ok: true, status: 200, json: async () => ({ ok: true, result: 'R' }) };
};
const { Native, NativeError, configure } = await import('../../resources/js/native.js');

assert.equal(await Native.secureStorage.get({ key: 'a' }), 'R');
assert.deepEqual(calls[0].body, { method: 'SecureStorage.get', params: { key: 'a' } });
assert.equal(calls[0].url, '/_native/bridge');
assert.equal(calls[0].headers['X-CSRF-Token'], 'tok123');

assert.equal(typeof Native.then, 'undefined', 'Native must not be thenable');
assert.equal(await Native, Native);

configure({ secret: 's3cret' });
await Native.emit('nativeAction', { action: 'go' }, 'id1');
assert.equal(calls[1].url, '/_native/events');
assert.equal(calls[1].headers['X-Native-Secret'], 's3cret');
assert.equal(calls[1].body.id, 'id1');

let got;
const stop = Native.on('nativeAction', (p) => { got = p; });
handlers['native:event']({ detail: { name: 'nativeAction', payload: { action: 'x' } } });
assert.deepEqual(got, { action: 'x' });
stop(); got = null;
handlers['native:event']({ detail: { name: 'nativeAction', payload: {} } });
assert.equal(got, null, 'unsubscribed');

globalThis.fetch = async () => ({ ok: false, status: 403, json: async () => ({ ok: false, error: { type: 'forbidden', message: 'nope' } }) });
await assert.rejects(Native.call('A.b'), (e) => e instanceof NativeError && e.type === 'forbidden' && e.status === 403 && e.message === 'nope');

globalThis.fetch = async () => { throw new TypeError('offline'); };
await assert.rejects(Native.call('A.b'), (e) => e instanceof NativeError && e.type === 'network');
console.log('native.js client: all assertions passed');
