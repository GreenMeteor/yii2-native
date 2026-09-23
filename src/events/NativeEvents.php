<?php

namespace greenmeteor\native\events;

final class NativeEvents
{
    public const LAUNCH = 'nativeLaunch';
    public const READY = 'nativeReady';
    public const ACTIVE = 'nativeActive';
    public const INACTIVE = 'nativeInactive';
    public const FOREGROUND = 'nativeForeground';
    public const BACKGROUND = 'nativeBackground';
    public const TERMINATING = 'nativeTerminating';
    public const LOW_MEMORY = 'nativeLowMemory';

    private function __construct()
    {
    }
}
