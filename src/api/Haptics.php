<?php

namespace greenmeteor\native\api;

class Haptics extends BaseApi
{
    public static function apiName(): string
    {
        return 'Haptics';
    }

    /** @param string $style light|medium|heavy|rigid|soft */
    public function impact(string $style = 'medium'): void
    {
        $this->assertOneOf($style, ['light', 'medium', 'heavy', 'rigid', 'soft'], 'impact style');
        $this->invoke('impact', ['style' => $style]);
    }

    /** @param string $type success|warning|error */
    public function notification(string $type = 'success'): void
    {
        $this->assertOneOf($type, ['success', 'warning', 'error'], 'notification type');
        $this->invoke('notification', ['type' => $type]);
    }

    public function selection(): void
    {
        $this->invoke('selection');
    }
}
