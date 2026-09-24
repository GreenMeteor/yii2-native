# Yii2 Native

Native mobile application foundation for Yii2.

Yii2 Native is an independent Yii2 implementation inspired by the architecture of NativePHP Mobile.

## Status

**Early development / foundation release.**

This package currently provides the Yii2-side architecture:

- `Yii::$app->native`
- persistent-runtime abstraction
- native bridge abstraction
- native lifecycle events
- native element tree
- Yii2 native widgets
- plugin discovery/manifest model
- Yii console commands
- JavaScript bridge helper
- iOS and Android shell scaffolding
- unit tests

## Requirements

- PHP 8.2+
- Yii2 2.0.49+
- Xcode for iOS development
- Android Studio / Android SDK for Android development

## Installation

```bash
composer require greenmeteor/yii2-native
```

The package is Composer-discovered as a Yii2 bootstrap component.

Initialize a native project:

```bash
php yii native/install
```

Inspect the environment:

```bash
php yii native/doctor
```

List discovered plugins:

```bash
php yii native/plugin/list
```

## Application component

The package exposes:

```php
Yii::$app->native
```

Example:

```php
$result = Yii::$app->native->bridge->call(
    'Device.vibrate',
    ['duration' => 100]
);
```

Native availability can be checked with:

```php
if (Yii::$app->native->isAvailable()) {
    // Native runtime is available.
}
```

## Native widgets

The initial widget layer produces a framework-neutral element tree.

```php
use greenmeteor\native\widgets\Button;
use greenmeteor\native\widgets\Text;
use greenmeteor\native\widgets\VStack;

return VStack::widget([
    Text::widget([
        'text' => 'Hello from Yii2',
    ]),
    Button::widget([
        'text' => 'Continue',
        'action' => 'continue',
    ]),
]);
```

The resulting tree can be serialized for the native renderer:

```php
$tree = VStack::widget([
    Text::widget(['text' => 'Hello']),
]);

$frame = Yii::$app->native->renderer->render($tree);
```

## Native events

Yii2's existing event system is used.

```php
Yii::$app->native->on(
    \greenmeteor\native\events\NativeEvents::FOREGROUND,
    function () {
        // App returned to the foreground.
    }
);
```

## Plugin model

Native plugins are Composer packages containing Yii2 PHP code and platform implementations.

A plugin may provide:

- bridge functions
- lifecycle events
- native UI elements
- iOS Swift code
- Android Kotlin code
- permissions
- native dependencies
- build hooks

The package intentionally follows the same high-level separation used by modern NativePHP: native UI rendering is separate from device/API bridge calls.

## Architecture

```text
                  Yii2 Application
                         |
                  greenmeteor/native
                         |
          +--------------+--------------+
          |              |              |
       Runtime        Renderer        Bridge
          |              |              |
          |          Element Tree       |
          |              |              |
          +--------------+--------------+
                         |
                  Native Application
                   /             \
                iOS             Android
              SwiftUI          Compose
```

The long-term renderer is intended to use a compact frame protocol rather than JSON on every UI update. Device APIs remain a separate named-function bridge because they are less latency-sensitive and naturally extensible through plugins.

## License

BSD 3-Clause License.
