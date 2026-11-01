<?php

namespace greenmeteor\native\helpers;

use greenmeteor\native\events\NativeEvent;
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

    /** `ios`, `android`, or null when not running inside the native shell. */
    public static function platform(): ?string
    {
        return Yii::$app->native->runtime->getPlatform();
    }

    public static function isIos(): bool
    {
        return Yii::$app->native->runtime->isIos();
    }

    public static function isAndroid(): bool
    {
        return Yii::$app->native->runtime->isAndroid();
    }

    public static function isMobile(): bool
    {
        return Yii::$app->native->runtime->isMobile();
    }

    public static function dispatch(string $type, array $payload = [], ?string $id = null): NativeEvent
    {
        return Yii::$app->native->dispatch($type, $payload, $id);
    }

    private function __construct()
    {
    }
}
