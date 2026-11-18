<?php

namespace Wexample\SymfonyMonitoring\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonyMonitoring\Entity\Incident;

trait IncidentEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return Incident::class;
    }
}
