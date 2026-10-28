<?php

namespace greenmeteor\native\bridge;

use greenmeteor\native\events\NativeCallEvent;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use greenmeteor\native\exceptions\NativeUnavailableException;

class NativeBridge extends Component
{
    public const EVENT_BEFORE_CALL = 'beforeCall';
    public const EVENT_AFTER_CALL = 'afterCall';
    public const EVENT_ERROR = 'error';

    public bool $allowUnavailable = false;

    public function call(string $function, array $arguments = [])
    {
        if ($function === '') {
            throw new InvalidArgumentException('Native function name cannot be empty.');
        }

        $event = new NativeCallEvent([
            'function' => $function,
            'arguments' => $arguments,
        ]);

        $this->trigger(self::EVENT_BEFORE_CALL, $event);

        try {
            $result = $this->dispatch($function, $arguments);
            $event->result = $result;
            $this->trigger(self::EVENT_AFTER_CALL, $event);

            return $result;
        } catch (\Throwable $e) {
            $event->error = $e;
            $this->trigger(self::EVENT_ERROR, $event);

            throw $e;
        }
    }

    protected function dispatch(string $function, array $arguments)
    {
        if (function_exists('nativephp_call')) {
            return nativephp_call($function, $arguments);
        }

        if (!$this->allowUnavailable) {
            throw new NativeUnavailableException(
                'The Yii2 Native runtime is not available. '
                . 'This bridge call requires the native mobile runtime.'
            );
        }

        return null;
    }
}
