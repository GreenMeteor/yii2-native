<?php

namespace greenmeteor\native\widgets;

/** Emits a `nativeAction` event with `$action` (and `$payload`) when tapped. */
class Button extends Widget
{
    public string $text = '';
    public ?string $action = null;
    public ?array $payload = null;
    /** filled|outlined|text */
    public ?string $variant = null;
    public ?bool $disabled = null;
    public ?string $icon = null;

    public function getElementType(): string
    {
        return 'button';
    }

    protected function elementProperties(): array
    {
        return [
            'text' => $this->text,
            'action' => $this->action,
            'payload' => $this->payload,
            'variant' => $this->variant,
            'disabled' => $this->disabled,
            'icon' => $this->icon,
        ];
    }
}
