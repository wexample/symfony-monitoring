<?php

namespace Wexample\SymfonyMonitoring\Class;

use DateTimeImmutable;

/**
 * Where a heartbeat stands: what it is, when it last succeeded and failed, and
 * whether it is late — no success for longer than its interval, counted from
 * when it was first watched for one never run.
 */
final readonly class HeartbeatState
{
    public function __construct(
        public HeartbeatDefinition $definition,
        public DateTimeImmutable $since,
        public ?DateTimeImmutable $lastSuccessAt,
        public ?DateTimeImmutable $lastFailureAt,
        public bool $late,
    ) {
    }
}
