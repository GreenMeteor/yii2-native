#!/usr/bin/env php
<?php

/**
 * Regenerates docs/reference/protocol.md from native/protocol.json.
 *
 *     php bin/generate-protocol-docs.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use greenmeteor\native\build\ProtocolDocs;
use greenmeteor\native\protocol\Protocol;

$target = dirname(__DIR__) . '/docs/reference/protocol.md';
file_put_contents($target, ProtocolDocs::render(new Protocol()));
echo "Wrote {$target}\n";
