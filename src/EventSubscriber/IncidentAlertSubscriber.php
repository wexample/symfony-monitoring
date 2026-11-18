<?php

namespace Wexample\SymfonyMonitoring\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyCheck\Enum\Severity;
use Wexample\SymfonyMonitoring\Event\IncidentChangedEvent;
use Wexample\SymfonyMonitoring\Service\IncidentRecipientService;
use Wexample\SymfonyNotification\Service\NotificationService;

/**
 * Tells the people in charge — IncidentRecipientService's — when an
 * incident opens or comes back, at the configured severity and above, through
 * symfony-notification's `monitoring.incident`. Its repeats tell nobody: an
 * incident alerts once per opening.
 *
 * symfony-notification creates the notifications through the ORM: an alert
 * flushes what the caller of the report had pending, which a report alone never
 * does — the price of being told.
 */
class IncidentAlertSubscriber implements EventSubscriberInterface
{
    public const string CATEGORY = 'monitoring.incident';

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly IncidentRecipientService $recipients,
        #[Autowire('%wexample_symfony_monitoring.alerts.min_severity%')]
        private readonly string $minSeverity,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [IncidentChangedEvent::class => 'onChange'];
    }

    public function onChange(IncidentChangedEvent $event): void
    {
        $incident = $event->incident;

        if (! $event->change->isAlarming() || ! $incident->getSeverity()->isAtLeast(Severity::from($this->minSeverity))) {
            return;
        }

        $this->notifications->notify(
            self::CATEGORY,
            $this->recipients->findRecipients(),
            $incident,
            [
                'change' => $event->change->value,
                'source' => $incident->getSourceType().' '.$incident->getSourceKey(),
                'code' => $incident->getCode(),
                'severity' => $incident->getSeverity()->value,
                'message' => $incident->getMessage(),
            ]
        );
    }
}
