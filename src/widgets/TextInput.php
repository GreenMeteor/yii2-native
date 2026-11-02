<?php

namespace greenmeteor\native\widgets;

/**
 * Text field. `$action` fires on submit, `$onChange` on every edit; both are
 * delivered as `nativeAction` events with the current `value`.
 */
class TextInput extends Widget
{
    public string $name = '';
    public ?string $value = null;
    public ?string $placeholder = null;
    /** text|email|number|phone|url */
    public ?string $keyboard = null;
    public ?bool $secure = null;
    public ?bool $multiline = null;
    public ?string $action = null;
    public ?string $onChange = null;

    public function getElementType(): string
    {
        return 'input';
    }

    protected function elementProperties(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'placeholder' => $this->placeholder,
            'keyboard' => $this->keyboard,
            'secure' => $this->secure,
            'multiline' => $this->multiline,
            'action' => $this->action,
            'onChange' => $this->onChange,
        ];
    }
}
