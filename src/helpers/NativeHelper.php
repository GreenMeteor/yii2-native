<?php

namespace greenmeteor\native\helpers;

use Yii;

final class NativeHelper
{
    public static function available(): bool
    {
        return Yii::$app->native->isAvailable();
    }

    public static function call(string $function, array $arguments = [])
    {
        return Yii::$app->native->call($function, $arguments);
    }

    private function __construct()
    {
    }
}
