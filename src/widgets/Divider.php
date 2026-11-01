<?php

namespace greenmeteor\native\widgets;

class Divider extends Widget
{
    public ?int $thickness = null;
    public ?string $color = null;

    public function getElementType(): string
    {
        return 'divider';
    }

    protected function elementProperties(): array
    {
        return ['thickness' => $this->thickness, 'color' => $this->color];
    }
}
