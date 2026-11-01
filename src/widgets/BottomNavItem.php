<?php

namespace greenmeteor\native\widgets;

class BottomNavItem extends Widget
{
    public string $label = '';
    public ?string $icon = null;
    public ?string $action = null;
    public ?bool $active = null;
    public ?string $badge = null;

    public function getElementType(): string
    {
        return 'navitem';
    }

    protected function elementProperties(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'action' => $this->action,
            'active' => $this->active,
            'badge' => $this->badge,
        ];
    }
}
