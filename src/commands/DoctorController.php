<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

use greenmeteor\native\build\Doctor;

/**
 * Inspect the environment and configuration.
 */
class DoctorController extends BaseNativeController
{
    /** Run all checks. Exits with 1 if any check fails. */
    public function actionIndex(): int
    {
        $this->line('Yii2 Native environment');
        $checks = (new Doctor($this->native()))->run();

        foreach ($checks as $check) {
            $this->line(sprintf('%s %-20s %s', $this->label($check['status']), $check['name'], $check['message']));
        }

        return Doctor::hasFailures($checks) ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
