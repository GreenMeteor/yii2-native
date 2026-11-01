<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

use greenmeteor\native\Native;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Shared helpers for the `native/*` console commands.
 */
abstract class BaseNativeController extends Controller
{
    protected function native(): Native
    {
        return Yii::$app->get('native');
    }

    protected function projectRoot(): string
    {
        return rtrim((string) Yii::getAlias($this->native()->projectPath), '/\\');
    }

    protected function line(string $message = ''): void
    {
        $this->stdout($message . "\n");
    }

    protected function error(string $message): int
    {
        $this->stderr($message . "\n", Console::FG_RED);

        return ExitCode::UNSPECIFIED_ERROR;
    }

    protected function label(string $status): string
    {
        return match ($status) {
            'ok' => $this->ansiFormat('[ ok ]', Console::FG_GREEN),
            'warn' => $this->ansiFormat('[warn]', Console::FG_YELLOW),
            default => $this->ansiFormat('[fail]', Console::FG_RED),
        };
    }

    /** @return string[]|null platforms, or null when `$platform` is invalid */
    protected function platforms(string $platform): ?array
    {
        $platform = strtolower($platform);
        if ($platform === 'all') {
            return ['ios', 'android'];
        }

        return in_array($platform, ['ios', 'android'], true) ? [$platform] : null;
    }
}
