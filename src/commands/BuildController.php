<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

use greenmeteor\native\build\ManifestBuilder;
use greenmeteor\native\build\ShellRunner;

/**
 * Prepare (and optionally run) the native shell build.
 *
 * This package does not bundle the Xcode / Gradle projects. `native/build`
 * validates the configuration and writes `native/build/manifest.json` plus the
 * Android permission and iOS Info.plist fragments. With `--exec` it also runs
 * the command configured under `build.<platform>.build`.
 */
class BuildController extends BaseNativeController
{
    /** @var bool run the configured shell build command */
    public $exec = false;

    protected string $commandKey = 'build';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['exec']);
    }

    public function actionIndex(string $platform = 'all'): int
    {
        $platforms = $this->platforms($platform);
        if ($platforms === null) {
            return $this->error("Unknown platform '{$platform}'. Use ios, android or all.");
        }

        $native = $this->native();
        if ($errors = $native->validate()) {
            foreach ($errors as $error) {
                $this->error('  - ' . $error);
            }

            return $this->error('Configuration is invalid; fix the problems above first.');
        }

        $buildDir = $this->projectRoot() . '/build';
        foreach ((new ManifestBuilder($native))->write($buildDir) as $file) {
            $this->line('wrote ' . $file);
        }

        $exit = ExitCode::OK;
        foreach ($platforms as $p) {
            $command = $native->build[$p][$this->commandKey] ?? null;

            if (!$this->exec) {
                $this->line($command
                    ? "{$p}: run with --exec to execute `{$command}`"
                    : "{$p}: no '{$this->commandKey}' command configured (build.{$p}.{$this->commandKey}); build the shell with your platform toolchain using the generated manifest.");
                continue;
            }
            if (!$command) {
                $this->error("{$p}: no build.{$p}.{$this->commandKey} command configured.");
                $exit = ExitCode::UNSPECIFIED_ERROR;
                continue;
            }

            $this->line("{$p}: {$command}");
            if ($this->runner()->run((string) $command, $this->projectRoot() . '/' . $p) !== 0) {
                $this->error("{$p}: command failed.");
                $exit = ExitCode::UNSPECIFIED_ERROR;
            }
        }

        return $exit;
    }

    protected function runner(): ShellRunner
    {
        return new ShellRunner();
    }
}
