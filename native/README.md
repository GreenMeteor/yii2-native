# Native shells

This directory is the platform boundary for Yii2 Native.

The intended architecture is:

- `ios/` — Swift / SwiftUI application shell
- `android/` — Kotlin / Jetpack Compose application shell
- the PHP application is embedded into the mobile application
- the PHP/native bridge exposes device APIs
- the renderer consumes the Yii2 element protocol

The current package deliberately does not ship a fake production PHP runtime or placeholder Xcode/Gradle project. Those pieces need to be implemented as real native build targets and kept versioned with the PHP runtime/protocol.

## What a shell must implement

The contract between the PHP package and a shell is [`protocol.json`](protocol.json)
(human-readable form: [`docs/reference/protocol.md`](../docs/reference/protocol.md)).

1. **Embed PHP** and expose a global `nativephp_call(string $function, array|string $arguments)`
   (configurable: see `FunctionTransport`). Set `NATIVE_RUNTIME=1` and
   `NATIVE_PLATFORM=ios|android` in the PHP environment.
2. **Implement the bridge functions** you support from `bridge.functions`. A function's
   result is returned as the call's value; failures are returned as
   `{"error": {"type": "...", "message": "...", "code": 0}}` (or thrown).
3. **Interactive functions** (`"interactive": true`) must return immediately and later deliver
   the declared `event` with the same `id` as the request.
4. **Deliver events** to PHP by `POST`ing `{event, payload, id?}` to the events endpoint with
   Yii's CSRF token (and `X-Native-Secret` if configured), or by calling `Native::dispatch()`
   in-process. A successful response may contain a UI frame in `result`.
5. **Render frames**: `{"version": 1, "root": {type, properties, children}}`. Node types and
   properties are in `nodes`; ignore unknown properties and node types so additive protocol
   changes don't break older shells. Use `properties.id` to diff consecutive frames.
6. **Consume the build manifest** written by `php yii native/build` (`native/build/manifest.json`,
   Android permission and iOS Info.plist fragments), and check its `protocolVersion`.
7. **Forward lifecycle** transitions as the `native*` lifecycle events.
