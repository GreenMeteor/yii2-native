# Native shells

This directory is the platform boundary for Yii2 Native.

The intended architecture is:

- `ios/` — Swift / SwiftUI application shell
- `android/` — Kotlin / Jetpack Compose application shell
- the PHP application is embedded into the mobile application
- the PHP/native bridge exposes device APIs
- the renderer consumes the Yii2 element protocol

The current package deliberately does not ship a fake production PHP runtime or placeholder Xcode/Gradle project. Those pieces need to be implemented as real native build targets and kept versioned with the PHP runtime/protocol.
