<?php

namespace Wexample\SymfonyMonitoring\Enum;

/**
 * What just happened to an incident, as IncidentChangedEvent tells it.
 */
enum IncidentChange: string
{
    case Opened = 'opened';

    /** It came back after being resolved. */
    case Reopened = 'reopened';

    case Acknowledged = 'acknowledged';

    /** By hand, or by itself when its cause went away — then without an actor. */
    case Resolved = 'resolved';

    case Ignored = 'ignored';

    /**
     * Whether someone must hear of it now.
     */
    public function isAlarming(): bool
    {
        return self::Opened === $this || self::Reopened === $this;
    }
}
