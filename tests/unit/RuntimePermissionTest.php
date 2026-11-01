<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\permission\PermissionManager;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\runtime\Runtime;
use PHPUnit\Framework\TestCase;

class RuntimePermissionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['NATIVE_PLATFORM'], $_SERVER['NATIVE_RUNTIME']);
    }

    public function testPlatformDetectionFromEnvironment(): void
    {
        $r = new Runtime();
        $this->assertNull($r->getPlatform());
        $this->assertFalse($r->isMobile());

        $_SERVER['NATIVE_PLATFORM'] = ' Android ';
        $this->assertSame('android', $r->getPlatform());
        $this->assertTrue($r->isAndroid());
        $this->assertFalse($r->isIos());

        $_SERVER['NATIVE_PLATFORM'] = 'windows';
        $this->assertNull($r->getPlatform());
    }

    public function testExplicitPlatformWinsOverEnvironment(): void
    {
        $_SERVER['NATIVE_PLATFORM'] = 'android';
        $this->assertSame('ios', (new Runtime(['platform' => 'ios']))->getPlatform());
    }

    public function testEnvironmentVariableMakesRuntimeAvailable(): void
    {
        $r = new Runtime();
        $this->assertFalse($r->isAvailable());
        $_SERVER['NATIVE_RUNTIME'] = '1';
        $this->assertTrue($r->isAvailable());
        $this->assertFalse((new Runtime(['autoDetect' => false]))->isAvailable());
    }

    public function testStartAndStopTriggerEvents(): void
    {
        $r = new Runtime();
        $log = [];
        $r->on(Runtime::EVENT_START, function () use (&$log) { $log[] = 'start'; });
        $r->on(Runtime::EVENT_STOP, function () use (&$log) { $log[] = 'stop'; });
        $r->start();
        $r->stop();
        $this->assertSame(['start', 'stop'], $log);
    }

    public function testListAndMapFormsAreNormalised(): void
    {
        $p = new PermissionManager(['permissions' => ['location', 'camera' => 'Scan receipts.']]);
        $this->assertSame(['camera', 'location'], $p->names());
        $this->assertTrue($p->has('camera'));
        $this->assertFalse($p->has('microphone'));
    }

    public function testAndroidPermissionsIncludeAlwaysOnesSortedAndUnique(): void
    {
        $p = new PermissionManager(['permissions' => ['location', 'location', 'camera']]);
        $android = $p->androidPermissions();
        $this->assertSame($android, array_values(array_unique($android)));
        $sorted = $android;
        sort($sorted);
        $this->assertSame($sorted, $android);
        foreach (PermissionManager::ANDROID_ALWAYS as $always) {
            $this->assertContains($always, $android);
        }
        $this->assertContains('android.permission.CAMERA', $android);
        $this->assertContains('android.permission.ACCESS_FINE_LOCATION', $android);
    }

    public function testIosPlistUsesCustomOrDefaultReasons(): void
    {
        $p = new PermissionManager(['permissions' => ['camera' => 'Scan receipts.', 'microphone']]);
        $plist = $p->iosPlist();
        $this->assertSame('Scan receipts.', $plist['NSCameraUsageDescription']);
        $this->assertSame('This app uses the microphone.', $plist['NSMicrophoneUsageDescription']);
    }

    public function testPushAddsEntitlement(): void
    {
        $p = new PermissionManager(['permissions' => ['push_notifications']]);
        $this->assertSame(['aps-environment' => 'production'], $p->iosEntitlements());
    }

    public function testUnknownPermissionsAreReported(): void
    {
        $p = new PermissionManager(['permissions' => ['camera', 'bogus']]);
        $this->assertSame(['bogus'], $p->unknown());
    }

    public function testMissingIsEmptyForUnknownFunctionsAndFunctionsWithoutPermissions(): void
    {
        $p = new PermissionManager(['protocol' => new Protocol()]);
        $this->assertSame([], $p->missing('Totally.unknown'));
        $this->assertSame([], $p->missing('Device.vibrate'));
        $this->assertSame(['camera', 'microphone'], $p->missing('Camera.recordVideo'));
    }
}
