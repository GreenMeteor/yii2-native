<?php

namespace greenmeteor\native\runtime;

use yii\base\Component;

/**
 * Describes whether the application is running inside the native shell.
 *
 * The runtime is "available" when it was started explicitly with
 * {@see start()}, or (with {@see $autoDetect}) when the shell exposes the
 * native call function or sets the `NATIVE_RUNTIME` environment variable.
 */
class Runtime extends Component
{
    public const EVENT_START = 'start';
    public const EVENT_STOP = 'stop';

    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_ANDROID = 'android';

    public bool $available = false;
    public bool $persistent = true;

    /** Detect the native shell from the environment. */
    public bool $autoDetect = true;
    /** Name of the global function the shell injects into the embedded PHP. */
    public string $callFunction = 'nativephp_call';
    /** Environment variable the shell sets to `1` / `true` when embedding PHP. */
    public string $runtimeEnvVar = 'NATIVE_RUNTIME';
    /** Environment variable holding `ios` or `android`. */
    public string $platformEnvVar = 'NATIVE_PLATFORM';
    /** Force a platform (useful for tests and previews). */
    public ?string $platform = null;

    public function isAvailable(): bool
    {
        return $this->available || ($this->autoDetect && $this->detect());
    }

    /** Whether the environment looks like the native shell, ignoring {@see $available}. */
    public function detect(): bool
    {
        if (function_exists($this->callFunction)) {
            return true;
        }

        return in_array(strtolower((string) $this->env($this->runtimeEnvVar)), ['1', 'true', 'yes'], true);
    }

    /** @return string|null `ios`, `android`, or null when not running natively / unknown. */
    public function getPlatform(): ?string
    {
        $platform = $this->platform ?? $this->env($this->platformEnvVar);
        $platform = strtolower(trim((string) $platform));

        return in_array($platform, [self::PLATFORM_IOS, self::PLATFORM_ANDROID], true) ? $platform : null;
    }

    public function isIos(): bool
    {
        return $this->getPlatform() === self::PLATFORM_IOS;
    }

    public function isAndroid(): bool
    {
        return $this->getPlatform() === self::PLATFORM_ANDROID;
    }

    public function isMobile(): bool
    {
        return $this->getPlatform() !== null;
    }

    public function start(): void
    {
        $this->available = true;
        $this->trigger(self::EVENT_START);
    }

    public function stop(): void
    {
        $this->available = false;
        $this->trigger(self::EVENT_STOP);
    }

    private function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return $value === false || $value === null ? null : (string) $value;
    }
}
