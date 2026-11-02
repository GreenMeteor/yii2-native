# Changelog

## 0.3.0

### Added
- **Browser preview** (`preview.enabled`, off by default): `/_native/preview` renders frames as DOM, delivers
  `nativeAction` events to your handlers against a fake transport, and lists the bridge calls they make.
  `resources/preview/preview.js` is reusable and XSS-tested.
- **Deep links**: `deepLinks` rules and `DeepLinkResolver` (scheme/host allow-listing, traversal and control-character
  rejection); `NativeEvent::$route` is filled for `nativeDeepLink` events; `Native::resolveDeepLink()`.
- **Strict property validation**: `Renderer::validate()` / strict mode now reports unknown properties for built-in
  node types (plugin nodes without a `properties` list accept anything).
- `number` parameter type in argument validation.
- `native/doctor` checks the preview setting.
- JavaScript tests (`npm test`): client and preview renderer, run in CI.
- Docs: `preview.md`, `deep-links.md`.

## 0.2.0

### Added
- **Device APIs**: `camera`, `scanner`, `biometrics`, `geolocation`, `microphone`, `notifications`,
  `secureStorage`, `device`, `haptics`, `dialog`, `share`, `browser`, `clipboard`, `filesystem`,
  `network`, `system` (49 bridge functions). Interactive calls return a request id correlated with
  a completion event.
- **Bridge**: pluggable transports (`FunctionTransport`, `FakeTransport`), PHP-side handlers
  (`register()`), `beforeCall` short-circuit, call duration, `NativeCallException` for native errors,
  protocol-driven argument validation, permission guard.
- **Events**: `Native::dispatch()`, `NativeEvent` (type, payload, id, response), catch-all `native`
  event, 25 documented events.
- **HTTP bridge**: `native-bridge/call` and `native-bridge/event` (`/_native/bridge`, `/_native/events`)
  with CSRF, optional shared secret, runtime requirement, allow/deny patterns, body size cap.
- **JS client**: `NativeError`, CSRF token, timeouts, `on/off/once/emit`, `Native.<api>.<method>()`
  proxy, TypeScript declarations, `NativeAsset`.
- **Widgets**: `ZStack`, `Spacer`, `Divider`, `Icon`, `TextInput`, `Toggle`, `Card`, `LazyList`,
  `TopBar`, `BottomNav`, `BottomNavItem`, `Fab`, `Loading`; common style properties;
  `Widget::make()` / `add()`; `RendersNative` trait and `native` response format.
- **Permissions**: `PermissionManager` and generated Android / iOS manifest fragments.
- **Plugins**: manifest validation, JSON manifests, Composer discovery, boot lifecycle registering
  functions, elements, events, permissions and handlers.
- **Tooling**: `native/config`, `native/manifest`, `native/plugin/{list,discover,info,make}`;
  `native/build --exec` and `native/run --exec`; richer `native/doctor`.
- **Testing**: `Native::fake()`, protocol drift guard tests, generated protocol reference.
- **Docs**: `docs/` with Mermaid diagrams.

### Changed
- Console commands moved to a `native` module (`native/plugin/list` now works as documented).
- Element ids are path-based and deterministic (`root.0.1`).
- Widget properties are computed when the element is built, not frozen in `init()`.
- `protocol.json` now describes the bridge, events and common properties.
- Tests run under both PHPUnit and Codeception.

### Fixed
- Console commands crashed after running on Yii 2.2 (`EXIT_CODE_*` constants were removed); now use `ExitCode`.
- README widget example threw `UnknownPropertyException`; widgets can now be nested with `make()`.
- The JS client posted to an endpoint that did not exist and sent no CSRF token.
- `.github/workflow/` was never read by GitHub Actions; moved to `.github/workflows/`.
- `composer test:phpunit` failed on every test.
- Identifier validation used `$` anchors, which accept a trailing newline; now `\z`.

## 0.1.0

Foundation release: component, runtime, minimal bridge, six widgets, plugin model, console controller.
