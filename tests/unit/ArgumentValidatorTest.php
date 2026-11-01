<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\bridge\FakeTransport;
use greenmeteor\native\bridge\NativeBridge;
use greenmeteor\native\protocol\Protocol;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidArgumentException;

class ArgumentValidatorTest extends TestCase
{
    private function bridge(bool $validate = true): array
    {
        $fake = new FakeTransport();
        $bridge = new NativeBridge(['protocol' => new Protocol(), 'validateArguments' => $validate]);
        $bridge->setTransport($fake);

        return [$bridge, $fake];
    }

    public function testRejectsInvalidArgumentsBeforeTheyReachNative(): void
    {
        foreach (self::rejected() as $label => [$fn, $args, $messagePart]) {
            [$bridge, $fake] = $this->bridge();
            try {
                $bridge->call($fn, $args);
                $this->fail("{$label}: expected InvalidArgumentException");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($messagePart, $e->getMessage(), $label);
            }
            $this->assertSame([], $fake->calls, "{$label}: nothing may reach the transport");
        }
    }

    public static function rejected(): array
    {
        return [
            'javascript url' => ['Browser.open', ['url' => 'javascript:alert(1)'], 'http(s) URL'],
            'file url' => ['Browser.openInApp', ['url' => 'file:///etc/passwd'], 'http(s) URL'],
            'auth url' => ['Browser.authenticate', ['url' => 'intent://x'], 'http(s) URL'],
            'share url' => ['Share.url', ['url' => 'data:text/html,x'], 'http(s) URL'],
            'optional url still checked' => ['Share.text', ['text' => 'a', 'url' => 'javascript:1'], 'http(s) URL'],
            'missing required' => ['Browser.open', [], "missing required parameter 'url'"],
            'unknown key' => ['Device.vibrate', ['duration' => 5, 'evil' => 1], "unknown parameter 'evil'"],
            'numeric key' => ['Device.vibrate', [0 => 5], 'unknown parameter'],
            'wrong type int' => ['Device.vibrate', ['duration' => '100'], 'type integer'],
            'wrong type bool' => ['Camera.pickImages', ['multiple' => 1], 'type boolean'],
            'wrong type list' => ['Scanner.scan', ['formats' => ['a' => 'qr']], 'type array'],
            'enum' => ['Haptics.impact', ['style' => 'earthquake'], 'one of'],
            'path nul' => ['Share.file', ['path' => "a\0b"], 'valid path'],
            'path empty' => ['Filesystem.saveToPhotos', ['path' => ''], 'valid path'],
            'huge string' => ['Clipboard.copy', ['text' => str_repeat('x', 1048577)], 'too long'],
            'params on paramless fn' => ['Device.getId', ['x' => 1], "unknown parameter 'x'"],
        ];
    }

    public function testValidArgumentsPass(): void
    {
        [$bridge, $fake] = $this->bridge();
        $bridge->call('Browser.open', ['url' => 'https://example.com/path?q=1']);
        $bridge->call('Device.vibrate', ['duration' => 200]);
        $bridge->call('Device.vibrate'); // default applies natively; optional here
        $bridge->call('Notifications.schedule', ['id' => 'a', 'title' => 't', 'body' => 'b', 'at' => 1, 'data' => ['k' => 'v']]);
        $this->assertCount(4, $fake->calls);
    }

    public function testNumberTypeAcceptsIntsAndFloatsOnly(): void
    {
        $protocol = new Protocol();
        $protocol->registerFunction('Geo.at', ['params' => ['lat' => ['type' => 'number', 'required' => true]]]);
        $bridge = new NativeBridge(['protocol' => $protocol]);
        $bridge->setTransport(new FakeTransport());

        $bridge->call('Geo.at', ['lat' => 1]);
        $bridge->call('Geo.at', ['lat' => 51.5]);
        $this->expectException(InvalidArgumentException::class);
        $bridge->call('Geo.at', ['lat' => '51.5']);
    }

    public function testFunctionsWithoutProtocolSpecAreNotValidated(): void
    {
        [$bridge, $fake] = $this->bridge();
        $bridge->call('Unknown.anything', ['whatever' => [1, 2]]);
        $this->assertCount(1, $fake->calls);
    }

    public function testCanBeDisabled(): void
    {
        [$bridge, $fake] = $this->bridge(false);
        $bridge->call('Device.vibrate', ['duration' => 'abc']);
        $this->assertCount(1, $fake->calls);
    }

    public function testBridgeWithoutProtocolSkipsValidation(): void
    {
        $bridge = new NativeBridge();
        $bridge->setTransport(new FakeTransport());
        $bridge->call('Device.vibrate', ['duration' => 'abc']);
        $this->addToAssertionCount(1);
    }
}
