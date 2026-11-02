<?php

namespace greenmeteor\native\bridge;

use yii\base\BaseObject;

/**
 * In-memory transport for tests and local development without a device.
 *
 * ```php
 * $fake = Yii::$app->native->fake(['Device.getId' => 'abc']);
 * Yii::$app->native->device->getId();
 * $fake->assertCalled('Device.getId');
 * ```
 *
 * Here `getId()` returns `'abc'` and `assertCalled()` passes.
 *
 * A response may be a value, a `callable (array $arguments, string $function)`,
 * or a `Throwable` (thrown when the function is called). Unlisted functions
 * return null.
 */
class FakeTransport extends BaseObject implements BridgeTransportInterface
{
    public bool $available = true;
    /** @var array<string,mixed> */
    public array $responses = [];
    /** @var array<int,array{function:string,arguments:array}> */
    public array $calls = [];

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function send(string $function, array $arguments): mixed
    {
        $this->calls[] = ['function' => $function, 'arguments' => $arguments];
        $response = $this->responses[$function] ?? null;

        if ($response instanceof \Throwable) {
            throw $response;
        }
        if (is_callable($response)) {
            return $response($arguments, $function);
        }

        return $response;
    }

    /** @return array<int,array> argument lists of every call to `$function`. */
    public function callsTo(string $function): array
    {
        return array_values(array_map(
            static fn (array $c) => $c['arguments'],
            array_filter($this->calls, static fn (array $c) => $c['function'] === $function)
        ));
    }

    public function wasCalled(string $function): bool
    {
        return $this->callsTo($function) !== [];
    }

    public function lastCall(): ?array
    {
        return $this->calls ? $this->calls[array_key_last($this->calls)] : null;
    }

    /**
     * @param callable|null $matcher receives the argument list; must return true for at least one call.
     * @throws \AssertionError
     */
    public function assertCalled(string $function, ?callable $matcher = null): void
    {
        $calls = $this->callsTo($function);
        if (!$calls) {
            throw new \AssertionError("Expected native function {$function} to be called, but it was not.");
        }
        if ($matcher !== null && !array_filter($calls, $matcher)) {
            throw new \AssertionError("{$function} was called, but no call matched the expectation. Calls: "
                . json_encode($calls));
        }
    }

    public function assertNotCalled(string $function): void
    {
        if ($this->wasCalled($function)) {
            throw new \AssertionError("Expected native function {$function} not to be called.");
        }
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
