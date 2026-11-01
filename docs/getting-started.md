# Getting started

## Requirements

- PHP 8.2+ and Yii2 2.0.55+
- To produce a real app: Xcode (iOS) and/or Android Studio. These are needed by
  the native shells, not by this package.

## Install

```bash
composer require greenmeteor/yii2-native
```

The package is discovered as a Yii2 bootstrap extension. It registers
`Yii::$app->native` and, in console applications, the `native/*` commands. In
web applications it additionally registers the [HTTP bridge](security.md) routes
and the `native` response format.

## Initialise the project

```bash
php yii native/install
```

This creates the native project layout next to your application:

```text
native/
├── android/            # Android shell goes here
├── ios/                # iOS shell goes here
├── plugins/            # your native plugins
├── config/native.php   # starter component configuration
└── build/              # generated manifests (git-ignored)
```

Merge the generated configuration into your application config:

```php
// config/web.php and config/console.php
'components' => [
    'native' => require __DIR__ . '/../native/config/native.php',
],
```

Edit at least `appId` (reverse-DNS, lowercase, e.g. `com.acme.shop`) and `name`.

## Check your setup

```bash
php yii native/doctor
```

```text
Yii2 Native environment
[ ok ] PHP                  8.3.6
[ ok ] Yii2                 2.0.55
[ ok ] Protocol             v1, 49 functions, 19 node types, 25 events
[ ok ] Configuration        valid
[warn] ios shell            no project found (shell not bundled in this package)
[warn] HTTP bridge          enabled without http.secret (relies on requireRuntime + CSRF only).
```

`doctor` exits with status `1` if any check fails, so it can gate CI.

## Your first native call

```php
use Yii;

if (Yii::$app->native->isAvailable()) {
    Yii::$app->native->device->vibrate(100);
}
```

Outside the native shell `isAvailable()` is `false`, and calling a device API
throws `NativeUnavailableException`. Either check first, or opt into silent
no-ops while developing:

```php
'native' => [
    'class' => \greenmeteor\native\Native::class,
    'bridgeClass' => [
        'class' => \greenmeteor\native\bridge\NativeBridge::class,
        'allowUnavailable' => YII_ENV_DEV,   // calls return null instead of throwing
    ],
],
```

## Your first screen

Return a UI frame from a controller action:

```php
use greenmeteor\native\web\RendersNative;
use greenmeteor\native\widgets\{Button, Text, VStack};

class HomeController extends \yii\web\Controller
{
    use RendersNative;

    public function actionIndex()
    {
        return $this->asNative(
            VStack::make(['spacing' => 16, 'padding' => 24], [
                Text::make(['text' => 'Hello from Yii2', 'size' => 24, 'weight' => 'bold']),
                Button::make(['text' => 'Take a photo', 'action' => 'photo']),
            ])
        );
    }
}
```

and handle the tap:

```php
use greenmeteor\native\events\{NativeEvent, NativeEvents};

Yii::$app->native->on(NativeEvents::ACTION, function (NativeEvent $e) {
    if ($e->get('action') === 'photo') {
        Yii::$app->native->camera->getPhoto('profile-photo');   // result arrives as an event
    }
});

Yii::$app->native->on(NativeEvents::CAMERA_PHOTO_TAKEN, function (NativeEvent $e) {
    if ($e->id === 'profile-photo') {
        // $e->get('path') is the captured file
    }
});
```

Where to register handlers is covered in [Events](events.md#where-to-register-handlers).

## Next steps

- [Architecture](architecture.md): how runtime, bridge, renderer and shell fit together
- [Device APIs](device-apis.md) and [UI widgets](ui-widgets.md)
- [Testing](testing.md): run all of the above without a device
