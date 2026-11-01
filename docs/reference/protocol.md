# Protocol reference

> Generated from `native/protocol.json` by `bin/generate-protocol-docs.php`. Do not edit by hand.

Element-tree protocol **v1**, bridge protocol **v1**: 49 functions, 25 events, 19 node types.

## Bridge functions

*Interactive* functions return immediately; the result arrives later as the listed event, carrying the same `id`.

### Camera

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Camera.getPhoto`<br>Open the camera and capture a photo. | interactive | `camera` | `id?: string` | `nativeCameraPhotoTaken` |
| `Camera.pickImages`<br>Open the photo picker. | interactive | `photo_library` | `multiple?: boolean`<br>`maxItems?: integer`<br>`id?: string` | `nativeMediaPicked` |
| `Camera.recordVideo`<br>Record a video. | interactive | `camera`, `microphone` | `maxDuration?: integer`<br>`id?: string` | `nativeCameraVideoRecorded` |

### Biometrics

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Biometrics.isAvailable`<br>Whether biometric authentication can be used. | immediate | - | - | boolean |
| `Biometrics.prompt`<br>Show the system biometric prompt. | interactive | `biometrics` | `reason?: string`<br>`id?: string` | `nativeBiometricCompleted` |

### Browser

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Browser.open`<br>Open a URL in the system browser. | immediate | - | `url: string` (http-url) | - |
| `Browser.openInApp`<br>Open a URL in an in-app browser sheet. | immediate | - | `url: string` (http-url) | - |
| `Browser.authenticate`<br>Run a web authentication session (OAuth). | interactive | - | `url: string` (http-url)<br>`callbackScheme?: string`<br>`id?: string` | `nativeBrowserAuthCompleted` |

### Clipboard

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Clipboard.copy`<br>Copy text to the clipboard. | immediate | - | `text: string` | - |
| `Clipboard.paste`<br>Read text from the clipboard. | immediate | - | - | string\|null |
| `Clipboard.clear`<br>Clear the clipboard. | immediate | - | - | - |

### Device

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Device.getId`<br>Stable device identifier. | immediate | - | - | string |
| `Device.getInfo`<br>Platform, OS version, model, manufacturer. | immediate | - | - | object |
| `Device.getBattery`<br>Battery state. | immediate | - | - | object {level: float 0..1, isCharging: bool} |
| `Device.vibrate`<br>Vibrate the device. | immediate | - | `duration?: integer` | - |
| `Device.toggleFlashlight`<br>Toggle the flashlight. | immediate | - | - | object {enabled: bool} |

### Dialog

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Dialog.alert`<br>Show a native alert with buttons. | interactive | - | `title: string`<br>`message: string`<br>`buttons?: array`<br>`id?: string` | `nativeDialogButtonPressed` |
| `Dialog.toast`<br>Show a transient toast message. | immediate | - | `message: string`<br>`duration?: short\|long` | - |

### Filesystem

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Filesystem.getPaths`<br>Sandbox directories available to the app. | immediate | - | - | object {documents, cache, temp} |
| `Filesystem.pickFiles`<br>Open the system document picker. | interactive | - | `types?: array`<br>`multiple?: boolean`<br>`id?: string` | `nativeFilesPicked` |
| `Filesystem.saveToPhotos`<br>Save an image or video file to the photo library. | immediate | `photo_library` | `path: string` (path) | - |

### Geolocation

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Geolocation.getCurrentPosition`<br>Request the current position. | interactive | `location` | `highAccuracy?: boolean`<br>`id?: string` | `nativeLocationReceived` |
| `Geolocation.checkPermissions`<br>Current location permission status. | immediate | - | - | object {status: string} |
| `Geolocation.requestPermissions`<br>Ask the user for location permission. | interactive | - | `id?: string` | `nativeLocationPermissionResult` |

### Haptics

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Haptics.impact`<br>Impact feedback. | immediate | - | `style?: light\|medium\|heavy\|rigid\|soft` | - |
| `Haptics.notification`<br>Notification feedback. | immediate | - | `type?: success\|warning\|error` | - |
| `Haptics.selection`<br>Selection-changed feedback. | immediate | - | - | - |

### Microphone

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Microphone.start`<br>Start recording audio. | interactive | `microphone` | `maxDuration?: integer`<br>`id?: string` | `nativeMicrophoneRecorded` |
| `Microphone.stop`<br>Stop recording and emit the recorded file. | immediate | - | - | - |
| `Microphone.pause`<br>Pause the current recording. | immediate | - | - | - |
| `Microphone.resume`<br>Resume a paused recording. | immediate | - | - | - |
| `Microphone.getStatus`<br>Recorder state. | immediate | - | - | string idle\|recording\|paused |

### Network

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Network.getStatus`<br>Connectivity status. | immediate | - | - | object {connected: bool, type: string, isExpensive: bool} |

### Notifications

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Notifications.schedule`<br>Schedule a local notification. | immediate | `push_notifications` | `title: string`<br>`body: string`<br>`at?: integer`<br>`id: string`<br>`data?: object` | - |
| `Notifications.cancel`<br>Cancel a scheduled notification. | immediate | - | `id: string` | - |
| `Notifications.enrollPush`<br>Register for remote push notifications. | interactive | `push_notifications` | `id?: string` | `nativePushTokenReceived` |
| `Notifications.getPushToken`<br>Last known push token. | immediate | - | - | string\|null |
| `Notifications.setBadge`<br>Set the app icon badge count. | immediate | - | `count: integer` | - |

