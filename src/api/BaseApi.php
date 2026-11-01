<?php

namespace greenmeteor\native\api;

use greenmeteor\native\bridge\NativeBridge;
use yii\base\BaseObject;
use yii\base\InvalidArgumentException;

/**
 * Base class for the typed device APIs exposed on `Yii::$app->native`.
 *
 * Interactive calls (camera, scanner, dialogs, ...) return immediately with a
 * request id; the result arrives later as a native event carrying the same id.
 */
abstract class BaseApi extends BaseObject
{
    public NativeBridge $bridge;

    /** Bridge namespace, e.g. `Camera` for `Camera.getPhoto`. */
    abstract public static function apiName(): string;

    protected function invoke(string $method, array $arguments = [])
    {
        return $this->bridge->call(static::apiName() . '.' . $method, $arguments);
    }

    /** Use the caller's id or generate one. */
    protected function requestId(?string $id): string
    {
        return $id ?? bin2hex(random_bytes(8));
    }

    /** Drop null values so optional arguments are omitted from the call. */
    protected function args(array $arguments): array
    {
        return array_filter($arguments, static fn ($v) => $v !== null);
    }

    protected function assertHttpUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || parse_url($url, PHP_URL_HOST) === null) {
            throw new InvalidArgumentException('Only absolute http(s) URLs are allowed.');
        }
    }

    protected function assertPath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Invalid file path.');
        }
    }

    protected function assertOneOf(string $value, array $allowed, string $label): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Invalid {$label} '{$value}'. Allowed: " . implode(', ', $allowed) . '.');
        }
    }
}
