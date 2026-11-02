<?php

namespace greenmeteor\native\widgets;

/** Indeterminate progress indicator. */
class Loading extends Widget
{
    public ?int $size = null;
    public ?string $message = null;

    public function getElementType(): string
    {
        return 'loading';
    }

    protected function elementProperties(): array
    {
        return ['size' => $this->size, 'message' => $this->message];
    }
}
