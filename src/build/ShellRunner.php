<?php

namespace greenmeteor\native\build;

/**
 * Runs a configured shell command (e.g. `./gradlew assembleDebug`) in a directory,
 * streaming its output. The command comes from the application's own
 * configuration and is only executed when the user passes `--exec`.
 */
class ShellRunner
{
    public function run(string $command, string $cwd): int
    {
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd);
        if (!is_resource($process)) {
            return 1;
        }

        return proc_close($process);
    }
}
