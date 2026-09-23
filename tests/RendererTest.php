<?php

namespace greenmeteor\native\tests;

use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\widgets\Button;
use greenmeteor\native\widgets\Text;
use greenmeteor\native\widgets\VStack;
use PHPUnit\Framework\TestCase;

class RendererTest extends TestCase
{
    public function testElementTree(): void
    {
        $stack = VStack::widget([
            'children' => [
                Text::widget(['text' => 'Hello']),
                Button::widget([
                    'text' => 'Continue',
                    'action' => 'continue',
                ]),
            ],
        ]);

        $tree = $stack->run();
        $frame = (new Renderer())->render($tree);

        $this->assertSame(1, $frame['version']);
        $this->assertSame('vstack', $frame['root']['type']);
        $this->assertCount(2, $frame['root']['children']);
        $this->assertSame('Hello', $frame['root']['children'][0]['properties']['text']);
        $this->assertSame('Continue', $frame['root']['children'][1]['properties']['text']);
    }
}
