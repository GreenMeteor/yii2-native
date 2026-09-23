<?php

namespace greenmeteor\native\widgets;

class Button extends Widget
{
    public string $text = '';
    public ?string $action = null;

    public function getElementType(): string
    {
        return 'button';
    }

    public function init()
    {
        $this->properties['text'] = $this->text;

        if ($this->action !== null) {
            $this->properties['action'] = $this->action;
        }

        parent::init();
    }
}
