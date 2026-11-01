# Architecture

Yii2 Native keeps **native UI rendering** separate from **device / API calls**,
following the same high-level split as NativePHP Mobile:

- the **renderer** turns a widget tree into a compact *frame*; the shell draws it natively;
- the **bridge** is a named-function channel (`Camera.getPhoto`, `SecureStorage.get`, ...)
  for device capabilities, extensible through plugins.

## Components

```mermaid
flowchart TB
    subgraph PHP["Yii2 application (PHP)"]
        direction TB
        N["Native component<br/>Yii::$app->native"]
        RT["Runtime<br/>detect / platform"]
        BR["NativeBridge<br/>validate · guard · dispatch"]
        RD["Renderer<br/>widgets → frame"]
        PM["PluginManager"]
        PERM["PermissionManager"]
        PROTO[("Protocol<br/>protocol.json")]
        API["Device APIs<br/>Camera · Scanner · ..."]
        N --> RT
        N --> BR
        N --> RD
        N --> PM
        N --> PERM
        N --> API
        API --> BR
        BR --> PERM
        BR --> PROTO
        RD --> PROTO
        PM -- "registers functions,<br/>nodes, events" --> PROTO
    end

    subgraph SHELL["Native shell"]
        direction TB
        TR["Transport<br/>nativephp_call"]
        UI["Element tree reader<br/>SwiftUI / Compose"]
        DEV["Device services"]
        TR --> DEV
    end

    BR -- "named call" --> TR
    RD -- "frame (JSON)" --> UI
    SHELL -- "events" --> N
```

## What lives where

| Layer | Namespace | Responsibility |
|---|---|---|
| Component | `greenmeteor\native\Native` | Entry point, configuration, event dispatch, APIs |
| Runtime | `...\runtime` | Am I inside the native shell? Which platform? |
| Bridge | `...\bridge` | Named calls, transports, handlers, validation, events |
| Device APIs | `...\api` | Typed methods over bridge functions |
| Protocol | `...\protocol` | Reads `native/protocol.json`, validates arguments |
| Renderer / widgets | `...\renderer`, `...\widgets` | Element tree and frames |
| Events | `...\events` | Native events and bridge-call events |
| Plugins | `...\plugin` | Manifests, discovery, lifecycle |
| Permissions | `...\permission` | Declared permissions → platform manifests |
| Web | `...\web` | HTTP bridge endpoints, response format |
| Tooling | `...\build`, `...\commands` | Manifest, doctor, installer, scaffolder, CLI |

## A device call

```mermaid
sequenceDiagram
    autonumber
    participant App as Your PHP code
    participant API as Device API
    participant B as NativeBridge
    participant G as PermissionManager
    participant V as ArgumentValidator
    participant T as Transport
    participant S as Native shell

    App->>API: native->device->vibrate(100)
    API->>B: call("Device.vibrate", {duration: 100})
    B->>B: validate function name
    B-->>B: trigger beforeCall
    B->>G: assertAllowed(function)
    B->>V: validate(arguments)
    alt PHP handler registered
        B->>B: run handler
    else runtime available
        B->>T: send(function, arguments)
        T->>S: nativephp_call(...)
        S-->>T: result
        T-->>B: result
    else unavailable
        B-->>App: NativeUnavailableException (or null)
    end
    B-->>B: trigger afterCall
    B-->>App: result
```

## An interactive call and its event

Calls that need the user (camera, scanner, dialogs, ...) cannot return a result
synchronously. They return a **request id** and the result comes back as an
event with the same id. See [Events](events.md).

```mermaid
sequenceDiagram
    autonumber
    participant App as PHP
    participant B as Bridge
    participant S as Native shell
    actor U as User

    App->>B: scanner->scan(id: "qr1")
    B->>S: Scanner.scan {id: "qr1"}
    S-->>App: returns "qr1" immediately
    S->>U: show scanner
    U->>S: scans a code
    S->>App: nativeScannerScanned {id: "qr1", data: "..."}
    Note over App: handler matches $event->id
```

## A screen

```mermaid
sequenceDiagram
    autonumber
    participant S as Native shell
    participant P as PHP (controller)
    participant R as Renderer

    S->>P: GET /home
    P->>R: widgets (VStack, Text, Button)
    R-->>P: frame {version, root}
    P-->>S: JSON frame
    S->>S: render natively
    S->>P: POST /_native/events nativeAction {action: "go"}
    P-->>S: new frame (optional reply)
```

## Persistent runtime

The runtime is designed to keep PHP resident between requests inside the shell
(`Runtime::$persistent` defaults to `true`). The shells are not bundled yet, but
write your code as if that holds:

- Don't keep per-user state in static properties.
- Register event handlers once, at bootstrap (component config or a bootstrap
  class), not inside actions, so they are not added repeatedly.

## Design decisions

- **Contract first.** `native/protocol.json` is the single source of truth for
  functions, events and node types. Tests ensure the PHP APIs, widgets and
  `NativeEvents` match it, and the [reference](reference/protocol.md) is generated
  from it.
- **Validate at the bridge.** Argument validation and permission checks live in
  `NativeBridge`, so PHP callers, the HTTP endpoint and plugins are all covered.
- **Fail early and loudly** for malformed trees and arguments; stay silent only
  where you opt in (`allowUnavailable`).
