<?php

namespace greenmeteor\native\api;

use yii\base\InvalidArgumentException;

class Device extends BaseApi
{
    public static function apiName(): string
    {
        return 'Device';
    }

    public function getId(): ?string
    {
        $id = $this->invoke('getId');

        return $id === null ? null : (string) $id;
    }

    public function getInfo(): array
    {
        return (array) ($this->invoke('getInfo') ?? []);
    }

    /** @return array{level?:float,isCharging?:bool} */
    public function getBattery(): array
    {
        return (array) ($this->invoke('getBattery') ?? []);
    }

    /** @param int $duration milliseconds, 1..10000 */
    public function vibrate(int $duration = 100): void
    {
        if ($duration < 1 || $duration > 10000) {
            throw new InvalidArgumentException('Vibration duration must be between 1 and 10000 milliseconds.');
        }
        $this->invoke('vibrate', ['duration' => $duration]);
    }

    /** @return bool new flashlight state */
    public function toggleFlashlight(): bool
    {
        $result = (array) ($this->invoke('toggleFlashlight') ?? []);

        return (bool) ($result['enabled'] ?? false);
    }
}
