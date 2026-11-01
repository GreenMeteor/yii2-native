# Testing

You don't need a device or the native shell to test code that uses Yii2 Native.

## The fake transport

`Native::fake()` swaps the transport for an in-memory `FakeTransport` and marks
the runtime as available:

```php
$native = Yii::$app->native;
$fake = $native->fake([
    'Device.getId'      => 'device-123',
    'Network.getStatus' => ['connected' => true, 'type' => 'wifi'],
]);

$this->assertSame('device-123', $native->device->getId());
$this->assertTrue($native->network->isConnected());

$native->haptics->impact('light');
$fake->assertCalled('Haptics.impact', fn (array $args) => $args['style'] === 'light');
$fake->assertNotCalled('Dialog.toast');
```

Responses are values, callables or throwables:

```php
$fake->responses['SecureStorage.get'] = fn (array $args) => $args['key'] === 'token' ? 'abc' : null;
$fake->responses['Biometrics.prompt'] = new NativeCallException('User cancelled');
```

| `FakeTransport` member | Purpose |
|---|---|
| `calls` | every call: `['function' => ..., 'arguments' => [...]]` |
| `callsTo($fn)` | argument lists of calls to one function |
| `wasCalled($fn)`, `lastCall()` | quick checks |
| `assertCalled($fn, ?callable $matcher)` | throws `AssertionError` if never called / no call matches |
| `assertNotCalled($fn)` | the inverse |
| `reset()` | clear recorded calls |

The fake transport still goes through the real bridge, so **permission checks
and argument validation run**. Declare the permissions your code needs in the
test configuration, as in production.

## Simulating native events

```php
$event = $native->dispatch(NativeEvents::SCANNER_SCANNED, ['id' => 'qr1', 'data' => 'ABC']);
// your handlers ran; read $event->response if they set one
```

## Rendering assertions

```php
$frame = $native->renderer->render($screen);
$this->assertSame('button', $frame['root']['children'][1]['type']);
$this->assertSame('root.1', $frame['root']['children'][1]['properties']['id']);
```

Because ids are deterministic, whole-frame snapshot comparisons are stable.

## Testing HTTP bridge behaviour

See `tests/unit/WebBridgeTest.php` for a pattern: boot a `yii\web\Application` with
the package `Bootstrap`, a `Request` subclass whose `getRawBody()` returns your JSON,
and call `Yii::$app->runAction('native-bridge/call')`.

## Running this package's own tests

```bash
composer install
composer test:phpunit      # vendor/bin/phpunit
composer test              # Codeception (same tests)
composer lint              # php -l over src and tests
```

The same tests run under both runners. Guard tests keep the contract honest:

| Guard | Fails when |
|---|---|
| every API method has a function in `protocol.json` (and vice versa) | an API is added without updating the protocol |
| every widget node type is declared (and vice versa) | a widget is added without a protocol entry |
| every `NativeEvents` constant is declared | an event constant is not in the protocol |
| interactive functions name a completion event that exists | a typo in an event name |
| `docs/reference/protocol.md` matches the generator | the protocol changed without regenerating docs |

After editing `native/protocol.json`:

```bash
php bin/generate-protocol-docs.php
```
