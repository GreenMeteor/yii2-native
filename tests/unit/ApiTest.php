<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\bridge\FakeTransport;
use greenmeteor\native\tests\Support\NativeTestCase;
use yii\base\InvalidArgumentException;

class ApiTest extends NativeTestCase
{
    private FakeTransport $fake;
    private \greenmeteor\native\Native $native;

    protected function setUp(): void
    {
        $this->native = $this->native(['permissions' => ['camera', 'microphone', 'location', 'biometrics', 'photo_library', 'push_notifications']]);
        $this->fake = $this->native->fake();
    }

    public function testInteractiveCallsReturnAndSendRequestId(): void
    {
        $id = $this->native->camera->getPhoto('my-id');
        $this->assertSame('my-id', $id);
        $this->assertSame([['id' => 'my-id']], $this->fake->callsTo('Camera.getPhoto'));

        $generated = $this->native->scanner->scan();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $generated);
        $this->assertSame(['formats' => ['qr'], 'id' => $generated], $this->fake->callsTo('Scanner.scan')[0]);
    }

    public function testOptionalArgumentsAreOmittedWhenNull(): void
    {
        $this->native->camera->pickImages(true, null, 'p');
        $this->assertSame(['multiple' => true, 'id' => 'p'], $this->fake->callsTo('Camera.pickImages')[0]);
    }

    public function testReturnValuesAreNormalised(): void
    {
        $this->fake->responses = [
            'Device.getId' => 'dev-1',
            'Device.getBattery' => ['level' => 0.5, 'isCharging' => true],
            'Device.toggleFlashlight' => ['enabled' => true],
            'Biometrics.isAvailable' => 1,
            'Network.getStatus' => ['connected' => true, 'type' => 'wifi'],
            'Clipboard.paste' => null,
            'Microphone.getStatus' => 'recording',
            'Notifications.getPushToken' => '',
            'SecureStorage.get' => 'secret',
        ];
        $n = $this->native;
        $this->assertSame('dev-1', $n->device->getId());
        $this->assertSame(0.5, $n->device->getBattery()['level']);
        $this->assertTrue($n->device->toggleFlashlight());
        $this->assertTrue($n->biometrics->isAvailable());
        $this->assertTrue($n->network->isConnected());
        $this->assertNull($n->clipboard->paste());
        $this->assertSame('recording', $n->microphone->getStatus());
        $this->assertNull($n->notifications->getPushToken());
        $this->assertSame('secret', $n->secureStorage->get('k'));
        $this->assertSame([], $n->device->getInfo());
    }

    public function testBrowserAndShareOnlyAcceptHttpUrls(): void
    {
        foreach (['javascript:alert(1)', 'file:///etc/passwd', 'intent://x', '/relative', 'http://', ''] as $bad) {
            try {
                $this->native->browser->open($bad);
                $this->fail("Accepted {$bad}");
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->native->browser->open('https://example.com/a?b=c');
        $this->native->share->url('https://example.com');
        $this->assertCount(1, $this->fake->callsTo('Browser.open'));

        $this->expectException(InvalidArgumentException::class);
        $this->native->share->text('hi', null, 'javascript:alert(1)');
    }

    public function testValidation(): void
    {
        $cases = [
            fn () => $this->native->device->vibrate(0),
            fn () => $this->native->device->vibrate(10001),
            fn () => $this->native->haptics->impact('thunder'),
            fn () => $this->native->haptics->notification('meh'),
            fn () => $this->native->dialog->toast('x', 'forever'),
            fn () => $this->native->dialog->alert('t', 'm', []),
            fn () => $this->native->dialog->alert('t', 'm', ['a', 'b', 'c', 'd']),
            fn () => $this->native->scanner->scan([]),
            fn () => $this->native->scanner->scan(['nope']),
            fn () => $this->native->secureStorage->set('', 'v'),
            fn () => $this->native->secureStorage->get(str_repeat('k', 257)),
            fn () => $this->native->notifications->setBadge(-1),
            fn () => $this->native->notifications->schedule('', 't', 'b'),
            fn () => $this->native->filesystem->saveToPhotos("a\0b"),
            fn () => $this->native->share->file(''),
        ];
        foreach ($cases as $i => $case) {
            try {
                $case();
                $this->fail("Case {$i} did not throw");
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->fake->calls, 'Invalid input must never reach the bridge');
    }

    public function testNotificationsScheduleSendsOnlyProvidedFields(): void
    {
        $this->native->notifications->schedule('n1', 'Hi', 'Body', 1900000000, ['a' => 1]);
        $this->native->notifications->schedule('n2', 'Hi', 'Body');
        $calls = $this->fake->callsTo('Notifications.schedule');
        $this->assertSame(['id' => 'n1', 'title' => 'Hi', 'body' => 'Body', 'at' => 1900000000, 'data' => ['a' => 1]], $calls[0]);
        $this->assertArrayNotHasKey('at', $calls[1]);
        $this->assertArrayNotHasKey('data', $calls[1]);
    }

    public function testSystemPlatformHelpersAreLocal(): void
    {
        $this->native->runtime->platform = 'ios';
        $this->assertTrue($this->native->system->isIos());
        $this->assertFalse($this->native->system->isAndroid());
        $this->assertTrue($this->native->system->isMobile());
        $this->assertSame('ios', $this->native->system->getPlatform());
        $this->assertSame([], $this->fake->calls);
    }

    public function testApiOverrideAndUnknownApi(): void
    {
        $native = $this->native(['apis' => ['device' => OverriddenDevice::class]]);
        $this->assertInstanceOf(OverriddenDevice::class, $native->device);
        $this->assertSame($native->device, $native->device, 'APIs are cached');

        $this->expectException(\yii\base\InvalidConfigException::class);
        $native->api('nope');
    }

    public function testPermissionsAreEnforcedThroughTheComponent(): void
    {
        $native = $this->native(['permissions' => []]);
        $native->fake();
        $this->expectException(\greenmeteor\native\exceptions\NativePermissionException::class);
        $native->camera->getPhoto();
    }

    public function testBridgeWithoutRuntimeThrowsUnavailable(): void
    {
        $native = $this->native();
        $this->expectException(\greenmeteor\native\exceptions\NativeUnavailableException::class);
        $native->device->getId();
    }
}

class OverriddenDevice extends \greenmeteor\native\api\Device
{
}
