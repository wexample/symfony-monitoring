<?php

namespace Wexample\SymfonyMonitoring\Repository;

use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyMonitoring\Entity\Incident;
use Wexample\SymfonyMonitoring\Entity\Traits\Manipulator\IncidentEntityManipulatorTrait;
use Wexample\SymfonyMonitoring\Enum\IncidentStatus;

/**
 * Incidents are written by IncidentService, through the connection: reports come
 * from listeners, in the middle of someone else's unit of work, which a persist
 * and a flush would carry along. What reads them is here.
 *
 * @method Incident|null find($id, $lockMode = null, $lockVersion = null)
 */
class IncidentRepository extends AbstractRepository
{
    use IncidentEntityManipulatorTrait;

    /**
     * The incidents of these statuses — all of them when none is given —, the
     * last seen first.
     *
     * @param list<IncidentStatus> $statuses
     *
     * @return list<Incident>
     */
    public function findByStatuses(
        array $statuses = [],
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->queryByStatuses($statuses)
            ->orderBy('incident.lastSeenAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<IncidentStatus> $statuses
     */
    public function countByStatuses(array $statuses = []): int
    {
        return (int) $this->queryByStatuses($statuses)
            ->select('COUNT(incident.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many incidents stand in each status.
     *
     * @return array<string, int> by status value
     */
    public function countPerStatus(): array
    {
        $counts = array_fill_keys(array_map(static fn (IncidentStatus $status): string => $status->value, IncidentStatus::cases()), 0);

        foreach ($this->createQueryBuilder('incident')
            ->select('incident.status AS status, COUNT(incident.id) AS count')
            ->groupBy('incident.status')
            ->getQuery()
            ->getArrayResult() as $row) {
            $counts[$row['status'] instanceof IncidentStatus ? $row['status']->value : $row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * @param list<IncidentStatus> $statuses
     */
    private function queryByStatuses(array $statuses): QueryBuilder
    {
        $builder = $this->createQueryBuilder('incident');

        if ([] !== $statuses) {
            $builder
                ->where('incident.status IN (:statuses)')
                ->setParameter('statuses', array_map(static fn (IncidentStatus $status): string => $status->value, $statuses));
        }

        return $builder;
    }
}
