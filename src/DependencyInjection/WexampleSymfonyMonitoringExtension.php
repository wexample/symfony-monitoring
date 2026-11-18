<?php

namespace Wexample\SymfonyMonitoring\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyMonitoring\EventSubscriber\IncidentAlertSubscriber;
use Wexample\SymfonyMonitoring\EventSubscriber\IncidentHistorySubscriber;
use Wexample\SymfonyNotification\Class\NotificationCategory;

class WexampleSymfonyMonitoringExtension extends AbstractWexampleSymfonyExtension
{
    private const string TEXTS = 'WexampleSymfonyMonitoringBundle.common.monitoring::';

    /**
     * The alert, declared here so that installing the bundle is all an
     * application does: `monitoring.incident`, sent to the roles of `alerts`, by
     * mail. Its page is the incident's, when symfony-monitoring-ds is installed.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));

        $container->prependExtensionConfig('wexample_symfony_notification', ['categories' => [
            IncidentAlertSubscriber::CATEGORY => [
                'title' => self::TEXTS.'notification.incident.title',
                'body' => self::TEXTS.'notification.incident.body',
                'label' => self::TEXTS.'notification.incident.label',
                'group' => self::TEXTS.'notification.group',
                'level' => NotificationCategory::LEVEL_DANGER,
                'roles' => $config['alerts']['roles'],
                'channels' => ['mail'],
                'route' => $container->hasExtension('wexample_symfony_monitoring_ds') ? 'monitoring_incident_show' : null,
            ],
        ]]);
    }

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_monitoring.alerts.roles', $config['alerts']['roles']);
        $container->setParameter('wexample_symfony_monitoring.alerts.min_severity', $config['alerts']['min_severity']);
        $container->setParameter('wexample_symfony_monitoring.heartbeats', $config['heartbeats']);
        $container->setParameter('wexample_symfony_monitoring.retention_days', $config['retention_days']);

        $this->loadConfig(
            __DIR__,
            $container
        );

        // The history is symfony-activity's, when the application has it — asked of
        // the kernel's bundles: the container given here knows no other extension.
        if (! in_array(WexampleSymfonyActivityBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->removeDefinition(IncidentHistorySubscriber::class);
        }
    }
}
