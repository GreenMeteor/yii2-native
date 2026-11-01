# Configuration

All options are public properties of `greenmeteor\native\Native`, set in the
component definition:

```php
'components' => [
    'native' => [
        'class' => \greenmeteor\native\Native::class,
        'appId' => 'com.acme.shop',
        'name' => 'Acme Shop',
        'version' => '1.4.0',
        'versionCode' => 14,
        'orientation' => 'portrait',
        'deepLinkScheme' => 'acme',
        'deepLinkHosts' => ['shop.acme.test'],
        'permissions' => ['camera' => 'Scan product barcodes.', 'location'],
        'plugins' => ['@app/native/plugins'],
        'http' => ['secret' => getenv('NATIVE_SECRET')],
    ],
],
```

Validate the result any time with `php yii native/config` or
`Yii::$app->native->validate()`, which returns a list of problems.

## App identity

| Option | Default | Notes |
|---|---|---|
| `appId` | `''` | Required for builds. Reverse-DNS, lowercase: `com.example.app`. |
| `name` | app `name` | Display name. Falls back to `Yii::$app->name`. |
| `version` | `1.0.0` | `MAJOR.MINOR.PATCH`, optional pre-release suffix. |
| `versionCode` | `1` | Positive integer; must increase for each store release. |
| `orientation` | `portrait` | `portrait`, `landscape` or `auto`. |
| `deepLinkScheme` | `null` | Custom URL scheme, e.g. `acme` for `acme://...`. |
| `deepLinkHosts` | `[]` | Hosts for universal / app links. |

## Behaviour

| Option | Default | Notes |
|---|---|---|
| `permissions` | `[]` | See [Permissions & manifest](permissions-and-manifest.md). |
| `enforcePermissions` | `true` | Throw `NativePermissionException` when a call needs an undeclared permission. |
| `plugins` | `[]` | Manifest arrays, class names, or paths / aliases. See [Plugins](plugins.md). |
| `apis` | `[]` | Replace an API class: `['camera' => MyCamera::class]`. |
| `projectPath` | `@app/native` | Where `native/install` creates the project. |
| `build` | `[]` | Shell commands for `native/build --exec` and `native/run --exec`. |

## Replacing subsystems

Each subsystem class can be swapped and accepts a class name **or** a Yii config
array:

| Option | Default |
|---|---|
| `runtimeClass` | `greenmeteor\native\runtime\Runtime` |
| `bridgeClass` | `greenmeteor\native\bridge\NativeBridge` |
| `rendererClass` | `greenmeteor\native\renderer\Renderer` |
| `pluginManagerClass` | `greenmeteor\native\plugin\PluginManager` |
| `permissionManagerClass` | `greenmeteor\native\permission\PermissionManager` |

```php
'runtimeClass' => ['class' => Runtime::class, 'platform' => 'ios'],        // force a platform
'bridgeClass' => ['class' => NativeBridge::class, 'allowUnavailable' => true],
'rendererClass' => ['class' => Renderer::class, 'strict' => YII_DEBUG],    // validate node types
```

### Runtime options

| Option | Default | Notes |
|---|---|---|
| `autoDetect` | `true` | Treat the app as native when the call function exists or `NATIVE_RUNTIME=1`. |
| `callFunction` | `nativephp_call` | Global function the shell injects. |
| `runtimeEnvVar` | `NATIVE_RUNTIME` | `1` / `true` / `yes` marks the runtime as present. |
| `platformEnvVar` | `NATIVE_PLATFORM` | `ios` or `android`. |
| `platform` | `null` | Override detection. |
| `persistent` | `true` | Informational: PHP stays resident between requests in the shell. |

### Bridge options

| Option | Default | Notes |
|---|---|---|
| `allowUnavailable` | `false` | Return `null` instead of throwing when no runtime is attached. |
| `validateArguments` | `true` | Validate arguments against `protocol.json` (see [Bridge](bridge.md#argument-validation)). |
| `transport` | `FunctionTransport` | Object, class name or config array. See [Bridge](bridge.md#transports). |

### Renderer options

| Option | Default | Notes |
|---|---|---|
| `strict` | `false` | Throw on unknown node types. Recommended in development. |
| `assignIds` | `true` | Give elements without an id a path-based id (`root.0.1`). |

## HTTP bridge options (`http`)

| Key | Default | Notes |
|---|---|---|
| `enabled` | `true` | Disable to remove the endpoints entirely (they return 404). |
| `bridgeRoute` | `_native/bridge` | Pretty-URL route for `POST` bridge calls. |
| `eventsRoute` | `_native/events` | Pretty-URL route for `POST` events. |
| `secret` | `null` | Shared secret expected in the `X-Native-Secret` header. Use 16+ characters. |
| `requireRuntime` | `true` | Return 503 unless the native runtime is present. |
| `allow` | `[]` | `fnmatch` patterns of callable functions. Empty means all known functions. |
| `deny` | `[]` | `fnmatch` patterns that are always refused (wins over `allow`). |
| `allowUnknownEvents` | `false` | Accept events not declared in `protocol.json` or a plugin. |

See [Security](security.md) for how these fit together.

## Event handlers in config

Because `Native` is a Yii component, handlers can be declared in configuration
and are therefore registered on every request:

```php
'native' => [
    'class' => \greenmeteor\native\Native::class,
    'appId' => 'com.acme.shop',
    'on nativeDeepLink' => [\app\native\Handlers::class, 'onDeepLink'],
],
```
