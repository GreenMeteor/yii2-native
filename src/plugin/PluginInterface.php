<?php

namespace greenmeteor\native\plugin;

interface PluginInterface
{
    public function getId(): string;

    public function getManifest(): array;
}
