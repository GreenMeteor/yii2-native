<?php

namespace greenmeteor\native\api;

class Network extends BaseApi
{
    public static function apiName(): string
    {
        return 'Network';
    }

    /** @return array{connected?:bool,type?:string,isExpensive?:bool} */
    public function getStatus(): array
    {
        return (array) ($this->invoke('getStatus') ?? []);
    }

    public function isConnected(): bool
    {
        return (bool) ($this->getStatus()['connected'] ?? false);
    }
}
