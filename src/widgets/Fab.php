<?php

namespace greenmeteor\native\widgets;

/** Floating action button. */
class Fab extends Widget
{
    public string $icon = 'add';
    public ?string $action = null;
    public ?string $label = null;

    public function getElementType(): string
    {
        return 'fab';
    }

    protected function elementProperties(): array
    {
        return ['icon' => $this->icon, 'action' => $this->action, 'label' => $this->label];
    }
}
