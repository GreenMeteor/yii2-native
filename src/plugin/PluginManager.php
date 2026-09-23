<?php

namespace greenmeteor\native\plugin;

use yii\base\Component;

class PluginManager extends Component
{
    private array $plugins = [];

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

    public function discover(string $basePath): array
    {
        $found = [];

        if (!is_dir($basePath)) {
            return $found;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $basePath,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() !== 'native-plugin.php') {
                continue;
            }

            $manifest = require $file->getPathname();

            if (is_array($manifest) && isset($manifest['id'])) {
                $found[$manifest['id']] = $manifest;
            }
        }

        return $found;
    }
}
