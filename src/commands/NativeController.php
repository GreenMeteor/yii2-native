<?php

namespace greenmeteor\native\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\FileHelper;

class NativeController extends Controller
{
    public function actionInstall(): int
    {
        $root = Yii::getAlias('@app/native');

        foreach ([
            $root,
            $root . '/ios',
            $root . '/android',
            $root . '/plugins',
            $root . '/config',
        ] as $directory) {
            FileHelper::createDirectory($directory);
        }

        $this->stdout("Yii2 Native project initialized at {$root}\n");

        return self::EXIT_CODE_NORMAL;
    }

    public function actionDoctor(): int
    {
        $this->stdout("Yii2 Native environment\n");
        $this->stdout("PHP: " . PHP_VERSION . "\n");
        $this->stdout("Yii2: " . Yii::getVersion() . "\n");
        $this->stdout(
            "Native runtime: "
            . (Yii::$app->native->isAvailable() ? 'available' : 'not attached')
            . "\n"
        );

        return self::EXIT_CODE_NORMAL;
    }

    public function actionPluginList(): int
    {
        $plugins = Yii::$app->native->pluginManager->all();

        if (!$plugins) {
            $this->stdout("No registered native plugins.\n");
            return self::EXIT_CODE_NORMAL;
        }

        foreach ($plugins as $plugin) {
            $this->stdout($plugin->getId() . "\n");
        }

        return self::EXIT_CODE_NORMAL;
    }

    public function actionBuild(string $platform = 'all'): int
    {
        $this->stdout(
            "Native build requested for {$platform}.\n"
            . "The platform shell/build toolchain is not bundled in this PHP package yet.\n"
        );

        return self::EXIT_CODE_NORMAL;
    }

    public function actionRun(string $platform = 'all'): int
    {
        $this->stdout(
            "Native run requested for {$platform}.\n"
            . "The platform shell/build toolchain is not bundled in this PHP package yet.\n"
        );

        return self::EXIT_CODE_NORMAL;
    }
}
