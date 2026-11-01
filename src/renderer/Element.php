<?php

namespace greenmeteor\native\renderer;

use yii\base\Arrayable;
use yii\base\BaseObject;

/**
 * A node of the native element tree.
 */
class Element extends BaseObject implements Arrayable, \JsonSerializable
{
    public string $type = 'view';
    public array $properties = [];
    /** @var array<int,Element|Arrayable> */
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
        $available = $this->fields();
        $requested = $fields === [] ? $available : $fields;
        $result = [];

        foreach ($requested as $key => $field) {
            if (is_int($key)) {
                $key = $field;
            }
            if (!in_array($field, $available, true)) {
                continue;
            }

            $value = $this->{$field};
            if ($field === 'children' && $recursive) {
                $value = array_map(static function ($child) {
                    return $child instanceof Arrayable ? $child->toArray([], [], true) : $child;
                }, $value);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function addChild($child): static
    {
        $this->children[] = $child;

        return $this;
    }

    /** Depth-first search by `properties['id']`. */
    public function find(string $id): ?Element
    {
        if (($this->properties['id'] ?? null) === $id) {
            return $this;
        }
        foreach ($this->children as $child) {
            if ($child instanceof self && ($found = $child->find($id)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Visit this element and all descendants depth-first.
     *
     * @param callable $callback `function (Element $element, int $depth): void`
     */
    public function walk(callable $callback, int $depth = 0): void
    {
        $callback($this, $depth);
        foreach ($this->children as $child) {
            if ($child instanceof self) {
                $child->walk($callback, $depth + 1);
            }
        }
    }

    /** Number of elements in this subtree, including this one. */
    public function count(): int
    {
        $n = 0;
        $this->walk(static function () use (&$n) {
            $n++;
        });

        return $n;
    }
}
