<?php

namespace greenmeteor\native\widgets;

/** Bottom navigation bar; children must be {@see BottomNavItem}. */
class BottomNav extends Widget
{
    /** Id of the active item. */
    public ?string $activeId = null;

    public function getElementType(): string
    {
        return 'bottomnav';
    }

    protected function elementProperties(): array
    {
        return ['activeId' => $this->activeId];
    }
}
