<?php

namespace Wexample\SymfonyMonitoring\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMonitoring\Repository\HeartbeatRepository;

/**
 * The last runs of something that must run — a nightly import —, declared in
 * the configuration under its key. HeartbeatService writes it; a heartbeat
 * without a success for longer than its interval opens an incident.
 */
#[ORM\Entity(repositoryClass: HeartbeatRepository::class)]
#[ORM\Table(name: 'monitoring_heartbeat')]
#[ORM\UniqueConstraint(name: 'monitoring_heartbeat_key', columns: ['heartbeat_key'])]
class Heartbeat extends AbstractEntity
{
    #[ORM\Column(name: 'heartbeat_key', type: Types::STRING, length: 128)]
    private string $key;

    /**
     * UTC; when it was first watched, which a heartbeat never run is measured from.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $watchedSince;

    /**
     * UTC.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSuccessAt = null;

    /**
     * UTC.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastFailureAt = null;

    public function getKey(): string
    {
        return $this->key;
    }

    public function getWatchedSince(): DateTimeImmutable
    {
        return $this->watchedSince;
    }

    public function getLastSuccessAt(): ?DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function getLastFailureAt(): ?DateTimeImmutable
    {
        return $this->lastFailureAt;
    }
}
