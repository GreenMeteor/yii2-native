<?php

namespace greenmeteor\native\widgets;

/** Fixed space when `$size` is set, otherwise flexible space. */
class Spacer extends Widget
{
    public ?int $size = null;

    public function getElementType(): string
    {
        return 'spacer';
    }

    protected function elementProperties(): array
    {
        return ['size' => $this->size];
    }
}
