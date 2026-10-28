<?php

namespace greenmeteor\native\tests\unit;

use Codeception\Test\Unit;
use greenmeteor\native\bridge\NativeBridge;
use greenmeteor\native\events\NativeCallEvent;
use yii\base\InvalidArgumentException;
use greenmeteor\native\exceptions\NativeUnavailableException;

class NativeBridgeTest extends Unit
{
    public function testEmptyFunctionNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NativeBridge())->call('');
    }

    public function testUnavailableRuntimeRejectsCallsByDefault(): void
    {
        $this->expectException(NativeUnavailableException::class);

        (new NativeBridge())->call('Device.vibrate', ['duration' => 100]);
    }

    public function testUnavailableRuntimeCanBeAllowedAndReturnsNull(): void
    {
        $bridge = new NativeBridge();
        $bridge->allowUnavailable = true;

        $this->assertNull($bridge->call('Device.vibrate', ['duration' => 100]));
    }

    public function testCallEventsExposeFunctionAndArguments(): void
    {
        $bridge = new NativeBridge();
        $bridge->allowUnavailable = true;
        $before = null;
        $after = null;

        $bridge->on(NativeBridge::EVENT_BEFORE_CALL, static function (NativeCallEvent $event) use (&$before): void {
            $before = [$event->function, $event->arguments];
        });
        $bridge->on(NativeBridge::EVENT_AFTER_CALL, static function (NativeCallEvent $event) use (&$after): void {
            $after = [$event->function, $event->arguments, $event->result];
        });

        $result = $bridge->call('Device.vibrate', ['duration' => 100]);

        $this->assertSame(['Device.vibrate', ['duration' => 100]], $before);
        $this->assertSame(['Device.vibrate', ['duration' => 100], null], $after);
        $this->assertNull($result);
    }

    public function testErrorsTriggerErrorEventAndAreRethrown(): void
    {
        $bridge = new NativeBridge();
        $captured = null;
        $bridge->on(NativeBridge::EVENT_ERROR, static function (NativeCallEvent $event) use (&$captured): void {
            $captured = $event->error;
        });

        try {
            $bridge->call('Device.vibrate');
            $this->fail('Expected an unavailable-runtime exception.');
        } catch (NativeUnavailableException $exception) {
            $this->assertSame($exception, $captured);
        }
    }
}
