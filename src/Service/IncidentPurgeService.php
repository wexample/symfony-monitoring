<?php

namespace Wexample\SymfonyMonitoring\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyMonitoring\Entity\Incident;
use Wexample\SymfonyMonitoring\Enum\IncidentStatus;

/**
 * Deletes the incidents closed — resolved or ignored — for longer than the
 * retention. An active incident is never deleted, however old.
 */
class IncidentPurgeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%wexample_symfony_monitoring.retention_days%')]
        private readonly ?int $retentionDays,
    ) {
    }

    /**
     * @return int|null the incidents deleted; null when nothing expires
     */
    public function purge(): ?int
    {
        if (null === $this->retentionDays) {
            return null;
        }

        $metadata = $this->entityManager->getClassMetadata(Incident::class);
        $column = $metadata->getColumnName(...);

        return (int) $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM '.$metadata->getTableName()
            .' WHERE '.$column('status').' IN (:closed) AND '.$column('statusChangedAt').' < :before',
            [
                'closed' => [IncidentStatus::Resolved->value, IncidentStatus::Ignored->value],
                'before' => new DateTimeImmutable(sprintf('-%d days', $this->retentionDays), new DateTimeZone('UTC')),
            ],
            ['closed' => ArrayParameterType::STRING, 'before' => Types::DATETIME_IMMUTABLE]
        );
    }
}
