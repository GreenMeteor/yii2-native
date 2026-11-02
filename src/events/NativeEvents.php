<?php

namespace greenmeteor\native\events;

/**
 * Names of events delivered by the native shell.
 *
 * Listen with `Yii::$app->native->on(NativeEvents::FOREGROUND, $handler)`.
 * Every event is additionally re-triggered as {@see self::ANY}.
 */
final class NativeEvents
{
    public const LAUNCH = 'nativeLaunch';
    public const READY = 'nativeReady';
    public const ACTIVE = 'nativeActive';
    public const INACTIVE = 'nativeInactive';
    public const FOREGROUND = 'nativeForeground';
    public const BACKGROUND = 'nativeBackground';
    public const TERMINATING = 'nativeTerminating';
    public const LOW_MEMORY = 'nativeLowMemory';

    public const ACTION = 'nativeAction';
    public const DEEP_LINK = 'nativeDeepLink';
    public const NETWORK_CHANGED = 'nativeNetworkChanged';

    public const CAMERA_PHOTO_TAKEN = 'nativeCameraPhotoTaken';
    public const CAMERA_VIDEO_RECORDED = 'nativeCameraVideoRecorded';
    public const MEDIA_PICKED = 'nativeMediaPicked';
    public const FILES_PICKED = 'nativeFilesPicked';
    public const BIOMETRIC_COMPLETED = 'nativeBiometricCompleted';
    public const DIALOG_BUTTON_PRESSED = 'nativeDialogButtonPressed';
    public const SCANNER_SCANNED = 'nativeScannerScanned';
    public const LOCATION_RECEIVED = 'nativeLocationReceived';
    public const LOCATION_PERMISSION_RESULT = 'nativeLocationPermissionResult';
    public const MICROPHONE_RECORDED = 'nativeMicrophoneRecorded';
    public const PUSH_TOKEN_RECEIVED = 'nativePushTokenReceived';
    public const PUSH_RECEIVED = 'nativePushReceived';
    public const NOTIFICATION_TAPPED = 'nativeNotificationTapped';
    public const BROWSER_AUTH_COMPLETED = 'nativeBrowserAuthCompleted';

    /** Catch-all: triggered after every dispatched native event. */
    public const ANY = 'native';

    /** @return string[] every built-in native event name (excluding {@see self::ANY}). */
    public static function all(): array
    {
        $names = (new \ReflectionClass(self::class))->getConstants();
        unset($names['ANY']);

        return array_values($names);
    }

    private function __construct()
    {
    }
}
