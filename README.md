# Yii2 Native

Native mobile application runtime and UI bridge for Yii2, inspired by the
architecture of [NativePHP Mobile](https://nativephp.com/docs/mobile).

Your Yii2 app describes its UI as a native element tree and reaches device
capabilities (camera, scanner, secure storage, notifications, ...) through a
validated named-function bridge. Everything is tested without a device.

## Status

**Early development.** This package is the **PHP half** of the system and is
feature-complete against the protocol in [`native/protocol.json`](native/protocol.json):

| Area | What you get |
|---|---|
| Component | `Yii::$app->native` with app identity, permissions, plugins, HTTP policy |
| Device APIs | 16 typed APIs (49 bridge functions): camera, scanner, biometrics, geolocation, microphone, notifications, secure storage, device, haptics, dialogs, share, browser, clipboard, filesystem, network, system |
| Bridge | transports, PHP-side handlers, argument validation from the protocol, permission guard, call events |
| Events | lifecycle, UI actions and completion of interactive calls, correlated by request id |
| UI | 19 widgets, deterministic element ids, `make()` composition, `asNative()` responses |
| HTTP + JS | CSRF-protected bridge endpoints and an ES-module client with `NativeError` |
| Plugins | manifest validation, directory and Composer discovery, boot lifecycle |
| Tooling | `native/install`, `doctor`, `config`, `manifest`, `build`, `run`, `plugin/*` |
| Testing | `Native::fake()` and `FakeTransport` |

**Not included yet:** the Swift / Kotlin shells and the embedded PHP runtime
(see [`native/README.md`](native/README.md)). `native/build` prepares a manifest
for them and can run build commands you configure; it does not compile an app.

## Requirements

- PHP 8.2+, Yii2 2.0.55+
- For real apps (not for this package): Xcode and/or Android Studio

## Quick start

```bash
composer require greenmeteor/yii2-native
php yii native/install      # create native/ and a starter config
php yii native/doctor       # check the setup
```

```php
// config: 'components' => ['native' => require __DIR__ . '/../native/config/native.php']

// device APIs
if (Yii::$app->native->isAvailable()) {
    Yii::$app->native->haptics->notification('success');
    $id = Yii::$app->native->camera->getPhoto('avatar');        // result arrives as an event
}

Yii::$app->native->on(NativeEvents::CAMERA_PHOTO_TAKEN, function (NativeEvent $e) {
    if ($e->id === 'avatar') { /* $e->get('path') */ }
});

// UI
use greenmeteor\native\widgets\{Button, Text, VStack};

return $this->asNative(          // controller using the RendersNative trait
    VStack::make(['spacing' => 16], [
        Text::make(['text' => 'Hello from Yii2']),
        Button::make(['text' => 'Continue', 'action' => 'continue']),
    ])
);
```

```js
import { Native } from './native.js';
await Native.device.vibrate({ duration: 100 });
Native.on('nativeCameraPhotoTaken', (e) => preview.src = e.path);
```

## Documentation

Full documentation, with diagrams, is in **[`docs/`](docs/README.md)**:

[Getting started](docs/getting-started.md) ·
[Architecture](docs/architecture.md) ·
[Configuration](docs/configuration.md) ·
[Bridge](docs/bridge.md) ·
[Device APIs](docs/device-apis.md) ·
[Events](docs/events.md) ·
[UI widgets](docs/ui-widgets.md) ·
[JavaScript](docs/javascript.md) ·
[Plugins](docs/plugins.md) ·
[Permissions & manifest](docs/permissions-and-manifest.md) ·
[Console commands](docs/console-commands.md) ·
[Security](docs/security.md) ·
[Testing](docs/testing.md) ·
[Protocol reference](docs/reference/protocol.md) ·
[Upgrading](docs/upgrading.md)

## Architecture

```text
                  Yii2 Application
                         |
                  greenmeteor/native
                         |
     +-----------+-------+-------+-----------+
     |           |               |           |
  Runtime     Renderer         Bridge     Plugins
     |           |               |           |
     |      Element tree    Device APIs      |
     +-----------+-------+-------+-----------+
                         |
                  Native Application
                   /             \
                iOS             Android
              SwiftUI          Compose
```

Native UI rendering is kept separate from device / API calls: the renderer
produces compact frames; the bridge is a named-function channel that plugins
extend.

## Development

```bash
composer install
composer test:phpunit   # or: composer test (Codeception) - same tests
composer lint
php bin/generate-protocol-docs.php   # after editing native/protocol.json
```

CI (`.github/workflows/ci.yml`) validates Composer, lints PHP, runs both test
runners on PHP 8.2 to 8.5, and syntax-checks the JavaScript client.

## License

BSD 3-Clause License.
