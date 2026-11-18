<?php

namespace Wexample\SymfonyMonitoring\Repository;

use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyMonitoring\Entity\Heartbeat;
use Wexample\SymfonyMonitoring\Entity\Traits\Manipulator\HeartbeatEntityManipulatorTrait;

/**
 * Heartbeats are written by HeartbeatService, through the connection, for the
 * reasons IncidentRepository gives. What reads them is here.
 *
 * @method Heartbeat|null find($id, $lockMode = null, $lockVersion = null)
 */
class HeartbeatRepository extends AbstractRepository
{
    use HeartbeatEntityManipulatorTrait;

    /**
     * @return array<string, Heartbeat> by key
     */
    public function findAllByKey(): array
    {
        $heartbeats = [];

        foreach ($this->findAll() as $heartbeat) {
            $heartbeats[$heartbeat->getKey()] = $heartbeat;
        }

        return $heartbeats;
    }
}
