<?php

namespace greenmeteor\native\exceptions;

/**
 * Thrown when the native side reports an error for a bridge call.
 */
class NativeCallException extends NativeException
{
    public string $errorType = 'error';
    public ?string $function = null;

    public static function fromPayload(string $function, array $error): static
    {
        $e = new static((string) ($error['message'] ?? 'Native call failed.'), (int) ($error['code'] ?? 0));
        $e->errorType = (string) ($error['type'] ?? 'error');
        $e->function = $function;

        return $e;
    }
}
