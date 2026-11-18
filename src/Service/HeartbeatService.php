<?php

namespace Wexample\SymfonyMonitoring\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyCheck\Enum\Severity;
use Wexample\SymfonyMonitoring\Class\HeartbeatDefinition;
use Wexample\SymfonyMonitoring\Class\IncidentSource;
use Wexample\SymfonyMonitoring\Entity\Heartbeat;
use Wexample\SymfonyMonitoring\Repository\HeartbeatRepository;

/**
 * Heartbeats: what must keep running tells it ran — beat() —, and the watch,
 * every ten minutes on the Scheduler, opens a `missed` incident for each one
 * without a success for longer than its interval. A success closes it; a run
 * reported as failed opens `failed`, which the next success closes too.
 *
 * Written through the connection, as IncidentService writes its reports: a beat
 * comes at the end of someone else's work.
 */
class HeartbeatService
{
    public const string CODE_MISSED = 'missed';

    public const string CODE_FAILED = 'failed';

    /**
     * @param array<string, array{label: string|null, every: string, severity: string}> $heartbeats
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HeartbeatRepository $repository,
        private readonly IncidentService $incidents,
        #[Autowire('%wexample_symfony_monitoring.heartbeats%')]
        private readonly array $heartbeats,
    ) {
    }

    /**
     * @return array<string, HeartbeatDefinition> by key
     */
    public function getDefinitions(): array
    {
        $definitions = [];

        foreach ($this->heartbeats as $key => $heartbeat) {
            $definitions[$key] = new HeartbeatDefinition(
                $key,
                $heartbeat['label'] ?? $key,
                DateInterval::createFromDateString($heartbeat['every']),
                Severity::from($heartbeat['severity'])
            );
        }

        return $definitions;
    }

    /**
     * @param string $message why it failed, when it did
     */
    public function beat(
        string $key,
        bool $success = true,
        string $message = ''
    ): void {
        $definition = $this->getDefinitions()[$key] ?? throw new LogicException(sprintf('No heartbeat "%s": declare it under wexample_symfony_monitoring.heartbeats.', $key));
        $this->watchSince($key);
        $now = $this->now();
        $metadata = $this->entityManager->getClassMetadata(Heartbeat::class);
        $column = $success ? 'lastSuccessAt' : 'lastFailureAt';

        $this->entityManager->getConnection()->update(
            $metadata->getTableName(),
            [$metadata->getColumnName($column) => $now],
            [$metadata->getColumnName('key') => $key],
            [$metadata->getColumnName($column) => Types::DATETIME_IMMUTABLE]
        );

        $source = IncidentSource::heartbeat($key);

        if ($success) {
            $this->incidents->recover($source, self::CODE_MISSED);
            $this->incidents->recover($source, self::CODE_FAILED);

            return;
        }

        $this->incidents->raise($source, self::CODE_FAILED, $definition->severity, $message);
    }

    /**
     * Opens `missed` for each heartbeat late on its interval: measured from its
     * last success, or from when it was first watched for one never run.
     */
    #[AsPeriodicTask('10 minutes')]
    public function watch(): void
    {
        $heartbeats = $this->repository->findAllByKey();

        foreach ($this->getDefinitions() as $key => $definition) {
            $heartbeat = $heartbeats[$key] ?? null;
            $since = $heartbeat?->getLastSuccessAt() ?? $heartbeat?->getWatchedSince() ?? $this->watchSince($key);

            if ($since->add($definition->every) < $this->now()) {
                $this->incidents->raise(
                    IncidentSource::heartbeat($key),
                    self::CODE_MISSED,
                    $definition->severity,
                    sprintf('No success since %s.', $since->format(DATE_ATOM)),
                    ['since' => $since->format(DATE_ATOM)]
                );
            }
        }
    }

    /**
     * The row of $key, created on its first beat or watch.
     */
    private function watchSince(string $key): DateTimeImmutable
    {
        $metadata = $this->entityManager->getClassMetadata(Heartbeat::class);
        $column = $metadata->getColumnName(...);
        $connection = $this->entityManager->getConnection();
        $since = $connection->fetchOne(
            'SELECT '.$column('watchedSince').' FROM '.$metadata->getTableName().' WHERE '.$column('key').' = :key',
            ['key' => $key]
        );

        if (false !== $since) {
            return new DateTimeImmutable($since, new DateTimeZone('UTC'));
        }

        $now = $this->now();
        $connection->insert(
            $metadata->getTableName(),
            [$column('id') => Uuid::v7(), $column('key') => $key, $column('watchedSince') => $now],
            [$column('id') => UuidType::NAME, $column('watchedSince') => Types::DATETIME_IMMUTABLE]
        );

        return $now;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
