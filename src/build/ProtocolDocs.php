<?php

namespace greenmeteor\native\build;

use greenmeteor\native\protocol\Protocol;

/**
 * Renders the protocol reference (bridge functions, events, UI nodes) as
 * Markdown. `bin/generate-protocol-docs.php` writes it to docs/reference/.
 */
final class ProtocolDocs
{
    public static function render(Protocol $protocol): string
    {
        $out = "# Protocol reference\n\n"
            . "> Generated from `native/protocol.json` by `bin/generate-protocol-docs.php`. Do not edit by hand.\n\n"
            . sprintf(
                "Element-tree protocol **v%d**, bridge protocol **v%d**: %d functions, %d events, %d node types.\n\n",
                $protocol->version(),
                $protocol->bridgeVersion(),
                count($protocol->functions()),
                count($protocol->eventNames()),
                count($protocol->nodeTypes())
            );

        $out .= "## Bridge functions\n\n"
            . "*Interactive* functions return immediately; the result arrives later as the listed event, carrying the same `id`.\n\n";

        $groups = [];
        foreach ($protocol->functions() as $name => $spec) {
            $groups[strstr($name, '.', true)][$name] = $spec;
        }
        foreach ($groups as $group => $functions) {
            $out .= "### {$group}\n\n";
            $out .= "| Function | Mode | Permissions | Parameters | Returns / event |\n|---|---|---|---|---|\n";
            foreach ($functions as $name => $spec) {
                $params = [];
                foreach ($spec['params'] ?? [] as $p => $def) {
                    $params[] = sprintf(
                        '`%s%s: %s`%s',
                        $p,
                        empty($def['required']) ? '?' : '',
                        isset($def['enum']) ? implode('\|', $def['enum']) : ($def['type'] ?? 'mixed'),
                        isset($def['format']) ? " ({$def['format']})" : ''
                    );
                }
                $out .= sprintf(
                    "| `%s`<br>%s | %s | %s | %s | %s |\n",
                    $name,
                    self::cell($spec['description'] ?? ''),
                    !empty($spec['interactive']) ? 'interactive' : 'immediate',
                    $spec['permissions'] ? '`' . implode('`, `', $spec['permissions']) . '`' : '-',
                    $params ? implode('<br>', $params) : '-',
                    !empty($spec['event']) ? '`' . $spec['event'] . '`' : self::cell($spec['returns'] ?? '-')
                );
            }
            $out .= "\n";
        }

        $out .= "## Events\n\n| Event | Description | Payload |\n|---|---|---|\n";
        foreach ($protocol->toArray()['events'] as $name => $spec) {
            $payload = [];
            foreach ($spec['payload'] ?? [] as $k => $t) {
                $payload[] = "`{$k}: {$t}`";
            }
            $out .= sprintf("| `%s` | %s | %s |\n", $name, self::cell($spec['description'] ?? ''), $payload ? implode('<br>', $payload) : '-');
        }

        $out .= "\n## UI node types\n\n"
            . 'Every node also accepts the common properties: `' . implode('`, `', $protocol->commonProperties()) . "`.\n\n"
            . "| Type | Container | Properties | Description |\n|---|---|---|---|\n";
        foreach ($protocol->toArray()['nodes'] as $type => $spec) {
            $props = $spec['properties'] ?? [];
            $out .= sprintf(
                "| `%s` | %s | %s | %s |\n",
                $type,
                !empty($spec['container']) ? 'yes' : 'no',
                $props ? '`' . implode('`, `', $props) . '`' : '-',
                self::cell($spec['description'] ?? '')
            );
        }

        return $out;
    }

    private static function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $text);
    }
}
