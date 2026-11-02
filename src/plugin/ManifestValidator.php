<?php

namespace greenmeteor\native\plugin;

use greenmeteor\native\permission\PermissionManager;

/**
 * Validates plugin manifests (`native-plugin.php` / `native-plugin.json`).
 *
 * ```php
 * return [
 *     'id' => 'acme/maps',
 *     'name' => 'Maps',
 *     'version' => '1.0.0',
 *     'description' => '...',
 *     'class' => acme\maps\MapsPlugin::class,
 *     'platforms' => ['ios', 'android'],
 *     'permissions' => ['location'],
 *     'bridge' => ['functions' => ['Maps.open' => ['interactive' => false, 'params' => []]]],
 *     'elements' => ['map' => ['description' => 'Map view']],
 *     'events' => ['mapsMarkerTapped' => []],
 *     'ios' => ['sources' => 'ios', 'dependencies' => ['MapKit']],
 *     'android' => ['sources' => 'android', 'dependencies' => ['com.google.android.gms:play-services-maps:18.2.0']],
 * ];
 * ```
 *
 * `id` is required (`name` or `vendor/name`, lowercase). `class` is an optional
 * `PluginInterface` implementation.
 */
final class ManifestValidator
{
    public const PLATFORMS = ['ios', 'android'];
    public const ID_PATTERN = '/^[a-z0-9][a-z0-9._\-]*(\/[a-z0-9][a-z0-9._\-]*)?\z/';

    /** @return string[] problems; empty when the manifest is valid */
    public static function validate(array $manifest): array
    {
        $errors = [];

        $id = $manifest['id'] ?? null;
        if (!is_string($id) || !preg_match(self::ID_PATTERN, $id)) {
            $errors[] = "id is required and must look like 'name' or 'vendor/name' (lowercase).";
        }
        if (isset($manifest['version']) && !preg_match('/^\d+\.\d+\.\d+([\-+][0-9A-Za-z.\-]+)?\z/', (string) $manifest['version'])) {
            $errors[] = "version '{$manifest['version']}' must look like 1.2.3.";
        }
        if (isset($manifest['class']) && !is_string($manifest['class'])) {
            $errors[] = 'class must be a class-name string.';
        }
        foreach ((array) ($manifest['platforms'] ?? []) as $platform) {
            if (!in_array($platform, self::PLATFORMS, true)) {
                $errors[] = "Unknown platform '{$platform}'. Allowed: " . implode(', ', self::PLATFORMS) . '.';
            }
        }
        foreach ((array) ($manifest['permissions'] ?? []) as $permission) {
            if (!is_string($permission) || !isset(PermissionManager::DEFINITIONS[$permission])) {
                $errors[] = 'Unknown permission ' . json_encode($permission) . '.';
            }
        }

        $functions = $manifest['bridge']['functions'] ?? [];
        if (!is_array($functions)) {
            $errors[] = 'bridge.functions must be a map of function name => spec.';
        } else {
            foreach ($functions as $name => $spec) {
                if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*\z/', $name)) {
                    $errors[] = "Bridge function '" . (is_string($name) ? $name : json_encode($name)) . "' must be named Namespace.method.";
                }
                if (!is_array($spec)) {
                    $errors[] = "Bridge function spec for '{$name}' must be an array.";
                }
            }
        }

        foreach (array_keys((array) ($manifest['elements'] ?? [])) as $type) {
            if (!is_string($type) || !preg_match('/^[a-z][a-z0-9\-]*\z/', $type)) {
                $errors[] = "Element type '{$type}' must be lowercase letters, digits and dashes.";
            }
        }

        foreach ((array) ($manifest['events'] ?? []) as $key => $value) {
            $name = is_int($key) ? $value : $key;
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9._:\-]*\z/', $name)) {
                $errors[] = 'Invalid event name ' . json_encode($name) . '.';
            }
        }

        return $errors;
    }
}
