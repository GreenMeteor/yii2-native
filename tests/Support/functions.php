<?php

/**
 * Stand-in for the native shell's call function (never `nativephp_call`, so
 * the test process is not mistaken for a native runtime).
 */
function yii2_native_test_call(string $function, $arguments)
{
    $GLOBALS['yii2_native_test_calls'][] = [$function, $arguments];
    $mode = $GLOBALS['yii2_native_test_mode'] ?? 'echo';

    return match ($mode) {
        'error' => ['error' => ['type' => 'denied', 'message' => 'User said no', 'code' => 7]],
        'json' => json_encode(['function' => $function]),
        default => ['function' => $function, 'arguments' => $arguments],
    };
}
