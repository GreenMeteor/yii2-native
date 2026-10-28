<?php

namespace greenmeteor\native\renderer;

use yii\base\Arrayable;
use yii\base\BaseObject;

class Element extends BaseObject implements Arrayable
{
    public string $type = 'view';
    public array $properties = [];
    public array $children = [];

    public function fields(): array
    {
        return ['type', 'properties', 'children'];
    }

    public function extraFields(): array
    {
        return [];
    }

    public function toArray(array $fields = [], array $expand = [], $recursive = true): array
    {
        $availableFields = $fields === [] ? $this->fields() : $fields;
        $result = [];

        foreach ($availableFields as $key => $field) {
            if (is_int($key)) {
                $key = $field;
            }

            if (!in_array($field, $this->fields(), true)) {
                continue;
            }

            $value = $this->{$field};
            if ($field === 'children' && $recursive) {
                $value = array_map(static function ($child) {
                    if ($child instanceof Arrayable) {
                        return $child->toArray([], [], true);
                    }
                    return $child;
                }, $value);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function addChild($child): static
    {
        $this->children[] = $child;
        return $this;
    }
}
