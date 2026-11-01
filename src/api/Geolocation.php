<?php

namespace greenmeteor\native\api;

class Geolocation extends BaseApi
{
    public static function apiName(): string
    {
        return 'Geolocation';
    }

    /**
     * Result: `NativeEvents::LOCATION_RECEIVED`.
     *
     * @return string request id
     */
    public function getCurrentPosition(bool $highAccuracy = false, ?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('getCurrentPosition', ['highAccuracy' => $highAccuracy, 'id' => $id]);

        return $id;
    }

    /** @return array{status?:string} */
    public function checkPermissions(): array
    {
        return (array) ($this->invoke('checkPermissions') ?? []);
    }

    /**
     * Result: `NativeEvents::LOCATION_PERMISSION_RESULT`.
     *
     * @return string request id
     */
    public function requestPermissions(?string $id = null): string
    {
        $id = $this->requestId($id);
        $this->invoke('requestPermissions', ['id' => $id]);

        return $id;
    }
}
