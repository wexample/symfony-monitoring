# symfony-monitoring

Version: 2.0.0

Incidents followed until someone deals with them — opened by what reports them, the remotes first, closed by hand or when the cause goes away — and heartbeats for what must keep running

## Table of Contents

- [Installation](#installation)
- [Introduction](#introduction)

## Installation

```php
// config/bundles.php
Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle::class => ['all' => true],
Wexample\SymfonyMonitoring\WexampleSymfonyMonitoringBundle::class => ['all' => true],
```

The bundle maps two tables, `monitoring_incident` and `monitoring_heartbeat`: generate a migration after installing. An incident keeps who decided about it through symfony-helpers' `UserEntityInterface`, which `doctrine.orm.resolve_target_entities` maps to the application's user.

The watch of the heartbeats and the purge of closed incidents are Scheduler tasks (`#[AsPeriodicTask]`): they run where the application consumes `scheduler_default`.

With no configuration, the users holding `ROLE_ADMIN` are told of every incident from `warning` up, by mail, and closed incidents are deleted after 180 days.

`symfony-monitoring` follows what goes wrong in an application until someone deals with it. An **incident** is one problem — a source, a code, what tells two of them apart —: opened when first reported, its repeats counted on it, resolved by hand or by itself when its cause goes away, opened again when it comes back. People acknowledge it, resolve it, or ignore it, with a note; who decided is kept.

What reports incidents:

- the **remotes**: every `RemoteSignal` of php-remote — a call failing, a check finding a service down, an answer with something wrong in it — becomes an incident, and its recovery closes it. Nothing to write: installing the bundle is enough;
- **heartbeats**: what must keep running — a nightly import — says it ran; a heartbeat without a success for longer than its interval opens an incident, a run that failed opens another;
- the application, through `IncidentService`.

An incident that opens or comes back alerts the people in charge through symfony-notification — once per opening, not per repeat. Each change goes to the subject's history when symfony-activity is installed. The screens are symfony-monitoring-ds's.

Not to be confused with symfony-activity, which keeps what happened to be read later, nor with symfony-check, which computes findings on objects without keeping them: an incident is a state, to be dealt with.
