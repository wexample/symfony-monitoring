<?php

namespace Wexample\SymfonyMonitoring\Traits;

use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonyMonitoring\WexampleSymfonyMonitoringBundle;

trait SymfonyMonitoringBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyMonitoringBundle::class;
    }
}
