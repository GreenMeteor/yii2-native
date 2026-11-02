<?php

namespace greenmeteor\native\widgets;

class Text extends Widget
{
    public string $text = '';
    public ?int $size = null;
    /** normal|medium|semibold|bold */
    public ?string $weight = null;
    public ?string $color = null;
    /** start|center|end */
    public ?string $align = null;
    public ?int $maxLines = null;

    public function getElementType(): string
    {
        return 'text';
    }

    protected function elementProperties(): array
    {
        return [
            'text' => $this->text,
            'size' => $this->size,
            'weight' => $this->weight,
            'color' => $this->color,
            'align' => $this->align,
            'maxLines' => $this->maxLines,
        ];
    }
}
