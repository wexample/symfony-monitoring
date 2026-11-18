<?php

namespace Wexample\SymfonyMonitoring;

use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Interface\LoaderBundleInterface;

/**
 * What goes wrong in an application and has to be followed until someone deals
 * with it: incidents, opened by what reports them, closed by hand or when the
 * cause goes away; heartbeats, which open one when a task stops running.
 *
 * Its assets hold the texts of its alert.
 */
class WexampleSymfonyMonitoringBundle extends AbstractBundle implements LoaderBundleInterface
{
    public static function getLoaderFrontPaths(): array
    {
        return [
            BundleHelper::getBundleCssAlias(static::class) => __DIR__.'/../assets/',
        ];
    }
}
