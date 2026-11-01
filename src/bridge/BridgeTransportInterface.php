<?php

namespace greenmeteor\native\bridge;

/**
 * Carries a named function call to the native shell.
 */
interface BridgeTransportInterface
{
    /** Whether the transport can currently reach the native side. */
    public function isAvailable(): bool;

    /**
     * @param array $arguments JSON-serialisable arguments.
     * @return mixed Decoded result.
     * @throws \greenmeteor\native\exceptions\NativeException on failure.
     */
    public function send(string $function, array $arguments): mixed;
}
