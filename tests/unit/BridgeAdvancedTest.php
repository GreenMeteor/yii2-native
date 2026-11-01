<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\bridge\FakeTransport;
use greenmeteor\native\bridge\FunctionTransport;
use greenmeteor\native\bridge\NativeBridge;
use greenmeteor\native\events\NativeCallEvent;
use greenmeteor\native\exceptions\NativeCallException;
use greenmeteor\native\exceptions\NativePermissionException;
use greenmeteor\native\permission\PermissionManager;
use greenmeteor\native\protocol\Protocol;
use greenmeteor\native\runtime\Runtime;
use greenmeteor\native\tests\Support\NativeTestCase;
use yii\base\InvalidArgumentException;

class BridgeAdvancedTest extends NativeTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['yii2_native_test_calls'] = [];
        $GLOBALS['yii2_native_test_mode'] = 'echo';
    }

    private function bridge(): array
    {
        $fake = new FakeTransport();
        $bridge = new NativeBridge();
        $bridge->setTransport($fake);

        return [$bridge, $fake];
    }

    public function testFakeTransportMakesBridgeAvailableAndRecordsCalls(): void
    {
        [$bridge, $fake] = $this->bridge();
        $fake->responses['Device.getId'] = 'abc';

        $this->assertTrue($bridge->isAvailable());
        $this->assertSame('abc', $bridge->call('Device.getId'));
        $fake->assertCalled('Device.getId');
        $fake->assertNotCalled('Device.vibrate');
    }

    public function testFakeResponsesCanBeCallablesOrThrowables(): void
    {
        [$bridge, $fake] = $this->bridge();
        $fake->responses['A.echo'] = static fn (array $args) => $args['x'] * 2;
        $fake->responses['A.fail'] = new \RuntimeException('boom');

        $this->assertSame(10, $bridge->call('A.echo', ['x' => 5]));
        $this->expectExceptionMessage('boom');
        $bridge->call('A.fail');
    }

    public function testAssertCalledFailsWhenMatcherDoesNotMatch(): void
    {
        [$bridge, $fake] = $this->bridge();
        $bridge->call('Device.vibrate', ['duration' => 50]);

        $fake->assertCalled('Device.vibrate', static fn ($a) => $a['duration'] === 50);
        $this->expectException(\AssertionError::class);
        $fake->assertCalled('Device.vibrate', static fn ($a) => $a['duration'] === 999);
    }

    public function testInvalidFunctionNamesAreRejected(): void
    {
        foreach (self::invalidNames() as [$name]) {
            [$bridge] = $this->bridge();
            try {
                $bridge->call($name);
                $this->fail('Accepted ' . json_encode($name));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public static function invalidNames(): array
    {
        return [['Bad Name'], ['a..b'], ['.lead'], ['trail.'], ['x;rm -rf'], [str_repeat('a', 200)], ["Dev\nice.x"]];
    }

    public function testBeforeCallListenerCanShortCircuit(): void
    {
        [$bridge, $fake] = $this->bridge();
        $bridge->on(NativeBridge::EVENT_BEFORE_CALL, static function (NativeCallEvent $e) {
            $e->handled = true;
            $e->result = 'cached';
        });

        $this->assertSame('cached', $bridge->call('Device.getId'));
        $this->assertFalse($fake->wasCalled('Device.getId'));
    }

    public function testAfterCallReportsDuration(): void
    {
        [$bridge] = $this->bridge();
        $duration = null;
        $bridge->on(NativeBridge::EVENT_AFTER_CALL, static function (NativeCallEvent $e) use (&$duration) {
            $duration = $e->duration;
        });
        $bridge->call('Device.getId');
        $this->assertIsFloat($duration);
        $this->assertGreaterThanOrEqual(0.0, $duration);
    }

    public function testRegisteredPhpHandlerRunsWithoutRuntime(): void
    {
        $bridge = new NativeBridge(); // no runtime, default FunctionTransport
        $bridge->register('Plug.ping', static fn (array $a, string $fn) => [$fn, $a]);

        $this->assertTrue($bridge->hasHandler('Plug.ping'));
        $this->assertSame(['Plug.ping', ['m' => 1]], $bridge->call('Plug.ping', ['m' => 1]));
        $bridge->unregister('Plug.ping');
        $this->assertFalse($bridge->hasHandler('Plug.ping'));
    }

    public function testPermissionGuardBlocksUndeclaredPermission(): void
    {
        $protocol = new Protocol();
        $bridge = new NativeBridge(['protocol' => $protocol]);
        $bridge->permissions = new PermissionManager(['protocol' => $protocol]);
        $bridge->setTransport(new FakeTransport());

        try {
            $bridge->call('Camera.getPhoto', ['id' => 'x']);
            $this->fail('Expected NativePermissionException');
        } catch (NativePermissionException $e) {
            $this->assertSame(['camera'], $e->missing);
            $this->assertStringContainsString("'camera'", $e->getMessage());
        }

        $bridge->permissions->merge(['camera']);
        $bridge->call('Camera.getPhoto', ['id' => 'x']);
        $this->addToAssertionCount(1);
    }

    public function testPermissionGuardCanBeDisabled(): void
    {
        $protocol = new Protocol();
        $bridge = new NativeBridge();
        $bridge->permissions = new PermissionManager(['protocol' => $protocol, 'enforce' => false]);
        $bridge->setTransport(new FakeTransport());
        $bridge->call('Camera.getPhoto');
        $this->addToAssertionCount(1);
    }

    public function testRuntimeAvailabilityTakesPartInBridgeAvailability(): void
    {
        $bridge = new NativeBridge(['runtime' => new Runtime(['available' => true, 'autoDetect' => false])]);
        $this->assertTrue($bridge->isAvailable());
    }

    public function testFunctionTransportPassesArraysByDefault(): void
    {
        $t = new FunctionTransport(['functionName' => 'yii2_native_test_call']);
        $this->assertTrue($t->isAvailable());
        $this->assertSame(['function' => 'A.b', 'arguments' => ['k' => 1]], $t->send('A.b', ['k' => 1]));
    }

    public function testFunctionTransportJsonModes(): void
    {
        $GLOBALS['yii2_native_test_mode'] = 'json';
        $t = new FunctionTransport(['functionName' => 'yii2_native_test_call', 'jsonArguments' => true, 'jsonResponse' => true]);
        $this->assertSame(['function' => 'A.b'], $t->send('A.b', ['k' => 1]));
        $this->assertSame('{"k":1}', $GLOBALS['yii2_native_test_calls'][0][1]);
    }

    public function testErrorEnvelopeBecomesNativeCallException(): void
    {
        $GLOBALS['yii2_native_test_mode'] = 'error';
        $bridge = new NativeBridge();
        $bridge->setTransport(['class' => FunctionTransport::class, 'functionName' => 'yii2_native_test_call']);

        try {
            $bridge->call('Biometrics.prompt');
            $this->fail('Expected NativeCallException');
        } catch (NativeCallException $e) {
            $this->assertSame('denied', $e->errorType);
            $this->assertSame('Biometrics.prompt', $e->function);
            $this->assertSame(7, $e->getCode());
            $this->assertSame('User said no', $e->getMessage());
        }
    }

    public function testMissingFunctionMeansUnavailable(): void
    {
        $t = new FunctionTransport(['functionName' => 'definitely_not_defined_fn']);
        $this->assertFalse($t->isAvailable());
    }

    public function testInvalidTransportIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NativeBridge())->setTransport(['class' => \stdClass::class]);
    }
}
