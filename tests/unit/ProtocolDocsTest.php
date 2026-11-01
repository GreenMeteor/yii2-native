<?php

namespace greenmeteor\native\tests\unit;

use greenmeteor\native\build\ProtocolDocs;
use greenmeteor\native\protocol\Protocol;
use PHPUnit\Framework\TestCase;

class ProtocolDocsTest extends TestCase
{
    public function testGeneratedReferenceIsUpToDate(): void
    {
        $file = dirname(__DIR__, 2) . '/docs/reference/protocol.md';
        $this->assertFileExists($file);
        $this->assertSame(
            ProtocolDocs::render(new Protocol()),
            file_get_contents($file),
            'docs/reference/protocol.md is stale. Run: php bin/generate-protocol-docs.php'
        );
    }

    public function testEveryFunctionAppearsInTheReference(): void
    {
        $md = ProtocolDocs::render(new Protocol());
        foreach ((new Protocol())->functions() as $name => $_) {
            $this->assertStringContainsString("`{$name}`", $md);
        }
    }
}
