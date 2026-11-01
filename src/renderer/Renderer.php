<?php

namespace greenmeteor\native\renderer;

use greenmeteor\native\exceptions\NativeException;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\widgets\Widget;
use yii\base\Component;

/**
 * Turns a widget / element tree into a protocol frame.
 *
 * A frame is `{"version": 1, "root": {...}}` plus an optional `meta` object.
 * Elements without an explicit `id` receive a deterministic, path-based id
 * (`root`, `root.0`, `root.0.1`, ...) so the shell can diff consecutive frames.
 */
class Renderer extends Component
{
    public const PROTOCOL_VERSION = Protocol::VERSION;

    private const MAX_DEPTH = 64;

    public ?Protocol $protocol = null;
    /** Throw when the tree contains unknown node types. */
    public bool $strict = false;
    /** Assign path-based ids to elements that have none. */
    public bool $assignIds = true;

    public function render(Element|Widget $root, array $meta = []): array
    {
        $element = $this->toElement($root);

        if ($this->strict && ($errors = $this->validate($element))) {
            throw new NativeException('Invalid native element tree: ' . implode(' ', $errors));
        }

        $tree = $element->toArray([], [], true);
        if ($this->assignIds) {
            $this->assignIdsTo($tree, 'root');
        }

        $frame = [
            'version' => self::PROTOCOL_VERSION,
            'root' => $tree,
        ];
        if ($meta) {
            $frame['meta'] = $meta;
        }

        return $frame;
    }

    public function encode(Element|Widget $root, array $meta = []): string
    {
        return json_encode(
            $this->render($root, $meta),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }

    public function tree(Element|Widget $root): ElementTree
    {
        return new ElementTree($this->toElement($root));
    }

    /**
     * Check node types (when a protocol is available), child types and depth.
     *
     * @return string[] problems; empty when the tree is valid
     */
    public function validate(Element|Widget $root): array
    {
        $errors = [];
        $this->toElement($root)->walk(function (Element $element, int $depth) use (&$errors) {
            if ($depth > self::MAX_DEPTH) {
                $errors[] = 'Tree is deeper than ' . self::MAX_DEPTH . ' levels.';
            }
            if ($this->protocol !== null && !$this->protocol->hasNode($element->type)) {
                $errors[] = "Unknown node type '{$element->type}'.";
            }
            foreach ($element->children as $child) {
                if (!$child instanceof Element) {
                    $errors[] = "Children of '{$element->type}' must be Element instances.";
                    break;
                }
            }
        });

        return array_values(array_unique($errors));
    }

    private function toElement(Element|Widget $root): Element
    {
        return $root instanceof Widget ? $root->getElement() : $root;
    }

    private function assignIdsTo(array &$node, string $path): void
    {
        if (!isset($node['properties']['id']) || $node['properties']['id'] === '') {
            $node['properties']['id'] = $path;
        }
        foreach (array_keys($node['children'] ?? []) as $i) {
            if (is_array($node['children'][$i])) {
                $this->assignIdsTo($node['children'][$i], $path . '.' . $i);
            }
        }
    }
}
