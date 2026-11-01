<?php

namespace greenmeteor\native\api;

/** Face ID / Touch ID / Android biometrics. Result: `NativeEvents::BIOMETRIC_COMPLETED`. */
class Biometrics extends BaseApi
{
    public static function apiName(): string
    {
        return 'Biometrics';
    }

    public function isAvailable(): bool
    {
        return (bool) $this->invoke('isAvailable');
    }

    /** @return string request id */
    public function prompt(?string $reason = null, ?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('prompt', $this->args(['reason' => $reason, 'id' => $id]));

        return $id;
    }
}
