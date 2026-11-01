<?php

namespace greenmeteor\native\build;

use greenmeteor\native\Native;
use greenmeteor\native\protocol\Protocol;
use Yii;

/**
 * Environment and configuration checks behind `native/doctor`.
 *
 * Each check is `['name' => string, 'status' => 'ok'|'warn'|'fail', 'message' => string]`.
 */
class Doctor
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    public function __construct(private readonly Native $native)
    {
    }

    /** @return array<int,array{name:string,status:string,message:string}> */
    public function run(): array
    {
        $checks = [];
        $add = static function (string $name, string $status, string $message) use (&$checks): void {
            $checks[] = ['name' => $name, 'status' => $status, 'message' => $message];
        };
        $n = $this->native;

        $add('PHP', PHP_VERSION_ID >= 80200 ? self::OK : self::FAIL, PHP_VERSION . (PHP_VERSION_ID >= 80200 ? '' : ' (8.2+ required)'));
        $add('Yii2', self::OK, Yii::getVersion());

        foreach (['json', 'mbstring'] as $ext) {
            $add("ext-{$ext}", extension_loaded($ext) ? self::OK : self::WARN, extension_loaded($ext) ? 'loaded' : 'missing');
        }

        try {
            $protocol = $n->getProtocol();
            $add('Protocol', self::OK, sprintf(
                'v%d, %d functions, %d node types, %d events',
                $protocol->version(),
                count($protocol->functions()),
                count($protocol->nodeTypes()),
                count($protocol->eventNames())
            ));
        } catch (\Throwable $e) {
            $add('Protocol', self::FAIL, $e->getMessage());
        }

        $errors = $n->validate();
        $add('Configuration', $errors ? self::FAIL : self::OK, $errors ? implode(' ', $errors) : 'valid');

        $root = Yii::getAlias($n->projectPath);
        $add('Project directory', is_dir($root) ? self::OK : self::WARN, is_dir($root) ? $root : "{$root} missing - run `php yii native/install`");

        foreach (['ios' => ['*.xcodeproj', '*.xcworkspace', 'Package.swift'], 'android' => ['gradlew', 'build.gradle', 'build.gradle.kts', 'settings.gradle.kts']] as $platform => $markers) {
            $dir = $root . '/' . $platform;
            $present = false;
            foreach ($markers as $marker) {
                if (glob($dir . '/' . $marker)) {
                    $present = true;
                    break;
                }
            }
            $add("{$platform} shell", $present ? self::OK : self::WARN, $present ? 'project found' : 'no project found (shell not bundled in this package)');
        }

        $plugins = $n->getPluginManager();
        $add('Plugins', self::OK, count($plugins->all()) . ' registered');
        foreach ($plugins->getErrors() as $source => $message) {
            $add('Plugin: ' . $source, self::FAIL, $message);
        }

        $add('Native runtime', self::OK, $n->isAvailable() ? 'available' : 'not attached');

        if ($n->http['enabled']) {
            $secret = $n->http['secret'];
            if ($secret === null && !$n->http['requireRuntime']) {
                $add('HTTP bridge', self::FAIL, 'enabled without a secret and without requireRuntime: any browser could call native functions.');
            } elseif ($secret === null) {
                $add('HTTP bridge', self::WARN, 'enabled without http.secret (relies on requireRuntime + CSRF only).');
            } else {
                $add('HTTP bridge', self::OK, 'enabled with shared secret');
            }
        } else {
            $add('HTTP bridge', self::OK, 'disabled');
        }

        return $checks;
    }

    public static function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === self::FAIL) {
                return true;
            }
        }

        return false;
    }
}
