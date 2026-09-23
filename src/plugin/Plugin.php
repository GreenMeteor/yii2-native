<?php

namespace greenmeteor\native\plugin;

use yii\base\BaseObject;

class Plugin extends BaseObject implements PluginInterface
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
}
