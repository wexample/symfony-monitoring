<?php

namespace Wexample\SymfonyMonitoring\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyMonitoring\Event\IncidentChangedEvent;

/**
 * Each change of an incident in its history, when symfony-activity is installed
 * and the application keeps the `monitoring` category: `monitoring.incident.resolved`,
 * by whom, with the note.
 */
class IncidentHistorySubscriber implements EventSubscriberInterface
{
    public const string CATEGORY = 'monitoring';

    public function __construct(
        private readonly ActivityRecorder $recorder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [IncidentChangedEvent::class => 'onChange'];
    }

    public function onChange(IncidentChangedEvent $event): void
    {
        $this->recorder->record(
            $event->incident,
            self::CATEGORY,
            self::CATEGORY.'.incident.'.$event->change->value,
            [
                'occurrences' => $event->incident->getOccurrences(),
                'note' => $event->incident->getNote(),
            ],
            $event->actor
        );
    }
}
