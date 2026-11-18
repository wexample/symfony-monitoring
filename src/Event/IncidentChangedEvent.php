<?php

namespace Wexample\SymfonyMonitoring\Event;

use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyMonitoring\Entity\Incident;
use Wexample\SymfonyMonitoring\Enum\IncidentChange;

/**
 * An incident opened, came back, or changed status: dispatched once the change is
 * written. The alerts and the history listen to it; so may the application.
 */
final readonly class IncidentChangedEvent
{
    /**
     * @param UserEntityInterface|null $actor who decided it; null when it happened by itself
     */
    public function __construct(
        public Incident $incident,
        public IncidentChange $change,
        public ?UserEntityInterface $actor = null,
    ) {
    }
}
