<?php

namespace greenmeteor\native\widgets;

/** Virtualised vertical list; the shell only lays out visible children. */
class LazyList extends Widget
{
    public ?int $itemSpacing = null;

    public function getElementType(): string
    {
        return 'list';
    }

    protected function elementProperties(): array
    {
        return ['itemSpacing' => $this->itemSpacing];
    }
}
