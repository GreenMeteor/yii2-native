<?php

namespace greenmeteor\native\build;

use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;

/**
 * Generates the skeleton of a native plugin.
 */
class PluginScaffolder
{
    /**
     * @param string $id        `name` or `vendor/name`
     * @param string $directory target directory (created)
     * @return string[] written files
     */
    public function create(string $id, string $directory): array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._\-]*(\/[a-z0-9][a-z0-9._\-]*)?\z/', $id)) {
            throw new InvalidArgumentException("Plugin id '{$id}' must look like 'name' or 'vendor/name' (lowercase).");
        }
        if (is_file($directory . '/native-plugin.php')) {
            throw new InvalidArgumentException("A plugin already exists in {$directory}.");
        }

        $short = substr(strrchr('/' . $id, '/'), 1);
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_', '.'], ' ', $short)));
        $ns = $studly;

        FileHelper::createDirectory($directory . '/ios');
        FileHelper::createDirectory($directory . '/android');

        $files = [
            '/native-plugin.php' => <<<PHP
<?php

return [
    'id' => '{$id}',
    'name' => '{$studly}',
    'version' => '1.0.0',
    'description' => 'Describe what {$studly} does.',
    'platforms' => ['ios', 'android'],
    'permissions' => [],
    'bridge' => [
        'functions' => [
            '{$studly}.ping' => [
                'description' => 'Example function implemented in PHP.',
                'interactive' => false,
                'params' => ['message' => ['type' => 'string']],
            ],
        ],
    ],
    'events' => [],
    'ios' => ['sources' => 'ios', 'dependencies' => []],
    'android' => ['sources' => 'android', 'dependencies' => []],
];

PHP,
            "/{$studly}Plugin.php" => <<<PHP
<?php

use greenmeteor\\native\\plugin\\Plugin;

/**
 * PHP side of the {$studly} plugin. Autoload it (or set 'class' in
 * native-plugin.php) to have these handlers registered at boot.
 */
class {$studly}Plugin extends Plugin
{
    public function handlers(): array
    {
        return [
            '{$studly}.ping' => static fn (array \$args): array => ['pong' => \$args['message'] ?? null],
        ];
    }
}

PHP,
            '/README.md' => "# {$studly}\n\nNative plugin `{$id}`.\n\n- `native-plugin.php` — manifest\n- `ios/` — Swift sources\n- `android/` — Kotlin sources\n",
            '/ios/.gitkeep' => '',
            '/android/.gitkeep' => '',
        ];

        $written = [];
        foreach ($files as $relative => $contents) {
            file_put_contents($directory . $relative, $contents);
            $written[] = $directory . $relative;
        }

        return $written;
    }
}
