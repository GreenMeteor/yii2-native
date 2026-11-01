<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

use greenmeteor\native\build\PluginScaffolder;
use Yii;
use yii\base\InvalidArgumentException;

/**
 * Manage native plugins.
 */
class PluginController extends BaseNativeController
{
    /** List registered plugins. */
    public function actionList(): int
    {
        $plugins = $this->native()->pluginManager->all();

        if (!$plugins) {
            $this->line('No registered native plugins.');

            return ExitCode::OK;
        }

        foreach ($plugins as $plugin) {
            $m = $plugin->getManifest();
            $this->line($plugin->getId() . (isset($m['version']) ? ' ' . $m['version'] : ''));
        }

        return ExitCode::OK;
    }

    /** List plugins discoverable in the project and in Composer packages (not yet registered). */
    public function actionDiscover(): int
    {
        $manager = $this->native()->pluginManager;
        $found = $manager->discover($this->projectRoot() . '/plugins')
            + $manager->discoverComposer((string) Yii::getAlias('@vendor'));

        if (!$found) {
            $this->line('No plugins discovered.');

            return ExitCode::OK;
        }

        foreach ($found as $id => $manifest) {
            $this->line(sprintf('%-30s %s%s', $id, $manifest['version'] ?? '-', $manager->has($id) ? '  (registered)' : ''));
        }
        $this->line();
        $this->line("Register them in the native component's 'plugins' option.");

        return ExitCode::OK;
    }

    /** Show the manifest of a registered plugin. */
    public function actionInfo(string $id): int
    {
        $plugin = $this->native()->pluginManager->get($id);
        if ($plugin === null) {
            return $this->error("Plugin '{$id}' is not registered.");
        }

        $this->line(json_encode($plugin->getManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ExitCode::OK;
    }

    /** Scaffold a plugin in native/plugins/<name>. */
    public function actionMake(string $id): int
    {
        $short = substr(strrchr('/' . $id, '/'), 1);
        $directory = $this->projectRoot() . '/plugins/' . $short;

        try {
            $files = (new PluginScaffolder())->create($id, $directory);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        foreach ($files as $file) {
            $this->line('created ' . $file);
        }

        return ExitCode::OK;
    }
}
