<?php

namespace greenmeteor\native\tests\Support;

use greenmeteor\native\Native;
use PHPUnit\Framework\TestCase;
use yii\console\Application;

abstract class NativeTestCase extends TestCase
{
    /** Boot a console app with a `native` component; returns the component. */
    protected function native(array $config = []): Native
    {
        if (\Yii::$app !== null) {
            \Yii::$app->getErrorHandler()->unregister();
        }

        new Application([
            'id' => 'test',
            'name' => 'Test App',
            'basePath' => dirname(__DIR__),
            'components' => [
                'native' => array_merge([
                    'class' => Native::class,
                    'appId' => 'com.example.test',
                ], $config),
            ],
        ]);

        return \Yii::$app->get('native');
    }

    protected function tearDown(): void
    {
        // Every Yii Application registers global error handlers; remove them
        // so PHPUnit does not flag the test as risky.
        if (\Yii::$app !== null) {
            \Yii::$app->getErrorHandler()->unregister();
            \Yii::$app = null;
        }
        parent::tearDown();
    }

    protected function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/yii2-native-' . bin2hex(random_bytes(5));
        mkdir($dir, 0777, true);
        register_shutdown_function(static fn () => self::rm($dir));

        return $dir;
    }

    protected static function rm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? self::rm($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
