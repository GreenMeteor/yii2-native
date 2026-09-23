<?php

namespace greenmeteor\native\runtime;

use yii\base\Component;

class Runtime extends Component
{
    public bool $available = false;
    public bool $persistent = true;

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function start(): void
    {
        $this->available = true;
    }

    public function stop(): void
    {
        $this->available = false;
    }
}
