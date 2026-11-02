<?php

namespace greenmeteor\native\widgets;

/** Named system icon (SF Symbols on iOS, Material icons on Android). */
class Icon extends Widget
{
    public string $name = '';
    public ?int $size = null;
    public ?string $color = null;

    public function getElementType(): string
    {
        return 'icon';
    }

    protected function elementProperties(): array
    {
        return ['name' => $this->name, 'size' => $this->size, 'color' => $this->color];
    }
}
