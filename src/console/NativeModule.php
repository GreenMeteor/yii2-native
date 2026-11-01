<?php

namespace greenmeteor\native\console;

use yii\base\Module;

/**
 * Console module that groups the `native/*` commands:
 *
 *     native/install  native/doctor  native/config  native/manifest
 *     native/build    native/run
 *     native/plugin/list  native/plugin/discover  native/plugin/info  native/plugin/make
 */
class NativeModule extends Module
{
    public $controllerNamespace = 'greenmeteor\native\commands';
    public $defaultRoute = 'doctor';
}
