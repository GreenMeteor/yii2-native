<?php

namespace greenmeteor\native\commands;

use yii\console\ExitCode;

/**
 * Show and validate the effective native configuration.
 */
class ConfigController extends BaseNativeController
{
    /** Print the configuration and validation problems. Exits with 1 when invalid. */
    public function actionIndex(): int
    {
        $n = $this->native();
        $this->line(json_encode([
            'appId' => $n->appId,
            'name' => $n->name,
            'version' => $n->version,
            'versionCode' => $n->versionCode,
            'orientation' => $n->orientation,
            'deepLinkScheme' => $n->deepLinkScheme,
            'deepLinkHosts' => $n->deepLinkHosts,
            'permissions' => $n->getPermissionManager()->names(),
            'http' => array_merge($n->http, ['secret' => $n->http['secret'] === null ? null : '***']),
            'projectPath' => $n->projectPath,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $errors = $n->validate();
        foreach ($errors as $error) {
            $this->error('  - ' . $error);
        }

        return $errors ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
