<?php

namespace greenmeteor\native\widgets;

class ScrollView extends Widget
{
    public ?bool $horizontal = null;
    public ?bool $showsIndicator = null;

    public function getElementType(): string
    {
        return 'scroll';
    }

    protected function elementProperties(): array
    {
        return [
            'horizontal' => $this->horizontal,
            'showsIndicator' => $this->showsIndicator,
        ];
    }
}
