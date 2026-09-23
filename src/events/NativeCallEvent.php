<?php

namespace greenmeteor\native\events;

use yii\base\Event;

class NativeCallEvent extends Event
{
    public string $function = '';
    public array $arguments = [];
    public $result;
    public ?\Throwable $error = null;
}
