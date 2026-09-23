<?php

namespace greenmeteor\native\tests;

use greenmeteor\native\Native;
use PHPUnit\Framework\TestCase;
use yii\console\Application;

class NativeTest extends TestCase
{
    protected function setUp(): void
    {
        new Application([
            'id' => 'test',
            'basePath' => dirname(__DIR__),
            'components' => [
                'native' => Native::class,
            ],
        ]);
    }

    public function testNativeComponentInitializes(): void
    {
        $this->assertInstanceOf(Native::class, \Yii::$app->native);
        $this->assertFalse(\Yii::$app->native->isAvailable());
    }
}
