<?php

namespace greenmeteor\native\widgets;

/** Children drawn on top of each other. */
class ZStack extends Widget
{
    /** Alignment of children: center|top-start|top-end|bottom-start|bottom-end ... */
    public ?string $align = null;

    public function getElementType(): string
    {
        return 'zstack';
    }

    protected function elementProperties(): array
    {
        return ['align' => $this->align];
    }
}
