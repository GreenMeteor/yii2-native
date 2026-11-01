# Upgrading from the foundation release

The foundation release shipped the component, a minimal bridge, six widgets and a
single console controller. This release keeps those public entry points working
but changes several behaviours deliberately. Review the list below.

## Breaking or behaviour changes

| Area | Before | Now | Action |
|---|---|---|---|
| Console routes | one `NativeController`; plugin command was `native/plugin-list` although the README said `native/plugin/list` | module `native` with `native/install`, `native/doctor`, `native/build`, `native/run`, `native/plugin/list`, ... | Use the new routes. `native/plugin-list` is gone. Remove references to `greenmeteor\native\commands\NativeController`. |
| Console exit codes | used `Controller::EXIT_CODE_*`, which no longer exist in Yii 2.2 (the commands crashed after doing their work) | `yii\console\ExitCode` | none |
| Nesting widgets | README showed `VStack::widget([...])`, which throws `UnknownPropertyException` | `VStack::make(config, children)` | Replace `widget()` composition with `make()`. |
| Element ids | `Text:12` (object-id based, different each request) | path-based: `root`, `root.0`, `root.1.2` | Set explicit `id`s where position can change. |
| Widget properties | computed once in `init()` | computed when the element is built | Changes after construction are now honoured. |
| Argument validation | none | functions declared in `protocol.json` are validated | Invalid calls now throw `InvalidArgumentException`. Set `validateArguments => false` to opt out. |
| Permission guard | none | calls needing undeclared permissions throw `NativePermissionException` | Declare `permissions`, or set `enforcePermissions => false`. |
| `NativeBridge::dispatch()` | called `nativephp_call()` directly | goes through a transport (`FunctionTransport` by default) | If you overrode it, move the logic into a transport. |
| JS client | returned the raw response, no server route existed | unwraps `{ok, result}`, rejects with `NativeError`, sends CSRF token | Update `try/catch` code; use `NativeAsset`. |
| CI | workflow in `.github/workflow/` (never ran) | `.github/workflows/` | none |
| Tests | Codeception `Unit` base class; PHPUnit run was broken | plain PHPUnit `TestCase`; both runners work | none |

## Compatible

These keep working unchanged:

- `Yii::$app->native->call()`, `isAvailable()`, `runtime`, `bridge`, `renderer`, `pluginManager`
- `NativeBridge::call()` with `allowUnavailable`, and `beforeCall` / `afterCall` / `error` events
- `Runtime::start()`, `stop()`, `isAvailable()`, `EVENT_START`, `EVENT_STOP`
- `Widget::widget([...])` for a standalone widget (returns the JSON frame), `child()`, `children` config
- `Renderer::render()`, `encode()`, `PROTOCOL_VERSION`
- `PluginManager::register()`, `has()`, `get()`, `all()`, `discover()` and `Plugin(['id', 'manifest'])`
- `NativeEvents` constants, `NativeHelper::available()` / `call()`

## Protocol additions

`native/protocol.json` gained `bridge`, `events` and `commonProperties`, and thirteen new
node types. The element-tree `version` stays `1`: changes are additive, and shells
should ignore properties and node types they don't know. See [protocol.md](reference/protocol.md).
