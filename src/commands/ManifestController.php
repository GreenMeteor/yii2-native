<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

use greenmeteor\native\build\ManifestBuilder;

/**
 * Generate the build manifest consumed by the native shells.
 */
class ManifestController extends BaseNativeController
{
    /** @var bool write files into native/build instead of printing JSON */
    public $write = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['write']);
    }

    public function actionIndex(): int
    {
        $builder = new ManifestBuilder($this->native());

        if ($this->write) {
            foreach ($builder->write($this->projectRoot() . '/build') as $file) {
                $this->line('wrote ' . $file);
            }

            return ExitCode::OK;
        }

        $this->line(json_encode($builder->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ExitCode::OK;
    }
}
