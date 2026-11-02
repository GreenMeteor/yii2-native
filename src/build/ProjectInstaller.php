<?php

namespace greenmeteor\native\build;

use Yii;
use yii\helpers\FileHelper;

/**
 * Creates the native project layout and a starter configuration file.
 */
class ProjectInstaller
{
    public const DIRECTORIES = ['ios', 'android', 'plugins', 'config', 'build'];

    public function __construct(private readonly string $root)
    {
    }

    /**
     * @return array{created:string[],skipped:string[]} paths relative to the project root
     */
    public function install(bool $force = false): array
    {
        $created = [];
        $skipped = [];

        foreach (self::DIRECTORIES as $dir) {
            $path = $this->root . '/' . $dir;
            if (is_dir($path)) {
                $skipped[] = $dir . '/';
                continue;
            }
            FileHelper::createDirectory($path);
            $created[] = $dir . '/';
        }

        $files = [
            'config/native.php' => $this->configStub(),
            '.gitignore' => "/build/\n",
        ];
        foreach ($files as $relative => $contents) {
            $path = $this->root . '/' . $relative;
            if (is_file($path) && !$force) {
                $skipped[] = $relative;
                continue;
            }
            file_put_contents($path, $contents);
            $created[] = $relative;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function configStub(): string
    {
        $name = Yii::$app !== null ? addslashes((string) Yii::$app->name) : 'My App';

        return <<<PHP
<?php

/**
 * Yii2 Native component configuration.
 *
 * Use it in your application config:
 *
 *     'components' => [
 *         'native' => require __DIR__ . '/../native/config/native.php',
 *     ],
 */
return [
    'class' => \\greenmeteor\\native\\Native::class,
    'appId' => 'com.example.app',     /* reverse-DNS, lowercase */
    'name' => '{$name}',
    'version' => '1.0.0',
    'versionCode' => 1,
    'orientation' => 'portrait',      /* portrait | landscape | auto */
    'deepLinkScheme' => null,         /* e.g. 'myapp' */
    'deepLinkHosts' => [],
    /* Declare what the app uses; values are optional iOS usage descriptions. */
    'permissions' => [
        /* 'camera' => 'Scan receipts.', */
        /* 'location', */
    ],
    'plugins' => [
        /* '@app/native/plugins', */
    ],
    'http' => [
        /* 'secret' => 'a-long-random-string-shared-with-the-shell', */
    ],
    'build' => [
        /* 'android' => ['build' => './gradlew assembleDebug', 'run' => './gradlew installDebug'], */
        /* 'ios' => ['build' => 'xcodebuild -scheme App build'], */
    ],
];

PHP;
    }
}
