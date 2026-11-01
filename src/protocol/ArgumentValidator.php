<?php

namespace greenmeteor\native\protocol;

use yii\base\InvalidArgumentException;

/**
 * Validates bridge call arguments against the `params` spec of a function in
 * protocol.json: required parameters, unknown parameters, types, `enum` and
 * `format` (`http-url`, `path`).
 *
 * Supported types: string, integer, number, boolean, array (list), object (map).
 * Applied by the bridge, so it protects PHP callers, the HTTP endpoint and
 * plugins alike.
 */
final class ArgumentValidator
{
    private const MAX_STRING_BYTES = 1048576;

    /** @throws InvalidArgumentException */
    public static function validate(string $function, array $spec, array $arguments): void
    {
        $params = $spec['params'] ?? [];

        foreach ($arguments as $name => $_) {
            if (!is_string($name) || !isset($params[$name])) {
                throw new InvalidArgumentException("{$function}: unknown parameter '" . (string) $name . "'.");
            }
        }

        foreach ($params as $name => $def) {
            if (!array_key_exists($name, $arguments)) {
                if (!empty($def['required'])) {
                    throw new InvalidArgumentException("{$function}: missing required parameter '{$name}'.");
                }
                continue;
            }
            self::check($function, $name, $def, $arguments[$name]);
        }
    }

    private static function check(string $function, string $name, array $def, mixed $value): void
    {
        $type = $def['type'] ?? null;
        $ok = match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value),
            default => true,
        };
        if (!$ok) {
            throw new InvalidArgumentException("{$function}: parameter '{$name}' must be of type {$type}, " . get_debug_type($value) . ' given.');
        }

        if (is_string($value)) {
            if (strlen($value) > self::MAX_STRING_BYTES) {
                throw new InvalidArgumentException("{$function}: parameter '{$name}' is too long.");
            }
            if (isset($def['enum']) && !in_array($value, $def['enum'], true)) {
                throw new InvalidArgumentException("{$function}: parameter '{$name}' must be one of: " . implode(', ', $def['enum']) . '.');
            }
            match ($def['format'] ?? null) {
                'http-url' => self::assertHttpUrl($function, $name, $value),
                'path' => self::assertPath($function, $name, $value),
                default => null,
            };
        }
    }

    private static function assertHttpUrl(string $function, string $name, string $value): void
    {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || parse_url($value, PHP_URL_HOST) === null) {
            throw new InvalidArgumentException("{$function}: parameter '{$name}' must be an absolute http(s) URL.");
        }
    }

    private static function assertPath(string $function, string $name, string $value): void
    {
        if ($value === '' || str_contains($value, "\0")) {
            throw new InvalidArgumentException("{$function}: parameter '{$name}' is not a valid path.");
        }
    }
}
