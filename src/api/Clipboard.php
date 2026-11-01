<?php

namespace greenmeteor\native\api;

class Clipboard extends BaseApi
{
    public static function apiName(): string
    {
        return 'Clipboard';
    }

    public function copy(string $text): void
    {
        $this->invoke('copy', ['text' => $text]);
    }

    public function paste(): ?string
    {
        $value = $this->invoke('paste');

        return $value === null ? null : (string) $value;
    }

    public function clear(): void
    {
        $this->invoke('clear');
    }
}
