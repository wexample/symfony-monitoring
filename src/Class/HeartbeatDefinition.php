<?php

namespace Wexample\SymfonyMonitoring\Class;

use DateInterval;
use Wexample\SymfonyCheck\Enum\Severity;

/**
 * Something that must run, as the configuration declares it: how often it
 * must succeed at least, and how serious missing it is.
 */
final readonly class HeartbeatDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public DateInterval $every,
        public Severity $severity,
    ) {
    }
}
