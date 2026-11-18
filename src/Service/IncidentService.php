<?php

namespace Wexample\SymfonyMonitoring\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyCheck\Enum\Severity;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyMonitoring\Class\IncidentSource;
use Wexample\SymfonyMonitoring\Entity\Incident;
use Wexample\SymfonyMonitoring\Enum\IncidentChange;
use Wexample\SymfonyMonitoring\Enum\IncidentStatus;
use Wexample\SymfonyMonitoring\Event\IncidentChangedEvent;
use Wexample\SymfonyMonitoring\Repository\IncidentRepository;

/**
 * Opens incidents, counts their repeats, closes them when their cause goes
 * away, and records what people decide about them.
 *
 * A report — raise(), recover() — is written straight through the connection,
 * not persisted: it comes from listeners and tasks, in the middle of someone
 * else's unit of work, which a flush would carry along. A decision — acknowledge,
 * resolve, ignore — comes from a person, through the entity.
 *
 * Each change is dispatched as an IncidentChangedEvent once written.
 */
class IncidentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IncidentRepository $repository,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Something went wrong: a new incident, a repeat counted on the one already
     * there — its severity raised if this one is worse —, or a resolved one opened
     * again. An ignored one only counts it.
     *
     * @param array<string, scalar|null> $context
     * @param string|null                $discriminator what tells two incidents of the same source and code apart
     */
    public function raise(
        IncidentSource $source,
        string $code,
        Severity $severity,
        string $message = '',
        array $context = [],
        ?string $discriminator = null
    ): void {
        $fingerprint = $source->fingerprint($code, $discriminator);
        $existing = $this->findState($fingerprint);
        $now = $this->now();

        if (null === $existing) {
            try {
                $this->insert($fingerprint, $source, $code, $severity, $message, $context, $now);
                $this->dispatch($fingerprint, IncidentChange::Opened);

                return;
            } catch (UniqueConstraintViolationException) {
                // Opened by another process meanwhile: a repeat, then.
                $existing = $this->findState($fingerprint);
            }
        }

        $reopened = IncidentStatus::Resolved === $existing['status'];
        $metadata = $this->metadata();
        $column = $metadata->getColumnName(...);
        $set = [
            $column('occurrences').' = '.$column('occurrences').' + 1',
            $column('lastSeenAt').' = :now',
            $column('message').' = :message',
            $column('context').' = :context',
        ];
        $parameters = ['now' => $now, 'message' => $message, 'context' => $context, 'fingerprint' => $fingerprint];

        if (IncidentStatus::Ignored !== $existing['status'] && ($reopened || $severity->isAtLeast($existing['severity']))) {
            $set[] = $column('severity').' = :severity';
            $parameters['severity'] = $severity->value;
        }

        if ($reopened) {
            array_push(
                $set,
                $column('status').' = :status',
                $column('reopenings').' = '.$column('reopenings').' + 1',
                $column('statusChangedAt').' = :now',
                $metadata->getSingleAssociationJoinColumnName('statusChangedBy').' = NULL',
                $column('note').' = NULL',
            );
            $parameters['status'] = IncidentStatus::Open->value;
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.$metadata->getTableName().' SET '.implode(', ', $set).' WHERE '.$column('fingerprint').' = :fingerprint',
            $parameters,
            ['now' => Types::DATETIME_IMMUTABLE, 'context' => Types::JSON]
        );

        if ($reopened) {
            $this->dispatch($fingerprint, IncidentChange::Reopened);
        }
    }

    /**
     * What failed works again: the active incident of this source and code is
     * resolved, by nobody. Cheap when there is none — a single update —, as it
     * comes with every call that works.
     */
    public function recover(
        IncidentSource $source,
        string $code,
        ?string $discriminator = null
    ): void {
        $fingerprint = $source->fingerprint($code, $discriminator);
        $metadata = $this->metadata();
        $column = $metadata->getColumnName(...);

        $resolved = $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.$metadata->getTableName()
            .' SET '.$column('status').' = :resolved, '.$column('statusChangedAt').' = :now, '
            .$metadata->getSingleAssociationJoinColumnName('statusChangedBy').' = NULL, '.$column('note').' = NULL'
            .' WHERE '.$column('fingerprint').' = :fingerprint AND '.$column('status').' IN (:active)',
            [
                'resolved' => IncidentStatus::Resolved->value,
                'now' => $this->now(),
                'fingerprint' => $fingerprint,
                'active' => array_map(static fn (IncidentStatus $status): string => $status->value, IncidentStatus::active()),
            ],
            ['now' => Types::DATETIME_IMMUTABLE, 'active' => ArrayParameterType::STRING]
        );

        if (0 < $resolved) {
            $this->dispatch($fingerprint, IncidentChange::Resolved);
        }
    }

    public function acknowledge(
        Incident $incident,
        ?UserEntityInterface $by,
        ?string $note = null
    ): void {
        $this->decide($incident, IncidentStatus::Acknowledged, IncidentChange::Acknowledged, $by, $note);
    }

    public function resolve(
        Incident $incident,
        ?UserEntityInterface $by,
        ?string $note = null
    ): void {
        $this->decide($incident, IncidentStatus::Resolved, IncidentChange::Resolved, $by, $note);
    }

    public function ignore(
        Incident $incident,
        ?UserEntityInterface $by,
        ?string $note = null
    ): void {
        $this->decide($incident, IncidentStatus::Ignored, IncidentChange::Ignored, $by, $note);
    }

    private function decide(
        Incident $incident,
        IncidentStatus $status,
        IncidentChange $change,
        ?UserEntityInterface $by,
        ?string $note
    ): void {
        $incident->changeStatus($status, $by, '' === trim((string) $note) ? null : trim($note), $this->now());
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new IncidentChangedEvent($incident, $change, $by));
    }

    /**
     * @return array{status: IncidentStatus, severity: Severity}|null
     */
    private function findState(string $fingerprint): ?array
    {
        $metadata = $this->metadata();
        $column = $metadata->getColumnName(...);
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT '.$column('status').' AS status, '.$column('severity').' AS severity FROM '.$metadata->getTableName()
            .' WHERE '.$column('fingerprint').' = :fingerprint',
            ['fingerprint' => $fingerprint]
        );

        return false === $row ? null : ['status' => IncidentStatus::from($row['status']), 'severity' => Severity::from($row['severity'])];
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function insert(
        string $fingerprint,
        IncidentSource $source,
        string $code,
        Severity $severity,
        string $message,
        array $context,
        DateTimeImmutable $now
    ): void {
        $column = $this->metadata()->getColumnName(...);

        $this->entityManager->getConnection()->insert(
            $this->metadata()->getTableName(),
            [
                $column('id') => Uuid::v7(),
                $column('fingerprint') => $fingerprint,
                $column('sourceType') => $source->type,
                $column('sourceKey') => $source->key,
                $column('code') => $code,
                $column('severity') => $severity->value,
                $column('status') => IncidentStatus::Open->value,
                $column('message') => $message,
                $column('context') => $context,
                $column('occurrences') => 1,
                $column('reopenings') => 0,
                $column('firstSeenAt') => $now,
                $column('lastSeenAt') => $now,
            ],
            [
                $column('id') => UuidType::NAME,
                $column('context') => Types::JSON,
                $column('firstSeenAt') => Types::DATETIME_IMMUTABLE,
                $column('lastSeenAt') => Types::DATETIME_IMMUTABLE,
            ]
        );
    }

    /**
     * Read back as an entity, so that listeners get it whole.
     */
    private function dispatch(
        string $fingerprint,
        IncidentChange $change
    ): void {
        $incident = $this->repository->findOneBy(['fingerprint' => $fingerprint]);
        // Written through the connection: what the identity map holds may be stale.
        $this->entityManager->refresh($incident);
        $this->dispatcher->dispatch(new IncidentChangedEvent($incident, $change));
    }

    private function metadata(): ClassMetadata
    {
        return $this->entityManager->getClassMetadata(Incident::class);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
