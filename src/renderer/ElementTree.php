<?php

namespace greenmeteor\native\renderer;

class ElementTree
{
    public function __construct(
        public readonly Element $root
    ) {
    }

    public function toArray(): array
    {
        return $this->root->toArray([], [], true);
    }
}
