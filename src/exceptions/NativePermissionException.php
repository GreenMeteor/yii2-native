<?php

namespace greenmeteor\native\exceptions;

/**
 * Thrown when a bridge function requires a permission that the application
 * has not declared in its `permissions` configuration.
 */
class NativePermissionException extends NativeException
{
    /** @var string[] */
    public array $missing = [];
}
