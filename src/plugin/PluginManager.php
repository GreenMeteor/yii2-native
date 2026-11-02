<?php

namespace greenmeteor\native\plugin;

use greenmeteor\native\Native;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Registers, discovers and boots native plugins.
 *
 * Discovery executes PHP manifest files (`native-plugin.php`) with `require`.
 * Only point it at code you trust (your project, installed Composer packages).
 * JSON manifests (`native-plugin.json`) are data-only and never executed.
 */
class PluginManager extends Component
{
    public const MANIFEST_FILES = ['native-plugin.php', 'native-plugin.json'];
    public const COMPOSER_TYPE = 'yii2-native-plugin';

    private const SKIP_DIRECTORIES = ['node_modules', '.git', 'vendor', 'build', 'Pods'];

    public ?Native $native = null;

    /** @var array<string,PluginInterface> */
    private array $plugins = [];
    /** @var array<string,bool> */
    private array $booted = [];
    /** @var array<string,string> path => error message */
    private array $errors = [];

    public function register(PluginInterface $plugin): void
    {
        $this->plugins[$plugin->getId()] = $plugin;
    }

    public function has(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    public function get(string $id): ?PluginInterface
    {
        return $this->plugins[$id] ?? null;
    }

    public function all(): array
    {
        return $this->plugins;
    }

    /** @return array<string,string> problems found while loading, keyed by manifest path / source */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Find manifests below `$basePath`.
     *
     * @return array<string,array> manifests keyed by plugin id
     */
    public function discover(string $basePath): array
    {
        $found = [];

        if (!is_dir($basePath)) {
            return $found;
        }

        $directory = new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            static function (\SplFileInfo $file) use ($basePath): bool {
                if ($file->isDir()) {
                    return $file->getPathname() === $basePath || !in_array($file->getFilename(), self::SKIP_DIRECTORIES, true);
                }

                return true;
            }
        );

        foreach (new \RecursiveIteratorIterator($filter) as $file) {
            if (!$file->isFile() || !in_array($file->getFilename(), self::MANIFEST_FILES, true)) {
                continue;
            }

            $manifest = $this->readManifest($file->getPathname());
            if ($manifest !== null && isset($manifest['id'])) {
                $found[$manifest['id']] = $manifest;
            }
        }

        return $found;
    }

    /**
     * Find plugins among installed Composer packages: packages of type
     * `yii2-native-plugin`, or with an `extra.yii2-native` section
     * (optionally `{"manifest": "path/inside/package"}`).
     *
     * @return array<string,array> manifests keyed by plugin id
     */
    public function discoverComposer(string $vendorPath): array
    {
        $found = [];
        $installed = rtrim($vendorPath, '/\\') . '/composer/installed.json';
        if (!is_file($installed)) {
            return $found;
        }

        $data = json_decode((string) file_get_contents($installed), true);
        $packages = is_array($data) ? ($data['packages'] ?? $data) : [];

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $extra = $package['extra']['yii2-native'] ?? null;
            if (($package['type'] ?? null) !== self::COMPOSER_TYPE && $extra === null) {
                continue;
            }

            $root = rtrim($vendorPath, '/\\') . '/composer/' . ($package['install-path'] ?? '');
            $root = realpath($root);
            if ($root === false) {
                continue;
            }

            $candidates = isset($extra['manifest']) ? [(string) $extra['manifest']] : self::MANIFEST_FILES;
            foreach ($candidates as $relative) {
                $path = realpath($root . '/' . $relative);
                if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
                    continue;
                }
                $manifest = $this->readManifest($path);
                if ($manifest !== null && isset($manifest['id'])) {
                    $manifest['_path'] = dirname($path);
                    $manifest['_package'] = $package['name'] ?? null;
                    $found[$manifest['id']] = $manifest;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Build a plugin from a manifest. Uses `manifest['class']` when present.
     *
     * @throws InvalidConfigException when the manifest is invalid
     */
    public function createFromManifest(array $manifest): PluginInterface
    {
        if ($errors = ManifestValidator::validate($manifest)) {
            throw new InvalidConfigException(
                "Invalid native plugin manifest '" . ($manifest['id'] ?? '?') . "': " . implode(' ', $errors)
            );
        }

        $class = $manifest['class'] ?? Plugin::class;
        $plugin = Yii::createObject(['class' => $class, 'id' => $manifest['id'], 'manifest' => $manifest]);
        if (!$plugin instanceof PluginInterface) {
            throw new InvalidConfigException("Plugin class {$class} must implement " . PluginInterface::class . '.');
        }

        return $plugin;
    }

    /**
     * Register plugins from mixed sources and boot them.
     *
     * Each source is a manifest array, a class name implementing
     * {@see PluginInterface}, or a path/alias to a manifest file or a directory
     * to {@see discover()} in. Problems are collected in {@see getErrors()}
     * instead of aborting the whole load.
     */
    public function load(array $sources): void
    {
        foreach ($sources as $source) {
            try {
                foreach ($this->resolveSource($source) as $plugin) {
                    $this->register($plugin);
                }
            } catch (\Throwable $e) {
                $this->errors[is_string($source) ? $source : 'inline'] = $e->getMessage();
            }
        }

        $this->boot();
    }

    /** Boot every registered bootable plugin once. Requires {@see $native}. */
    public function boot(): void
    {
        if ($this->native === null) {
            return;
        }

        foreach ($this->plugins as $id => $plugin) {
            if (isset($this->booted[$id]) || !$plugin instanceof BootablePluginInterface) {
                continue;
            }
            $this->booted[$id] = true;
            try {
                $plugin->boot($this->native);
            } catch (\Throwable $e) {
                $this->errors[$id] = 'boot failed: ' . $e->getMessage();
            }
        }
    }

    /** @return PluginInterface[] */
    private function resolveSource($source): array
    {
        if (is_array($source)) {
            return [$this->createFromManifest($source)];
        }
        if (!is_string($source) || $source === '') {
            throw new InvalidConfigException('Plugin source must be a manifest array, class name or path.');
        }

        if (class_exists($source)) {
            $plugin = Yii::createObject($source);
            if (!$plugin instanceof PluginInterface) {
                throw new InvalidConfigException("{$source} must implement " . PluginInterface::class . '.');
            }

            return [$plugin];
        }

        $path = str_starts_with($source, '@') ? Yii::getAlias($source) : $source;
        if (is_file($path)) {
            $manifest = $this->readManifest($path);
            if ($manifest === null) {
                throw new InvalidConfigException("Could not read plugin manifest {$path}.");
            }

            return [$this->createFromManifest($manifest)];
        }
        if (is_dir($path)) {
            return array_map(fn (array $m) => $this->createFromManifest($m), array_values($this->discover($path)));
        }

        throw new InvalidConfigException("Plugin source '{$source}' was not found.");
    }

    private function readManifest(string $path): ?array
    {
        try {
            if (str_ends_with($path, '.json')) {
                $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            } else {
                $manifest = require $path;
            }
        } catch (\Throwable $e) {
            $this->errors[$path] = $e->getMessage();

            return null;
        }

        return is_array($manifest) ? $manifest : null;
    }
}
