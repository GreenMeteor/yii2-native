<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\exceptions\NativeException;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\renderer\Element;
use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\widgets\{BottomNav, BottomNavItem, Button, Card, Divider, Fab, HStack, Icon, LazyList, Loading, Spacer, Text, TextInput, Toggle, TopBar, VStack, ZStack};
use PHPUnit\Framework\TestCase;
use yii\base\InvalidArgumentException;

class WidgetsTest extends TestCase
{
    private function renderer(bool $strict = false): Renderer
    {
        return new Renderer(['protocol' => new Protocol(), 'strict' => $strict]);
    }

    public function testMakeNestsWidgetsAndFlattensLists(): void
    {
        $tree = VStack::make(['spacing' => 8], [
            Text::make(['text' => 'A']),
            array_map(fn ($t) => Text::make(['text' => $t]), ['B', 'C']),
            null,
            false,
            Spacer::make(),
        ]);

        $frame = $this->renderer()->render($tree);
        $this->assertCount(4, $frame['root']['children']);
        $this->assertSame(['A', 'B', 'C'], array_map(fn ($c) => $c['properties']['text'] ?? null, array_slice($frame['root']['children'], 0, 3)));
        $this->assertSame(8, $frame['root']['properties']['spacing']);
    }

    public function testIdsAreDeterministicAcrossRenders(): void
    {
        $build = fn () => VStack::make([], [Text::make(['text' => 'a']), HStack::make([], [Text::make(['text' => 'b'])])]);
        $r = $this->renderer();
        $a = $r->render($build());
        $b = $r->render($build());

        $this->assertSame($a, $b);
        $this->assertSame('root', $a['root']['properties']['id']);
        $this->assertSame('root.0', $a['root']['children'][0]['properties']['id']);
        $this->assertSame('root.1.0', $a['root']['children'][1]['children'][0]['properties']['id']);
    }

    public function testExplicitIdsAreKeptAndCanBeDisabledGlobally(): void
    {
        $w = VStack::make([], [Text::make(['id' => 'title', 'text' => 'x'])]);
        $frame = $this->renderer()->render($w);
        $this->assertSame('title', $frame['root']['children'][0]['properties']['id']);
        $this->assertSame('title', $w->getElement()->find('title')->properties['id']);

        $noIds = new Renderer(['assignIds' => false]);
        $this->assertArrayNotHasKey('id', $noIds->render(Text::make(['text' => 'x']))['root']['properties']);
    }

    public function testNullPropertiesAreNotSerialized(): void
    {
        $props = Text::make(['text' => 'Hi'])->getProperties();
        $this->assertSame(['text' => 'Hi'], $props);

        $props = Text::make(['text' => 'Hi', 'size' => 20, 'padding' => [8, 16], 'visible' => false, 'testId' => 't'])->getProperties();
        $this->assertSame(['text' => 'Hi', 'size' => 20, 'padding' => [8, 16], 'visible' => false, 'testId' => 't'], $props);
    }

    public function testPropertiesFollowLaterChanges(): void
    {
        $t = new Text(['text' => 'one']);
        $t->text = 'two';
        $this->assertSame('two', $t->getElement()->properties['text']);
    }

    public function testRawPropertiesOverrideComputedOnes(): void
    {
        $t = Text::make(['text' => 'a', 'properties' => ['text' => 'b', 'custom' => 1]]);
        $this->assertSame(['text' => 'b', 'custom' => 1], $t->getProperties());
    }

    public function testInvalidChildrenFailEarly(): void
    {
        foreach (['string', ['array' => 1], 42, new \stdClass()] as $bad) {
            $w = new VStack(['children' => [$bad]]);
            try {
                $w->getElement();
                $this->fail('Accepted ' . get_debug_type($bad));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString(get_debug_type($bad), $e->getMessage());
            }
        }
    }

