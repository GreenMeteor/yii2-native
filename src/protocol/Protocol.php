<?php

namespace greenmeteor\native\protocol;

use yii\base\InvalidConfigException;

/**
 * Reads `native/protocol.json`: the contract shared between the PHP package
 * and the iOS / Android shells (UI node types, bridge functions, events).
 *
 * Plugins extend an instance at runtime via {@see registerFunction()},
 * {@see registerNode()} and {@see registerEvent()}.
 */
final class Protocol
{
    /** Element-tree protocol version. Additive changes do not bump it. */
    public const VERSION = 1;

    private static ?array $raw = null;
    private array $data;

    public function __construct(?array $data = null)
    {
        $this->data = $data ?? self::loadDefault();
        $this->data['nodes'] ??= [];
        $this->data['events'] ??= [];
        $this->data['bridge']['functions'] ??= [];
    }

    public static function defaultPath(): string
    {
        return dirname(__DIR__, 2) . '/native/protocol.json';
    }

    private static function loadDefault(): array
    {
        if (self::$raw === null) {
            $path = self::defaultPath();
            $json = is_file($path) ? file_get_contents($path) : false;
            if ($json === false) {
                throw new InvalidConfigException("Native protocol file not found at {$path}.");
            }
            try {
                self::$raw = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidConfigException("Native protocol file is not valid JSON: {$e->getMessage()}", 0, $e);
            }
        }

        return self::$raw;
    }

    public function version(): int
    {
        return (int) ($this->data['version'] ?? self::VERSION);
    }

    public function bridgeVersion(): int
    {
        return (int) ($this->data['bridge']['version'] ?? 1);
    }

    /** @return string[] */
    public function commonProperties(): array
    {
        return $this->data['commonProperties'] ?? [];
    }

    /** @return string[] */
    public function nodeTypes(): array
    {
        return array_keys($this->data['nodes']);
    }

    public function hasNode(string $type): bool
    {
        return isset($this->data['nodes'][$type]);
    }

    public function getNode(string $type): ?array
    {
        return $this->data['nodes'][$type] ?? null;
    }

    public function registerNode(string $type, array $spec = []): void
    {
        $this->data['nodes'][$type] = $spec;
    }

    /** @return array<string,array> */
    public function functions(): array
    {
        return $this->data['bridge']['functions'];
    }

    public function hasFunction(string $name): bool
    {
        return isset($this->data['bridge']['functions'][$name]);
    }

    public function getFunction(string $name): ?array
    {
        return $this->data['bridge']['functions'][$name] ?? null;
    }

    public function registerFunction(string $name, array $spec = []): void
    {
        // `params` is deliberately not defaulted: a function without a params
        // spec is not argument-validated. Declare `params` to opt in.
        $this->data['bridge']['functions'][$name] = $spec + [
            'description' => '',
            'interactive' => false,
            'permissions' => [],
        ];
    }

    /** @return string[] permissions required to call `$name` (empty for unknown functions). */
    public function permissionsFor(string $name): array
    {
        return array_values($this->data['bridge']['functions'][$name]['permissions'] ?? []);
    }

    public function isInteractive(string $name): bool
    {
        return (bool) ($this->data['bridge']['functions'][$name]['interactive'] ?? false);
    }

    /** @return string[] */
    public function eventNames(): array
    {
        return array_keys($this->data['events']);
    }

    public function hasEvent(string $name): bool
    {
        return isset($this->data['events'][$name]);
    }

    public function registerEvent(string $name, array $spec = []): void
    {
        $this->data['events'][$name] = $spec;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
