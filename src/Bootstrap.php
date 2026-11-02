<?php

namespace greenmeteor\native;

use greenmeteor\native\console\NativeModule;
use greenmeteor\native\web\BridgeController;
use greenmeteor\native\web\NativeResponseFormatter;
use greenmeteor\native\web\PreviewController;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;

/**
 * Composer-discovered bootstrap: registers `Yii::$app->native`, the console
 * commands (web: HTTP bridge routes, the dev preview routes and the `native`
 * response format). The preview is inert unless `preview.enabled` is set.
 */
class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if (!$app instanceof Application) {
            return;
        }

        Yii::setAlias('@greenmeteor/native', __DIR__);

        if (!$app->has('native')) {
            $app->set('native', [
                'class' => Native::class,
            ]);
        }

        if ($app instanceof \yii\console\Application) {
            if (!$app->hasModule('native')) {
                $app->setModule('native', ['class' => NativeModule::class]);
            }

            return;
        }

        if ($app instanceof \yii\web\Application) {
            $app->controllerMap['native-bridge'] ??= ['class' => BridgeController::class];

            $app->controllerMap['native-preview'] ??= ['class' => PreviewController::class];

            $app->getUrlManager()->addRules([
                'POST _native/bridge' => 'native-bridge/call',
                'POST _native/events' => 'native-bridge/event',
                'GET _native/preview' => 'native-preview/index',
                'GET _native/preview.js' => 'native-preview/script',
                'POST _native/preview/event' => 'native-preview/event',
            ], false);

            $app->getResponse()->formatters[NativeResponseFormatter::FORMAT] ??= NativeResponseFormatter::class;
        }
    }
}
