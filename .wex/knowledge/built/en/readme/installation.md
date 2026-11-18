## Installation

```php
// config/bundles.php
Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle::class => ['all' => true],
Wexample\SymfonyMonitoring\WexampleSymfonyMonitoringBundle::class => ['all' => true],
```

The bundle maps two tables, `monitoring_incident` and `monitoring_heartbeat`: generate a migration after installing. An incident keeps who decided about it through symfony-helpers' `UserEntityInterface`, which `doctrine.orm.resolve_target_entities` maps to the application's user.

The watch of the heartbeats and the purge of closed incidents are Scheduler tasks (`#[AsPeriodicTask]`): they run where the application consumes `scheduler_default`.

With no configuration, the users holding `ROLE_ADMIN` are told of every incident from `warning` up, by mail, and closed incidents are deleted after 180 days.
