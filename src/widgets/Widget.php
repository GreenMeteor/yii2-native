<?php

namespace greenmeteor\native\widgets;

use greenmeteor\native\renderer\Element;
use yii\base\Widget as YiiWidget;

abstract class Widget extends YiiWidget
{
    public array $properties = [];
    public array $children = [];

    abstract public function getElementType(): string;

    public function init()
    {
        parent::init();

        if (!isset($this->properties['id'])) {
            $this->properties['id'] = static::class . ':' . spl_object_id($this);
        }
    }

    public function run(): Element
    {
        $element = new Element([
            'type' => $this->getElementType(),
            'properties' => $this->properties,
        ]);

        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                $element->children[] = $child;
            } elseif ($child instanceof self) {
                $element->children[] = $child->run();
            }
        }

        return $element;
    }

    public function child($child): static
    {
        $this->children[] = $child;
        return $this;
    }
}
