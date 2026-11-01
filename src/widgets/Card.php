<?php

namespace greenmeteor\native\widgets;

class Card extends Widget
{
    public ?int $elevation = null;
    public ?int $cornerRadius = null;

    public function getElementType(): string
    {
        return 'card';
    }

    protected function elementProperties(): array
    {
        return ['elevation' => $this->elevation, 'cornerRadius' => $this->cornerRadius];
    }
}
