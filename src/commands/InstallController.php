<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

use greenmeteor\native\build\ProjectInstaller;

/**
 * Initialise the native project layout.
 */
class InstallController extends BaseNativeController
{
    /** @var bool overwrite files that already exist (config/native.php) */
    public $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['force']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['f' => 'force']);
    }

    /** Create native/{ios,android,plugins,config,build} and a starter config. */
    public function actionIndex(): int
    {
        $root = $this->projectRoot();
        $result = (new ProjectInstaller($root))->install((bool) $this->force);

        $this->line("Yii2 Native project initialized at {$root}");
        foreach ($result['created'] as $path) {
            $this->line('  created  ' . $path);
        }
        foreach ($result['skipped'] as $path) {
            $this->line('  exists   ' . $path);
        }
        $this->line();
        $this->line("Next: merge native/config/native.php into your application config, then run `php yii native/doctor`.");

        return ExitCode::OK;
    }
}
