# JavaScript client

`resources/js/native.js` is an ES module that talks to the [HTTP
bridge](security.md) and receives native events in the web view. It also sets
`window.Native`.

## Loading it

In a Yii view or layout:

```php
\greenmeteor\native\assets\NativeAsset::register($this);
```

This publishes the script as `<script type="module">` and injects
`window.yii2NativeConfig` with the correct endpoints (using your pretty-URL
routes, or `native-bridge/call` / `native-bridge/event` without pretty URLs).

With a bundler, import it instead:

```js
import { Native } from './native.js';
```

> Module scripts are deferred. Run code that uses `Native` from another module
> or after `DOMContentLoaded`, not from an inline `<script>` in `<head>`.

TypeScript declarations are in `resources/js/native.d.ts`.

## Calling native functions

`Native.<api>.<method>(params)` maps to the bridge function `Api.method`:

```js
await Native.device.vibrate({ duration: 100 });            // Device.vibrate
const token = await Native.secureStorage.get({ key: 'token' });   // SecureStorage.get
await Native.call('Reports.summary', { year: 2026 });      // any function, by name
```

Parameter names are those of the [protocol reference](reference/protocol.md),
so the same validation applies as in PHP.

Interactive calls return immediately; listen for the result:

```js
Native.on('nativeCameraPhotoTaken', (e) => {
  if (e.id === 'avatar') preview.src = e.path;
});
await Native.camera.getPhoto({ id: 'avatar' });
```

## Events

The shell delivers events to the page with:

```js
window.dispatchEvent(new CustomEvent('native:event', {
  detail: { name: 'nativeForeground', payload: {} },
}));
```

and your code subscribes:

```js
const stop = Native.on('nativeDeepLink', (payload) => open(payload.url));
Native.once('nativeReady', () => console.log('ready'));
Native.on('*', (payload, name) => console.debug(name, payload));   // everything
stop();                                                            // or Native.off(name, handler)
```

`Native.emit(event, payload, id?)` sends an event **to PHP** (for example a UI
action) and resolves with the rendered frame if a PHP handler replied with one:

```js
const frame = await Native.emit('nativeAction', { action: 'checkout' });
```

## Errors

Failures reject with `NativeError`:

```js
try {
  await Native.browser.open({ url: 'javascript:alert(1)' });
} catch (e) {
  e instanceof Native.NativeError;   // true
  e.type;      // 'invalid_argument'
  e.status;    // 400
  e.message;   // "Browser.open: parameter 'url' must be an absolute http(s) URL."
}
```

| `type` | HTTP | Meaning |
|---|---|---|
| `invalid_request` | 400 | malformed body |
| `invalid_argument` | 400 | failed validation |
| `forbidden` | 403 | function / event not allowed |
| `permission` | 403 | permission not declared |
| `unavailable` | 503 | native runtime not present |
| *(native type)* | 502 | the device reported an error |
| `network` | - | request failed or timed out |

## Configuration

```js
Native.configure({
  bridge: '/_native/bridge',
  events: '/_native/events',
  secret: null,        // sent as X-Native-Secret
  timeout: 30000,      // ms
  csrfToken: null,     // otherwise read from yii.getCsrfToken() or <meta name="csrf-token">
});
```

Every request is a `POST` and carries Yii's CSRF token. Output
`<?= Html::csrfMetaTags() ?>` in your layout (the default Yii layout already
does).