### Scanner

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Scanner.scan`<br>Open the barcode / QR scanner. | interactive | `camera` | `formats?: array`<br>`prompt?: string`<br>`id?: string` | `nativeScannerScanned` |
| `Scanner.cancel`<br>Dismiss the scanner. | immediate | - | - | - |

### SecureStorage

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `SecureStorage.set`<br>Store a value in the Keychain / Keystore. | immediate | - | `key: string`<br>`value: string` | - |
| `SecureStorage.get`<br>Read a value. | immediate | - | `key: string` | string\|null |
| `SecureStorage.delete`<br>Delete a value. | immediate | - | `key: string` | - |

### Share

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `Share.text`<br>Open the share sheet with text. | immediate | - | `title?: string`<br>`text: string`<br>`url?: string` (http-url) | - |
| `Share.url`<br>Share a URL. | immediate | - | `title?: string`<br>`url: string` (http-url) | - |
| `Share.file`<br>Share a file. | immediate | - | `title?: string`<br>`path: string` (path)<br>`mime?: string` | - |

### System

| Function | Mode | Permissions | Parameters | Returns / event |
|---|---|---|---|---|
| `System.openAppSettings`<br>Open the OS settings page for this app. | immediate | - | - | - |
| `System.getLocale`<br>Active locale, e.g. en-US. | immediate | - | - | string |
| `System.getAppInfo`<br>Installed app identifiers. | immediate | - | - | object {id, name, version, versionCode} |

## Events

| Event | Description | Payload |
|---|---|---|
| `nativeLaunch` | App process started. | - |
| `nativeReady` | Runtime ready to serve requests. | - |
| `nativeActive` | App became active. | - |
| `nativeInactive` | App became inactive. | - |
| `nativeForeground` | App entered the foreground. | - |
| `nativeBackground` | App entered the background. | - |
| `nativeTerminating` | App is about to terminate. | - |
| `nativeLowMemory` | OS reported low memory. | - |
| `nativeAction` | A UI element emitted an action. | `action: string`<br>`id: string`<br>`value: mixed`<br>`payload: object` |
| `nativeDeepLink` | App opened from a deep link. | `url: string` |
| `nativeNetworkChanged` | Connectivity changed. | `connected: bool`<br>`type: string`<br>`isExpensive: bool` |
| `nativeCameraPhotoTaken` | Photo captured or cancelled. | `id: string`<br>`path: string|null`<br>`mime: string`<br>`cancelled: bool` |
| `nativeCameraVideoRecorded` | Video recorded or cancelled. | `id: string`<br>`path: string|null`<br>`cancelled: bool` |
| `nativeMediaPicked` | Images picked. | `id: string`<br>`files: array<{path,mime}>` |
| `nativeFilesPicked` | Files picked. | `id: string`<br>`files: array<{path,mime,name}>` |
| `nativeBiometricCompleted` | Biometric prompt finished. | `id: string`<br>`success: bool`<br>`error: string|null` |
| `nativeDialogButtonPressed` | Alert button pressed. | `id: string`<br>`index: int`<br>`label: string` |
| `nativeScannerScanned` | Code scanned. | `id: string`<br>`data: string`<br>`format: string` |
| `nativeLocationReceived` | Position result. | `id: string`<br>`success: bool`<br>`latitude: float`<br>`longitude: float`<br>`accuracy: float`<br>`timestamp: int`<br>`error: string|null` |
| `nativeLocationPermissionResult` | Location permission result. | `id: string`<br>`status: string` |
| `nativeMicrophoneRecorded` | Recording finished. | `id: string`<br>`path: string`<br>`duration: float` |
| `nativePushTokenReceived` | Push token issued. | `id: string`<br>`token: string` |
| `nativePushReceived` | Push received in foreground. | `title: string`<br>`body: string`<br>`data: object` |
| `nativeNotificationTapped` | Notification tapped. | `id: string`<br>`data: object` |
| `nativeBrowserAuthCompleted` | Web auth session finished. | `id: string`<br>`url: string|null`<br>`cancelled: bool` |

## UI node types

Every node also accepts the common properties: `id`, `padding`, `margin`, `background`, `width`, `height`, `visible`, `accessibilityLabel`, `testId`.

| Type | Container | Properties | Description |
|---|---|---|---|
| `text` | no | `text`, `size`, `weight`, `color`, `align`, `maxLines` | Text label. |
| `button` | no | `text`, `action`, `payload`, `variant`, `disabled`, `icon` | Tappable button; emits nativeAction. |
| `image` | no | `source`, `alt`, `contentMode`, `cornerRadius` | Image from URL or file path. |
| `vstack` | yes | `spacing`, `align`, `justify` | Vertical stack. |
| `hstack` | yes | `spacing`, `align`, `justify` | Horizontal stack. |
| `zstack` | yes | `align` | Overlaid stack. |
| `scroll` | yes | `horizontal`, `showsIndicator` | Scrollable container. |
| `spacer` | no | `size` | Flexible or fixed space. |
| `divider` | no | `thickness`, `color` | Separator line. |
| `icon` | no | `name`, `size`, `color` | Named system icon. |
| `input` | no | `name`, `value`, `placeholder`, `keyboard`, `secure`, `multiline`, `action`, `onChange` | Text input; emits nativeAction on change/submit. |
| `toggle` | no | `name`, `label`, `value`, `action` | Boolean switch. |
| `card` | yes | `elevation`, `cornerRadius` | Elevated container. |
| `list` | yes | `itemSpacing` | Virtualised list of children. |
| `topbar` | yes | `title`, `subtitle`, `showBack`, `actions` | Top app bar. |
| `bottomnav` | yes | `activeId` | Bottom navigation bar. |
| `navitem` | no | `label`, `icon`, `action`, `active`, `badge` | Bottom navigation item. |
| `fab` | no | `icon`, `action`, `label` | Floating action button. |
| `loading` | no | `size`, `message` | Progress indicator. |
