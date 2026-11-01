<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\bridge\FakeTransport;
use greenmeteor\native\bridge\NativeBridge;
use greenmeteor\native\build\PluginScaffolder;
use greenmeteor\native\plugin\ManifestValidator;
use greenmeteor\native\tests\Support\NativeTestCase;
use yii\base\InvalidArgumentException;

/**
 * PCRE's `$` matches before a trailing "\n", so `/^abc$/` accepts "abc\n".
 * Every identifier validator must use `\z`.
 */
class TrailingNewlineRegressionTest extends NativeTestCase
{
    public function testBridgeFunctionName(): void
    {
        $bridge = new NativeBridge();
        $bridge->setTransport(new FakeTransport());
        $this->expectException(InvalidArgumentException::class);
        $bridge->call("Device.getId\n");
    }

    public function testManifestFields(): void
    {
        $errors = ManifestValidator::validate([
            'id' => "acme/maps\n",
            'version' => "1.0.0\n",
            'bridge' => ['functions' => ["Maps.open\n" => []]],
            'elements' => ["map\n" => []],
            'events' => ["evt\n"],
        ]);
        $this->assertCount(5, $errors, implode("\n", $errors));
    }

    public function testConfigFields(): void
    {
        $native = $this->native(['appId' => "com.example.app\n", 'version' => "1.0.0\n", 'deepLinkScheme' => "myapp\n", 'deepLinkHosts' => ["host.test\n"]]);
        $this->assertCount(4, $native->validate());
    }

    public function testScaffolderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PluginScaffolder())->create("acme/maps\n", $this->tempDir() . '/x');
    }
}
