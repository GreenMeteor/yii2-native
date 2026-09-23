<?php

namespace greenmeteor\native;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Application;

class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if (!$app instanceof Application) {
            return;
        }

        if (!isset($app->components['native'])) {
            $app->set('native', [
                'class' => Native::class,
            ]);
        }

        if ($app instanceof \yii\console\Application) {
            $app->controllerMap['native'] = [
                'class' => commands\NativeController::class,
            ];
        }
    }
}
