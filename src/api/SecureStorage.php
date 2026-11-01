<?php

namespace greenmeteor\native\api;

use yii\base\InvalidArgumentException;

/** Keychain (iOS) / Keystore-backed (Android) key-value storage. */
class SecureStorage extends BaseApi
{
    public static function apiName(): string
    {
        return 'SecureStorage';
    }

    public function set(string $key, string $value): void
    {
        $this->assertKey($key);
        $this->invoke('set', ['key' => $key, 'value' => $value]);
    }

    public function get(string $key): ?string
    {
        $this->assertKey($key);
        $value = $this->invoke('get', ['key' => $key]);

        return $value === null ? null : (string) $value;
    }

    public function delete(string $key): void
    {
        $this->assertKey($key);
        $this->invoke('delete', ['key' => $key]);
    }

    private function assertKey(string $key): void
    {
        if ($key === '' || strlen($key) > 256) {
            throw new InvalidArgumentException('Secure storage keys must be 1-256 characters.');
        }
    }
}
