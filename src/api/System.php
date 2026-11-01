<?php

namespace greenmeteor\native\api;

use greenmeteor\native\runtime\Runtime;

/**
 * Platform information. `isIos()`, `isAndroid()` and `isMobile()` are answered
 * locally from the {@see Runtime}; they never call the native side.
 */
class System extends BaseApi
{
    public ?Runtime $runtime = null;

    public static function apiName(): string
    {
        return 'System';
    }

    public function getPlatform(): ?string
    {
        return $this->runtime?->getPlatform();
    }

    public function isIos(): bool
    {
        return $this->runtime?->isIos() ?? false;
    }

    public function isAndroid(): bool
    {
        return $this->runtime?->isAndroid() ?? false;
    }

    public function isMobile(): bool
    {
        return $this->runtime?->isMobile() ?? false;
    }

    public function openAppSettings(): void
    {
        $this->invoke('openAppSettings');
    }

    public function getLocale(): ?string
    {
        $locale = $this->invoke('getLocale');

        return $locale === null ? null : (string) $locale;
    }

    /** @return array{id?:string,name?:string,version?:string,versionCode?:int} */
    public function getAppInfo(): array
    {
        return (array) ($this->invoke('getAppInfo') ?? []);
    }
}
