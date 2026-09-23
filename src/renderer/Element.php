<?php

namespace greenmeteor\native\renderer;

use yii\base\Arrayable;
use yii\base\BaseObject;

class Element extends BaseObject implements Arrayable
{
    public string $type = 'view';
    public array $properties = [];
    public array $children = [];

    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        return [
            'type' => $this->type,
            'properties' => $this->properties,
            'children' => array_map(
                static fn ($child) => $child instanceof Arrayable
                    ? $child->toArray([], [], true)
                    : $child,
                $this->children
            ),
        ];
    }

    public function addChild($child): static
    {
        $this->children[] = $child;
        return $this;
    }
}
