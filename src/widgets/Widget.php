<?php

namespace greenmeteor\native\widgets;

use greenmeteor\native\renderer\Element;
use greenmeteor\native\renderer\Renderer;
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

    /** Build the native element tree without invoking Yii's output lifecycle. */
    public function getElement(): Element
    {
        $element = new Element([
            'type' => $this->getElementType(),
            'properties' => $this->properties,
        ]);

        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                $element->addChild($child);
            } elseif ($child instanceof self) {
                $element->addChild($child->getElement());
            } elseif (is_array($child)) {
                // Arrays are not implicitly converted into native elements.
                // Require callers to pass a widget or Element so malformed
                // protocol trees fail early and predictably.
                throw new \InvalidArgumentException(
                    'Native widget children must be Widget or Element instances.'
                );
            } else {
                throw new \InvalidArgumentException(
                    'Native widget children must be Widget or Element instances.'
                );
            }
        }

        return $element;
    }

    /** Yii widgets must return renderable string output from run(). */
    public function run(): string
    {
        return (new Renderer())->encode($this->getElement());
    }

    public function child($child): static
    {
        $this->children[] = $child;
        return $this;
    }
}
