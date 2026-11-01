<?php

namespace greenmeteor\native\build;

use greenmeteor\native\Native;
use greenmeteor\native\protocol\Protocol;
use yii\helpers\FileHelper;

/**
 * Produces the build-time contract consumed by the iOS / Android shells:
 * app identity, permissions (as Android manifest entries and Info.plist keys),
 * deep links, plugins and the bridge function list.
 *
 * Output is deterministic (sorted) so it diffs cleanly in version control.
 */
class ManifestBuilder
{
    public const SCHEMA = 1;

    public function __construct(private readonly Native $native)
    {
    }

    public function build(): array
    {
        $n = $this->native;
        $permissions = $n->getPermissionManager();

        $plugins = [];
        foreach ($n->getPluginManager()->all() as $id => $plugin) {
            $m = $plugin->getManifest();
            $plugins[$id] = array_filter([
                'name' => $m['name'] ?? null,
                'version' => $m['version'] ?? null,
                'platforms' => $m['platforms'] ?? ['ios', 'android'],
                'ios' => $m['ios'] ?? null,
                'android' => $m['android'] ?? null,
                'path' => $m['_path'] ?? null,
            ], static fn ($v) => $v !== null);
        }
        ksort($plugins);

        $functions = array_keys($n->getProtocol()->functions());
        sort($functions);

        return [
            'schema' => self::SCHEMA,
            'generator' => 'greenmeteor/yii2-native ' . Native::VERSION,
            'protocolVersion' => Protocol::VERSION,
            'app' => [
                'id' => $n->appId,
                'name' => $n->name,
                'version' => $n->version,
                'versionCode' => $n->versionCode,
                'orientation' => $n->orientation,
            ],
            'deepLinks' => [
                'scheme' => $n->deepLinkScheme,
                'hosts' => array_values($n->deepLinkHosts),
            ],
            'permissions' => $permissions->names(),
            'android' => ['permissions' => $permissions->androidPermissions()],
            'ios' => [
                'infoPlist' => $permissions->iosPlist(),
                'entitlements' => $permissions->iosEntitlements(),
            ],
            'plugins' => $plugins,
            'bridgeFunctions' => $functions,
        ];
    }

    /** `<uses-permission>` elements ready to paste into AndroidManifest.xml. */
    public function androidManifestFragment(): string
    {
        $lines = [];
        foreach ($this->native->getPermissionManager()->androidPermissions() as $permission) {
            $lines[] = '<uses-permission android:name="' . $this->xml($permission) . '" />';
        }

        return implode("\n", $lines) . "\n";
    }

    /** `<key>/<string>` pairs ready to merge into Info.plist. */
    public function iosPlistFragment(): string
    {
        $lines = [];
        foreach ($this->native->getPermissionManager()->iosPlist() as $key => $reason) {
            $lines[] = '<key>' . $this->xml($key) . '</key>';
            $lines[] = '<string>' . $this->xml($reason) . '</string>';
        }

        return implode("\n", $lines) . ($lines ? "\n" : '');
    }

    /**
     * Write `manifest.json` and the platform fragments.
     *
     * @return string[] written file paths
     */
    public function write(string $directory): array
    {
        FileHelper::createDirectory($directory . '/android');
        FileHelper::createDirectory($directory . '/ios');

        $files = [
            $directory . '/manifest.json' => json_encode(
                $this->build(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n",
            $directory . '/android/permissions.xml' => $this->androidManifestFragment(),
            $directory . '/ios/Info.plist.fragment' => $this->iosPlistFragment(),
        ];

        foreach ($files as $path => $contents) {
            file_put_contents($path, $contents);
        }

        return array_keys($files);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
