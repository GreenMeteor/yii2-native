<?php

namespace greenmeteor\native\tests\unit;

use Codeception\Test\Unit;
use greenmeteor\native\renderer\Element;
use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\widgets\Button;
use greenmeteor\native\widgets\Text;
use greenmeteor\native\widgets\VStack;

class RendererTest extends Unit
{
    public function testWidgetTreeRendersToProtocolFrame(): void
    {
        $stack = new VStack([
            'children' => [
                new Text(['text' => 'Hello']),
                new Button(['text' => 'Continue', 'action' => 'continue']),
            ],
        ]);

        $frame = (new Renderer())->render($stack);

        $this->assertSame(Renderer::PROTOCOL_VERSION, $frame['version']);
        $this->assertSame('vstack', $frame['root']['type']);
        $this->assertCount(2, $frame['root']['children']);
        $this->assertSame('Hello', $frame['root']['children'][0]['properties']['text']);
        $this->assertSame('Continue', $frame['root']['children'][1]['properties']['text']);
        $this->assertSame('continue', $frame['root']['children'][1]['properties']['action']);
    }

    public function testFrameCanBeEncodedAsJson(): void
    {
        $json = (new Renderer())->encode(new Element([
            'type' => 'text',
            'properties' => ['text' => 'Hello / world'],
        ]));

        $frame = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(Renderer::PROTOCOL_VERSION, $frame['version']);
        $this->assertSame('Hello / world', $frame['root']['properties']['text']);
    }
}
