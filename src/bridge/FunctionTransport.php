<?php

namespace greenmeteor\native\bridge;

use greenmeteor\native\exceptions\NativeCallException;
use greenmeteor\native\exceptions\NativeException;
use yii\base\BaseObject;

/**
 * Default transport: calls the global function that the native shell injects
 * into the embedded PHP runtime (`nativephp_call` by default).
 *
 * By default arguments are passed as an array and the return value is used
 * as-is. Enable {@see $jsonArguments} / {@see $jsonResponse} when the shell
 * speaks JSON strings. A response consisting solely of an `error` object is
 * converted to a {@see NativeCallException}.
 */
class FunctionTransport extends BaseObject implements BridgeTransportInterface
{
    public string $functionName = 'nativephp_call';
    public bool $jsonArguments = false;
    public bool $jsonResponse = false;

    public function isAvailable(): bool
    {
        return function_exists($this->functionName);
    }

    public function send(string $function, array $arguments): mixed
    {
        $callable = $this->functionName;

        try {
            $payload = $this->jsonArguments ? json_encode($arguments, JSON_THROW_ON_ERROR) : $arguments;
            $response = $callable($function, $payload);

            if ($this->jsonResponse && is_string($response)) {
                $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\JsonException $e) {
            throw new NativeException("Invalid JSON in native call {$function}: {$e->getMessage()}", 0, $e);
        }

        if (is_array($response) && array_keys($response) === ['error']) {
            $error = $response['error'];
            throw NativeCallException::fromPayload($function, is_array($error) ? $error : ['message' => (string) $error]);
        }

        return $response;
    }
}
