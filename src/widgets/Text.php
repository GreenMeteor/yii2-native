<?php

namespace greenmeteor\native\widgets;

class Text extends Widget
{
    public string $text = '';

    public function getElementType(): string
    {
        return 'text';
    }

    public function init()
    {
        $this->properties['text'] = $this->text;
        parent::init();
    }
}
