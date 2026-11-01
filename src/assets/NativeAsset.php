<?php

namespace greenmeteor\native\assets;

use Yii;
use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Publishes `resources/js/native.js` (an ES module that also sets `window.Native`)
 * and passes the bridge endpoints from the PHP configuration to it.
 *
 * ```php
 * NativeAsset::register($this);   // in a layout or view
 * ```
 */
class NativeAsset extends AssetBundle
{
    public $sourcePath = '@greenmeteor/native/../resources/js';
    public $js = ['native.js'];
    public $jsOptions = ['type' => 'module', 'position' => View::POS_HEAD];

    public static function register($view)
    {
        $bundle = parent::register($view);

        $native = Yii::$app->has('native') ? Yii::$app->get('native') : null;
        if ($native !== null && $native->http['enabled']) {
            $config = [
                'bridge' => Url::to(['/native-bridge/call']),
                'events' => Url::to(['/native-bridge/event']),
            ];
            if (Yii::$app->getUrlManager()->enablePrettyUrl) {
                $config['bridge'] = Url::to('/' . ltrim($native->http['bridgeRoute'], '/'));
                $config['events'] = Url::to('/' . ltrim($native->http['eventsRoute'], '/'));
            }
            $view->registerJsVar('yii2NativeConfig', $config, View::POS_HEAD);
        }

        return $bundle;
    }
}
