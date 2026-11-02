<?php

namespace greenmeteor\native\widgets;

/** Vertical stack. */
class VStack extends Widget
{
    public ?int $spacing = null;
    /** start|center|end|stretch (cross axis) */
    public ?string $align = null;
    /** start|center|end|space-between|space-around (main axis) */
    public ?string $justify = null;

    public function getElementType(): string
    {
        return 'vstack';
    }

    protected function elementProperties(): array
    {
        return [
            'spacing' => $this->spacing,
            'align' => $this->align,
            'justify' => $this->justify,
        ];
    }
}
