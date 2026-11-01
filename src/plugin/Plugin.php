<?php

namespace greenmeteor\native\plugin;

use greenmeteor\native\Native;
use yii\base\BaseObject;

/**
 * Manifest-backed plugin. Extend it and override {@see handlers()} to provide
 * PHP-side bridge functions, or override {@see boot()} for custom wiring.
 */
class Plugin extends BaseObject implements PluginInterface, BootablePluginInterface
{
    public string $id = '';
    public array $manifest = [];

    public function getId(): string
    {
        return $this->id;
    }

    public function getManifest(): array
    {
        return $this->manifest;
    }

    /**
     * PHP implementations of this plugin's bridge functions.
     *
     * @return array<string,callable> function name => `function (array $arguments, string $function)`
     */
    public function handlers(): array
    {
        return [];
    }

    /**
     * Registers the manifest's bridge functions, element types, events and
     * permissions with the native component, plus any {@see handlers()}.
     */
    public function boot(Native $native): void
    {
        $protocol = $native->getProtocol();

        foreach ((array) ($this->manifest['bridge']['functions'] ?? []) as $name => $spec) {
            $protocol->registerFunction($name, (array) $spec);
        }
        foreach ((array) ($this->manifest['elements'] ?? []) as $type => $spec) {
            $protocol->registerNode($type, (array) $spec);
        }
        foreach ((array) ($this->manifest['events'] ?? []) as $key => $value) {
            is_int($key) ? $protocol->registerEvent((string) $value) : $protocol->registerEvent($key, (array) $value);
        }
        if (!empty($this->manifest['permissions'])) {
            $native->getPermissionManager()->merge((array) $this->manifest['permissions']);
        }
        foreach ($this->handlers() as $function => $handler) {
            $protocol->hasFunction($function) || $protocol->registerFunction($function);
            $native->getBridge()->register($function, $handler);
        }
    }
}
