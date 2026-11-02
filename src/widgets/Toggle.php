<?php

namespace greenmeteor\native\widgets;

class Toggle extends Widget
{
    public string $name = '';
    public ?string $label = null;
    public bool $value = false;
    public ?string $action = null;

    public function getElementType(): string
    {
        return 'toggle';
    }

    protected function elementProperties(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'value' => $this->value,
            'action' => $this->action,
        ];
    }
}
