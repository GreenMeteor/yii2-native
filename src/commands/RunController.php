<?php

namespace greenmeteor\native\commands;

/**
 * Prepare (and optionally run) the app on a device or simulator.
 * Same behaviour as `native/build`, using the `build.<platform>.run` command.
 */
class RunController extends BuildController
{
    protected string $commandKey = 'run';
}
