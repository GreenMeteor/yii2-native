<?php

namespace greenmeteor\native\widgets;

use greenmeteor\native\renderer\Element;
use greenmeteor\native\renderer\Renderer;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Widget as YiiWidget;

/**
 * Base class for native UI widgets.
 *
 * Widgets build a framework-neutral {@see Element} tree. Compose them with
 * {@see make()}, which returns widget *instances* that can be nested:
 *
 * ```php
 * return VStack::make(['spacing' => 12], [
 *     Text::make(['text' => 'Hello']),
 *     Button::make(['text' => 'Continue', 'action' => 'continue']),
 * ]);
 * ```
 *
 * Yii's static `widget()` still works but, as for every Yii widget, returns the
 * rendered output: here the JSON frame of that widget, which cannot be nested.
 *
 * Properties shared by all widgets (`padding`, `margin`, `background`,
 * `width`, `height`, `visible`, `accessibilityLabel`, `testId`) are only sent
 * when set. `$properties` can override or add raw protocol properties.
 */
abstract class Widget extends YiiWidget
{
    /** Raw protocol properties; they override computed ones. */
    public array $properties = [];
    /** @var array<int,Widget|Element|null> */
    public array $children = [];

    /** @var int|float|array|null number, [vertical, horizontal] or [top, right, bottom, left] */
    public $padding;
    /** @var int|float|array|null same shapes as $padding */
    public $margin;
    public ?string $background = null;
    /** @var int|float|string|null number of points or "fill" */
    public $width;
    /** @var int|float|string|null number of points or "fill" */
    public $height;
    public ?bool $visible = null;
    public ?string $accessibilityLabel = null;
    public ?string $testId = null;

    abstract public function getElementType(): string;

    /**
     * Widget-specific protocol properties. Null values are dropped.
     *
     * @return array<string,mixed>
     */
    protected function elementProperties(): array
    {
        return [];
    }

    /**
     * Create a widget instance without running it, optionally with children.
     * Nested lists (e.g. from `array_map`) are flattened.
     *
     * @param array $config widget properties
     * @param array $children Widget|Element|null, or lists of them
     */
    public static function make(array $config = [], array $children = []): static
    {
        $config['class'] = static::class;
        /** @var static $widget */
        $widget = Yii::createObject($config);

        return $widget->add(...$children);
    }

    /** Append children. Lists are flattened; null and false are ignored. */
    public function add(...$children): static
    {
        foreach ($children as $child) {
            if (is_array($child)) {
                $this->add(...$child);
            } elseif ($child !== null && $child !== false) {
                $this->children[] = $child;
            }
        }

        return $this;
    }

    /** Append a single child (kept for backward compatibility; see {@see add()}). */
    public function child($child): static
    {
        return $this->add($child);
    }

    /** @return array<string,mixed> all protocol properties for this widget */
    public function getProperties(): array
    {
        $common = [
            'padding' => $this->padding,
            'margin' => $this->margin,
            'background' => $this->background,
            'width' => $this->width,
            'height' => $this->height,
            'visible' => $this->visible,
            'accessibilityLabel' => $this->accessibilityLabel,
            'testId' => $this->testId,
            'id' => $this->getId(false),
        ];

        return array_replace(
            $this->prune($this->elementProperties()),
            $this->prune($common),
            $this->properties
        );
    }

    /** Build the native element tree without invoking Yii's output lifecycle. */
    public function getElement(): Element
    {
        $element = new Element([
            'type' => $this->getElementType(),
            'properties' => $this->getProperties(),
        ]);

        foreach ($this->children as $child) {
            if ($child === null || $child === false) {
                continue;
            }
            if ($child instanceof Element) {
                $element->addChild($child);
            } elseif ($child instanceof self) {
                $element->addChild($child->getElement());
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Native widget children must be Widget or Element instances, %s given.',
                    get_debug_type($child)
                ));
            }
        }

        return $element;
    }

    /** Yii widgets must return renderable string output from run(). */
    public function run(): string
    {
        return (new Renderer())->encode($this->getElement());
    }

    private function prune(array $values): array
    {
        return array_filter($values, static fn ($v) => $v !== null);
    }
}
