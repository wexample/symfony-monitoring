## Configuration

```yaml
wexample_symfony_monitoring:
  alerts:
    roles: [ROLE_ADMIN]        # who is told — through the role hierarchy; empty: nobody
    min_severity: warning      # info, warning, error
  heartbeats:
    app.import.senat:
      label: Sénat import
      every: '26 hours'        # how often it must succeed at least
      severity: error          # of the incident it opens when it does not
  retention_days: 180          # closed incidents deleted after; null keeps them
```

The alert is the notification category `monitoring.incident`, declared by the bundle: the recipients turn it off on their preferences page like any other; its link opens the incident when symfony-monitoring-ds is installed.

## Remotes

Nothing to do: every remote reporting through php-remote's `RemoteReporter` — the guard, the registry, the `symfony-remote-*` packages — is followed. A failure opens an `error`, an anomaly a `warning`, a recovery closes the incident of its code. The signal's fingerprint tells two anomalies of one code apart: two misdated reports, two incidents.

## Heartbeats

```php
public function __construct(private HeartbeatService $heartbeats) {}

$this->heartbeats->beat('app.import.senat');                              // it ran
$this->heartbeats->beat('app.import.senat', false, 'The source failed.'); // it ran and failed
```

A key not declared is refused. Every ten minutes, the watch opens `missed` for a heartbeat without a success for longer than its `every` — counted from when it was first watched for one never run —; a success closes `missed` and `failed`.

## Reporting from the application

```php
$this->incidents->raise(new IncidentSource('invoice', $invoice->getNumber()), 'unpaid_after_due', Severity::Warning, 'Unpaid ten days after its due date.');
$this->incidents->recover(new IncidentSource('invoice', $invoice->getNumber()), 'unpaid_after_due');
```

Both are cheap and safe from anywhere — a listener, a flush, a loop —: they write through the connection, never flushing what the caller has pending. Only an alert does, symfony-notification creating its notifications through the ORM.

## Decisions

```php
$this->incidents->acknowledge($incident, $user, 'Looking into it.');   // no alert while it lasts
$this->incidents->resolve($incident, $user);                          // opened again if it comes back
$this->incidents->ignore($incident, $user, 'Known, accepted.');        // repeats counted, nothing more
```

Each change dispatches `IncidentChangedEvent` — the incident, what changed, who decided —, which the alert and the history listen to. With symfony-activity, the application keeps them by enabling the `monitoring` category.
