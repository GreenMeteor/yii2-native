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

    public function find(string $id): ?Element
    {
        return $this->root->find($id);
    }

    public function count(): int
    {
        return $this->root->count();
    }
}