    public function testLegacyChildAndConfigChildrenStillWork(): void
    {
        $viaChild = (new VStack())->child(new Text(['text' => 'a']))->child(new Text(['text' => 'b']));
        $viaConfig = new VStack(['children' => [new Text(['text' => 'a']), new Text(['text' => 'b'])]]);
        $this->assertSame($viaChild->getElement()->toArray(), $viaConfig->getElement()->toArray());
    }

    public function testStaticWidgetReturnsJsonFrameForStandaloneWidget(): void
    {
        $json = Text::widget(['text' => 'Hello']);
        $frame = json_decode($json, true);
        $this->assertSame('text', $frame['root']['type']);
        $this->assertSame(1, $frame['version']);
    }

    public function testStrictModeRejectsUnknownNodeTypes(): void
    {
        $unknown = new Element(['type' => 'hologram']);
        $this->assertSame(["Unknown node type 'hologram'."], $this->renderer()->validate($unknown));

        $this->expectException(NativeException::class);
        $this->renderer(true)->render($unknown);
    }

    public function testStrictModeRejectsNonElementChildren(): void
    {
        $e = new Element(['type' => 'vstack', 'children' => ['raw']]);
        $this->assertNotEmpty($this->renderer()->validate($e));
    }

    public function testMetaIsOnlyIncludedWhenGiven(): void
    {
        $r = $this->renderer();
        $this->assertArrayNotHasKey('meta', $r->render(Text::make()));
        $this->assertSame(['title' => 'Home'], $r->render(Text::make(), ['title' => 'Home'])['meta']);
    }

    public function testEncodeKeepsSlashesAndThrowsOnBadUtf8(): void
    {
        $r = $this->renderer();
        $this->assertStringContainsString('a/b', $r->encode(Text::make(['text' => 'a/b'])));
        $this->expectException(\JsonException::class);
        $r->encode(Text::make(['text' => "\xB1\x31"]));
    }

    public function testElementHelpers(): void
    {
        $e = VStack::make([], [Text::make(['id' => 'x']), HStack::make([], [Text::make(['id' => 'y'])])])->getElement();
        $this->assertSame(4, $e->count());
        $this->assertSame('y', $e->find('y')->properties['id']);
        $this->assertNull($e->find('nope'));
        $this->assertSame(4, $this->renderer()->tree(VStack::make([], [Text::make(), Text::make(), Text::make()]))->count());
        $this->assertSame($e->toArray(), json_decode(json_encode($e), true));
    }

    public function testNewWidgetsProduceExpectedNodes(): void
    {
        $screen = ZStack::make([], [
            TopBar::make(['title' => 'Home', 'actions' => [['icon' => 'search', 'action' => 'search']]]),
            LazyList::make(['itemSpacing' => 4], [
                Card::make(['elevation' => 2], [Icon::make(['name' => 'star']), Divider::make(['thickness' => 1])]),
                TextInput::make(['name' => 'q', 'keyboard' => 'email', 'onChange' => 'typing']),
                Toggle::make(['name' => 'push', 'label' => 'Push', 'value' => true]),
                Loading::make(['message' => 'Wait']),
            ]),
            BottomNav::make(['activeId' => 'home'], [
                BottomNavItem::make(['id' => 'home', 'label' => 'Home', 'icon' => 'home', 'action' => 'go.home', 'active' => true]),
            ]),
            Fab::make(['action' => 'compose']),
            Button::make(['text' => 'Go', 'action' => 'go', 'payload' => ['id' => 5]]),
        ]);

        $r = $this->renderer(true);
        $frame = $r->render($screen);
        $types = array_map(fn ($c) => $c['type'], $frame['root']['children']);
        $this->assertSame(['topbar', 'list', 'bottomnav', 'fab', 'button'], $types);
        $this->assertSame([['icon' => 'search', 'action' => 'search']], $frame['root']['children'][0]['properties']['actions']);
        $this->assertSame(['id' => 5], $frame['root']['children'][4]['properties']['payload']);
    }

    public function testTopBarActionsRequireActionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TopBar::make(['title' => 'x', 'actions' => [['icon' => 'x']]])->getElement();
    }
}
