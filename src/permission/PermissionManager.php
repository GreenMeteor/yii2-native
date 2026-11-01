<?php

namespace greenmeteor\native\permission;

use greenmeteor\native\exceptions\NativePermissionException;
use greenmeteor\native\protocol\Protocol;
use yii\base\Component;

/**
 * Holds the permissions the application declares and maps them to the
 * Android manifest entries and iOS Info.plist keys needed at build time.
 *
 * Configure either a list or a map (permission => iOS usage description):
 *
 * ```php
 * 'permissions' => [
 *     'camera' => 'Scan receipts.',
 *     'location',
 * ],
 * ```
 */
class PermissionManager extends Component
{
    /** Permissions Android always needs for the bridge to work. */
    public const ANDROID_ALWAYS = [
        'android.permission.INTERNET',
        'android.permission.ACCESS_NETWORK_STATE',
        'android.permission.VIBRATE',
    ];

    public const DEFINITIONS = [
        'camera' => [
            'android' => ['android.permission.CAMERA'],
            'plist' => ['NSCameraUsageDescription'],
            'reason' => 'This app uses the camera.',
        ],
        'microphone' => [
            'android' => ['android.permission.RECORD_AUDIO'],
            'plist' => ['NSMicrophoneUsageDescription'],
            'reason' => 'This app uses the microphone.',
        ],
        'location' => [
            'android' => ['android.permission.ACCESS_FINE_LOCATION', 'android.permission.ACCESS_COARSE_LOCATION'],
            'plist' => ['NSLocationWhenInUseUsageDescription'],
            'reason' => 'This app uses your location.',
        ],
        'background_location' => [
            'android' => ['android.permission.ACCESS_BACKGROUND_LOCATION'],
            'plist' => ['NSLocationAlwaysAndWhenInUseUsageDescription'],
            'reason' => 'This app uses your location in the background.',
        ],
        'biometrics' => [
            'android' => ['android.permission.USE_BIOMETRIC'],
            'plist' => ['NSFaceIDUsageDescription'],
            'reason' => 'This app uses Face ID to authenticate you.',
        ],
        'photo_library' => [
            'android' => ['android.permission.READ_MEDIA_IMAGES', 'android.permission.READ_MEDIA_VIDEO'],
            'plist' => ['NSPhotoLibraryUsageDescription', 'NSPhotoLibraryAddUsageDescription'],
            'reason' => 'This app accesses your photo library.',
        ],
        'push_notifications' => [
            'android' => ['android.permission.POST_NOTIFICATIONS'],
            'plist' => [],
            'entitlements' => ['aps-environment' => 'production'],
            'reason' => 'This app sends notifications.',
        ],
    ];

    /** Throw when a function needs a permission that is not declared. */
    public bool $enforce = true;
    public ?Protocol $protocol = null;

    /** @var array<string,string|null> permission => custom iOS usage description */
    private array $granted = [];

    /** @param array<int|string,string|null> $permissions */
    public function setPermissions(array $permissions): void
    {
        $this->granted = [];
        $this->merge($permissions);
    }

    /** @param array<int|string,string|null> $permissions */
    public function merge(array $permissions): void
    {
        foreach ($permissions as $key => $value) {
            if (is_int($key)) {
                $this->granted[(string) $value] ??= null;
            } else {
                $this->granted[$key] = $value === null ? null : (string) $value;
            }
        }
    }

    public function has(string $permission): bool
    {
        return array_key_exists($permission, $this->granted);
    }

    /** @return string[] */
    public function names(): array
    {
        $names = array_keys($this->granted);
        sort($names);

        return $names;
    }

    /** @return string[] declared permission names that are not known. */
    public function unknown(): array
    {
        return array_values(array_diff($this->names(), array_keys(self::DEFINITIONS)));
    }

    /** @return string[] permissions needed by `$function` that are not declared. */
    public function missing(string $function): array
    {
        $required = $this->protocol?->permissionsFor($function) ?? [];

        return array_values(array_filter($required, fn (string $p) => !$this->has($p)));
    }

    /** @throws NativePermissionException */
    public function assertAllowed(string $function): void
    {
        if (!$this->enforce) {
            return;
        }

        $missing = $this->missing($function);
        if ($missing) {
            $e = new NativePermissionException(sprintf(
                "%s requires the %s permission(s). Add them to the 'permissions' option of the native component.",
                $function,
                "'" . implode("', '", $missing) . "'"
            ));
            $e->missing = $missing;
            throw $e;
        }
    }

    /** @return string[] sorted, unique Android permission strings. */
    public function androidPermissions(): array
    {
        $list = self::ANDROID_ALWAYS;
        foreach ($this->names() as $name) {
            $list = array_merge($list, self::DEFINITIONS[$name]['android'] ?? []);
        }
        $list = array_values(array_unique($list));
        sort($list);

        return $list;
    }

    /** @return array<string,string> Info.plist usage key => description. */
    public function iosPlist(): array
    {
        $plist = [];
        foreach ($this->names() as $name) {
            $def = self::DEFINITIONS[$name] ?? null;
            foreach ($def['plist'] ?? [] as $key) {
                $plist[$key] = $this->granted[$name] ?? $def['reason'];
            }
        }
        ksort($plist);

        return $plist;
    }

    /** @return array<string,string> */
    public function iosEntitlements(): array
    {
        $out = [];
        foreach ($this->names() as $name) {
            $out += self::DEFINITIONS[$name]['entitlements'] ?? [];
        }
        ksort($out);

        return $out;
    }
}
