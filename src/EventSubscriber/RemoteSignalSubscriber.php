<?php

namespace Wexample\SymfonyMonitoring\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\PhpRemote\Class\RemoteSignal;
use Wexample\PhpRemote\Enum\RemoteSignalKind;
use Wexample\SymfonyCheck\Enum\Severity;
use Wexample\SymfonyMonitoring\Class\IncidentSource;
use Wexample\SymfonyMonitoring\Service\IncidentService;

/**
 * What the remotes report (php-remote's RemoteSignal) as incidents: a failure is
 * an error, an anomaly a warning, a recovery closes the incident of its code.
 */
class RemoteSignalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IncidentService $incidents,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [RemoteSignal::class => 'onSignal'];
    }

    public function onSignal(RemoteSignal $signal): void
    {
        $source = IncidentSource::remote($signal->remoteKey);

        match ($signal->kind) {
            RemoteSignalKind::Recovery => $this->incidents->recover($source, $signal->code, $signal->fingerprint),
            RemoteSignalKind::Failure => $this->incidents->raise($source, $signal->code, Severity::Error, $signal->message, $signal->context, $signal->fingerprint),
            RemoteSignalKind::Anomaly => $this->incidents->raise($source, $signal->code, Severity::Warning, $signal->message, $signal->context, $signal->fingerprint),
        };
    }
}
