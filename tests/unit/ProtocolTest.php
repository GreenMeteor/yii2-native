<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\renderer\Renderer;
use greenmeteor\native\widgets\Widget;
use PHPUnit\Framework\TestCase;

/**
 * Guards the contract between protocol.json, the PHP API classes, widgets and events
 * so the native shells and the docs cannot silently drift.
 */
class ProtocolTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    public function testFileLoadsAndVersionsMatch(): void
    {
        $p = new Protocol();
        $this->assertSame(Renderer::PROTOCOL_VERSION, $p->version());
        $this->assertSame(1, $p->bridgeVersion());
        $this->assertNotEmpty($p->functions());
    }

    public function testEveryApiMethodHasAProtocolFunctionAndViceVersa(): void
    {
        $declared = array_keys((new Protocol())->functions());
        $implemented = [];

        foreach (glob(self::ROOT . 'src/api/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'BaseApi') {
                continue;
            }
            $class = "greenmeteor\\native\\api\\{$name}";
            preg_match_all("/invoke\('(\w+)'/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $method) {
                $implemented[] = $class::apiName() . '.' . $method;
            }
        }

        sort($declared);
        sort($implemented);
        $this->assertSame([], array_values(array_diff($implemented, $declared)), 'API calls missing from protocol.json');
        $this->assertSame([], array_values(array_diff($declared, $implemented)), 'protocol.json functions with no API method');
    }

    public function testEveryWidgetTypeIsDeclaredAndViceVersa(): void
    {
        $p = new Protocol();
        $types = [];
        foreach (glob(self::ROOT . 'src/widgets/*.php') as $file) {
            $name = basename($file, '.php');
            if ($name === 'Widget') {
                continue;
            }
            $class = "greenmeteor\\native\\widgets\\{$name}";
            /** @var Widget $w */
            $w = new $class();
            $types[] = $w->getElementType();
        }
        $declared = $p->nodeTypes();
        sort($types);
        sort($declared);
        $this->assertSame($declared, $types);
    }

    public function testEveryEventConstantIsDeclaredInProtocol(): void
    {
        $p = new Protocol();
        foreach (NativeEvents::all() as $event) {
            $this->assertTrue($p->hasEvent($event), "{$event} missing from protocol.json");
        }
        $this->assertSame(count(NativeEvents::all()), count($p->eventNames()));
    }

    public function testInteractiveFunctionsDeclareACompletionEventThatExists(): void
    {
        $p = new Protocol();
        foreach ($p->functions() as $name => $spec) {
            if ($spec['interactive']) {
                $this->assertArrayHasKey('event', $spec, "{$name} is interactive but has no event");
                $this->assertTrue($p->hasEvent($spec['event']), "{$name} -> unknown event {$spec['event']}");
            }
        }
    }

    public function testDeclaredPermissionsAreKnown(): void
    {
        $known = array_keys(\greenmeteor\native\permission\PermissionManager::DEFINITIONS);
        foreach ((new Protocol())->functions() as $name => $spec) {
            foreach ($spec['permissions'] as $perm) {
                $this->assertContains($perm, $known, "{$name} needs unknown permission {$perm}");
            }
        }
    }

    public function testInstancesAreIsolatedWhenExtended(): void
    {
        $a = new Protocol();
        $a->registerFunction('Plug.go');
        $this->assertTrue($a->hasFunction('Plug.go'));
        $this->assertFalse((new Protocol())->hasFunction('Plug.go'));
    }
}
