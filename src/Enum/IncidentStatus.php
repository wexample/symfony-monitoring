<?php

namespace Wexample\SymfonyMonitoring\Enum;

/**
 * Where an incident stands. Open and acknowledged are the active ones: a repeat
 * counts on them, a recovery closes them.
 */
enum IncidentStatus: string
{
    /** Nobody has looked at it yet. */
    case Open = 'open';

    /** Someone is on it: no new alert while it lasts. */
    case Acknowledged = 'acknowledged';

    /** Over — by hand, or because its cause went away. Opened again if it comes back. */
    case Resolved = 'resolved';

    /** Known and accepted: its repeats are counted, nothing more. */
    case Ignored = 'ignored';

    public function isActive(): bool
    {
        return self::Open === $this || self::Acknowledged === $this;
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Open, self::Acknowledged];
    }
}
