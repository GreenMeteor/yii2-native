<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\events\NativeEvent;
use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\renderer\Element;
use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\routing\DeepLinkResolver;
use greenmeteor\native\tests\Support\NativeTestCase;
use greenmeteor\native\widgets\Text;

class DeepLinkTest extends NativeTestCase
{
    private function resolver(): DeepLinkResolver
    {
        return new DeepLinkResolver('acme', ['shop.acme.test'], [
            'orders/<id:\d+>' => 'orders/view',
            'promo/<code>' => 'promo/open',
            'u/<user>/posts/<post:\d+>' => 'posts/view',
            '' => 'site/index',
        ]);
    }

    public function testCustomSchemeResolvesPathAndParams(): void
    {
        $r = $this->resolver();
        $this->assertSame(['route' => 'orders/view', 'params' => ['id' => '42']], $r->resolve('acme://orders/42'));
        $this->assertSame(['route' => 'promo/open', 'params' => ['ref' => 'x', 'code' => 'SPRING']], $r->resolve('acme://promo/SPRING?ref=x'));
        $this->assertSame(['route' => 'posts/view', 'params' => ['user' => 'ann', 'post' => '7']], $r->resolve('acme://u/ann/posts/7'));
        $this->assertSame('site/index', $r->resolve('https://shop.acme.test/')['route'], 'the empty pattern is the root path');
        $this->assertNull($r->resolve('acme://'), 'a bare scheme is malformed');
    }

    public function testHttpsLinksOnlyForConfiguredHosts(): void
    {
        $r = $this->resolver();
        $this->assertSame('orders/view', $r->resolve('https://shop.acme.test/orders/9')['route']);
        $this->assertSame('orders/view', $r->resolve('HTTPS://SHOP.ACME.TEST/orders/9')['route']);
        $this->assertNull($r->resolve('https://evil.test/orders/9'));
        $this->assertNull($r->resolve('https://shop.acme.test.evil.test/orders/9'));
        $this->assertNull($r->resolve('ftp://shop.acme.test/orders/9'));
    }

    public function testPathParamsWinOverQueryAndAreDecoded(): void
    {
        $r = $this->resolver();
        $this->assertSame('42', $r->resolve('acme://orders/42?id=999')['params']['id']);
        $this->assertSame('a b', $r->resolve('acme://promo/a%20b')['params']['code']);
    }

    public function testRejectsHostileOrMalformedInput(): void
    {
        $r = $this->resolver();
        foreach ([
            '', 'orders/42', 'javascript:alert(1)', 'file:///etc/passwd', 'acme', 'other://orders/42',
            'acme://orders/../admin', 'acme://orders/./42', 'acme://orders//42', "acme://orders/42\n", 'acme://orders/42 ',
            "acme://orders/4\x002", 'acme://orders/abc', 'acme://orders/42/extra', 'acme://nothing/here',
            'acme://' . str_repeat('a', 2100),
        ] as $url) {
            $this->assertNull($r->resolve($url), json_encode($url));
        }
    }

    public function testRegexMetaCharactersInPatternsAreLiteral(): void
    {
        $r = new DeepLinkResolver('acme', [], ['a.b/<id:\d+>' => 'x/y']);
        $this->assertNotNull($r->resolve('acme://a.b/1'));
        $this->assertNull($r->resolve('acme://aXb/1'), 'the dot must not be a wildcard');
    }

    public function testNoSchemeConfiguredMeansCustomSchemeNeverMatches(): void
    {
        $r = new DeepLinkResolver(null, [], ['x' => 'a/b']);
        $this->assertNull($r->resolve('acme://x'));
        $this->assertNull($r->resolve('https://anything.test/x'));
    }

    public function testNativeDispatchAttachesResolvedRoute(): void
    {
        $native = $this->native(['deepLinkScheme' => 'acme', 'deepLinks' => ['orders/<id:\d+>' => 'orders/view']]);
        $seen = null;
        $native->on(NativeEvents::DEEP_LINK, function (NativeEvent $e) use (&$seen) {
            $seen = $e->route;
        });

        $native->dispatch(NativeEvents::DEEP_LINK, ['url' => 'acme://orders/5']);
        $this->assertSame(['route' => 'orders/view', 'params' => ['id' => '5']], $seen);

        $native->dispatch(NativeEvents::DEEP_LINK, ['url' => 'evil://orders/5']);
        $this->assertNull($seen, 'disallowed URLs resolve to null');

        $native->dispatch(NativeEvents::DEEP_LINK, ['url' => ['not', 'a', 'string']]);
        $this->assertNull($seen);
    }

    public function testDeepLinkConfigIsValidated(): void
    {
        $this->assertSame([], $this->native(['deepLinks' => ['a/<id>' => 'x/y']])->validate());
        $this->assertCount(1, $this->native(['deepLinks' => ['a' => '']])->validate());
        $this->assertCount(1, $this->native(['deepLinks' => [0 => 'x/y']])->validate());
    }

    public function testRendererFlagsUnknownProperties(): void
    {
        $r = new Renderer(['protocol' => new Protocol()]);
        $el = new Element(['type' => 'text', 'properties' => ['text' => 'x', 'padding' => 4, 'bogus' => 1]]);
        $this->assertSame(["Unknown property 'bogus' on 'text'."], $r->validate($el));

        $this->expectException(\greenmeteor\native\exceptions\NativeException::class);
        (new Renderer(['protocol' => new Protocol(), 'strict' => true]))->render($el);
    }

    public function testPluginNodesWithoutPropertyListAcceptAnything(): void
    {
        $protocol = new Protocol();
        $protocol->registerNode('map', ['description' => 'Map']);
        $r = new Renderer(['protocol' => $protocol, 'strict' => true]);
        $frame = $r->render(new Element(['type' => 'map', 'properties' => ['lat' => 1, 'anything' => true]]));
        $this->assertSame('map', $frame['root']['type']);
    }

    public function testEveryBuiltInWidgetPassesStrictPropertyValidation(): void
    {
        $r = new Renderer(['protocol' => new Protocol(), 'strict' => true]);
        foreach (glob(dirname(__DIR__, 2) . '/src/widgets/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'Widget') {
                continue;
            }
            $class = "greenmeteor\\native\\widgets\\{$name}";
            $w = new $class(['padding' => 1, 'margin' => 1, 'background' => '#fff', 'width' => 'fill', 'height' => 2, 'visible' => true, 'accessibilityLabel' => 'a', 'testId' => 't', 'id' => 'x']);
            $this->assertSame([], $r->validate($w), $name);
        }
    }
}
