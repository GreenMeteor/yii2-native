<?php

namespace greenmeteor\native\renderer;

use yii\base\Component;

class Renderer extends Component
{
    public const PROTOCOL_VERSION = 1;

    public function render(Element $root): array
    {
        return [
            'version' => self::PROTOCOL_VERSION,
            'root' => $root->toArray([], [], true),
        ];
    }

    public function encode(Element $root): string
    {
        return json_encode(
            $this->render($root),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }
}
