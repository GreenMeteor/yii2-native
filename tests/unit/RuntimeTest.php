<?php

namespace greenmeteor\native\tests\unit;

use Codeception\Test\Unit;
use greenmeteor\native\runtime\Runtime;

class RuntimeTest extends Unit
{
    public function testRuntimeStartsUnavailableAndCanBeStartedAndStopped(): void
    {
        $runtime = new Runtime();

        $this->assertFalse($runtime->isAvailable());

        $runtime->start();
        $this->assertTrue($runtime->isAvailable());

        $runtime->stop();
        $this->assertFalse($runtime->isAvailable());
    }

    public function testRuntimePersistenceDefaultsToTrue(): void
    {
        $this->assertTrue((new Runtime())->persistent);
    }
}
