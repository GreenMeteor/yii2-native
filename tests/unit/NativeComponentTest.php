<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\events\NativeEvent;
use greenmeteor\native\events\NativeEvents;
use greenmeteor\native\Native;
use greenmeteor\native\tests\Support\NativeTestCase;

class NativeComponentTest extends NativeTestCase
{
    public function testComponentInitializesWithDefaults(): void
    {
        $native = $this->native();
        $this->assertSame($native, \Yii::$app->native);
        $this->assertFalse($native->isAvailable());
        $this->assertSame('Test App', $native->name, 'name falls back to the application name');
        $this->assertSame([], $native->validate());
    }

    public function testComponentClassesCanBeConfiguredAsArrays(): void
    {
        $native = $this->native(['runtimeClass' => ['class' => \greenmeteor\native\runtime\Runtime::class, 'autoDetect' => false, 'persistent' => false]]);
        $this->assertFalse($native->runtime->persistent);
    }

    public function testCallDelegatesToBridge(): void
    {
        $native = $this->native();
        $native->fake(['Device.getId' => 'x']);
        $this->assertTrue($native->isAvailable());
        $this->assertSame('x', $native->call('Device.getId'));
    }

    public function testDispatchTriggersSpecificAndCatchAllHandlers(): void
    {
        $native = $this->native();
        $seen = [];
        $native->on(NativeEvents::SCANNER_SCANNED, function (NativeEvent $e) use (&$seen) {
            $seen[] = ['specific', $e->type, $e->id, $e->get('data')];
        });
        $native->on(NativeEvents::ANY, function (NativeEvent $e) use (&$seen) {
            $seen[] = ['any', $e->type];
        });

        $event = $native->dispatch(NativeEvents::SCANNER_SCANNED, ['id' => 'r1', 'data' => 'QR']);

        $this->assertSame([['specific', 'nativeScannerScanned', 'r1', 'QR'], ['any', 'nativeScannerScanned']], $seen);
        $this->assertSame('r1', $event->id);
        $this->assertSame('fallback', $event->get('missing', 'fallback'));
    }

    public function testHandledEventStopsCatchAll(): void
    {
        $native = $this->native();
        $any = 0;
        $native->on(NativeEvents::ACTION, static function (NativeEvent $e) {
            $e->handled = true;
        });
        $native->on(NativeEvents::ANY, function () use (&$any) {
            $any++;
        });
        $native->dispatch(NativeEvents::ACTION, ['action' => 'go']);
        $this->assertSame(0, $any);
    }

    public function testEventHandlersCanBeConfiguredViaComponentConfig(): void
    {
        $hit = null;
        $native = $this->native(['on ' . NativeEvents::DEEP_LINK => function (NativeEvent $e) use (&$hit) {
            $hit = $e->get('url');
        }]);
        $native->dispatch(NativeEvents::DEEP_LINK, ['url' => 'myapp://x']);
        $this->assertSame('myapp://x', $hit);
    }

    public function testValidateReportsEachProblem(): void
    {
        $native = $this->native([
            'appId' => 'Bad_Id',
            'name' => '  ',
            'version' => 'v1',
            'versionCode' => 0,
            'orientation' => 'sideways',
            'deepLinkScheme' => '1bad',
            'deepLinkHosts' => ['ok.example.com', 'bad host'],
            'permissions' => ['camera', 'teleport'],
            'http' => ['secret' => 'short'],
        ]);
        $errors = implode("\n", $native->validate());
        foreach (['appId', 'name must', 'version', 'versionCode', 'orientation', 'deepLinkScheme', 'deepLinkHosts', "'teleport'", 'http.secret'] as $needle) {
            $this->assertStringContainsString($needle, $errors);
        }
        $this->assertCount(9, $native->validate());
    }

    public function testHttpFunctionPolicy(): void
    {
        $native = $this->native();
        $this->assertTrue($native->isHttpFunctionAllowed('Device.vibrate'));
        $this->assertFalse($native->isHttpFunctionAllowed('Unknown.fn'), 'unknown functions are never callable over HTTP');

        $native = $this->native(['http' => ['allow' => ['Device.*', 'Haptics.*'], 'deny' => ['Device.toggleFlashlight']]]);
        $this->assertTrue($native->isHttpFunctionAllowed('Device.vibrate'));
        $this->assertFalse($native->isHttpFunctionAllowed('Device.toggleFlashlight'), 'deny beats allow');
        $this->assertFalse($native->isHttpFunctionAllowed('SecureStorage.get'), 'not in allow list');
    }

    public function testPhpHandlersAreHttpCallable(): void
    {
        $native = $this->native();
        $native->bridge->register('Custom.go', static fn () => 1);
        $this->assertTrue($native->isHttpFunctionAllowed('Custom.go'));
    }

    public function testKnownEvents(): void
    {
        $native = $this->native();
        $this->assertTrue($native->isKnownEvent(NativeEvents::FOREGROUND));
        $this->assertFalse($native->isKnownEvent('somethingElse'));
    }

    public function testVersionConstant(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Native::VERSION);
    }
}
