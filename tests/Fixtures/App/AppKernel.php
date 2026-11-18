<?php

namespace Wexample\SymfonyMonitoring\Tests\Fixtures\App;

use Wexample\SymfonyLoader\WexampleSymfonyLoaderBundle;
use Wexample\SymfonyMail\WexampleSymfonyMailBundle;
use Wexample\SymfonyMonitoring\WexampleSymfonyMonitoringBundle;
use Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new WexampleSymfonyLoaderBundle(),
            new WexampleSymfonyTranslationsBundle(),
            new WexampleSymfonyMailBundle(),
            new WexampleSymfonyNotificationBundle(),
            new WexampleSymfonyMonitoringBundle(),
        ];
    }

    protected function configuresItsOwnSecurity(): bool
    {
        return true;
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__ . '/config/config.yaml'];
    }
}
