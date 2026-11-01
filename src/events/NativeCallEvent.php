<?php

namespace greenmeteor\native\events;

use yii\base\Event;

/**
 * Raised by {@see \greenmeteor\native\bridge\NativeBridge} around every call.
 *
 * A `beforeCall` listener may short-circuit the call by setting
 * {@see $handled} to true and providing {@see $result}.
 */
class NativeCallEvent extends Event
{
    public string $function = '';
    public array $arguments = [];
    public $result;
    public ?\Throwable $error = null;
    /** Elapsed time in milliseconds; set on `afterCall` and `error`. */
    public ?float $duration = null;
}
