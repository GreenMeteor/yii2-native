<?php

namespace greenmeteor\native\widgets;

class Image extends Widget
{
    public string $source = '';

    public function getElementType(): string
    {
        return 'image';
    }

    public function init()
    {
        $this->properties['source'] = $this->source;
        parent::init();
    }
}
