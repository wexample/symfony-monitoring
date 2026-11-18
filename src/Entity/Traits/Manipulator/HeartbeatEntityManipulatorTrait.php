<?php

namespace Wexample\SymfonyMonitoring\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonyMonitoring\Entity\Heartbeat;

trait HeartbeatEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return Heartbeat::class;
    }
}
