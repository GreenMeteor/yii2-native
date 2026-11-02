<?php

namespace greenmeteor\native\widgets;

class Image extends Widget
{
    public string $source = '';
    public ?string $alt = null;
    /** fit|fill|cover */
    public ?string $contentMode = null;
    public ?int $cornerRadius = null;

    public function getElementType(): string
    {
        return 'image';
    }

    protected function elementProperties(): array
    {
        return [
            'source' => $this->source,
            'alt' => $this->alt,
            'contentMode' => $this->contentMode,
            'cornerRadius' => $this->cornerRadius,
        ];
    }
}
