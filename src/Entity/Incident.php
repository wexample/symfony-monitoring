<?php

namespace Wexample\SymfonyMonitoring\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyCheck\Enum\Severity;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyMonitoring\Enum\IncidentStatus;
use Wexample\SymfonyMonitoring\Repository\IncidentRepository;

/**
 * Something that went wrong and is followed until it is dealt with: one row per
 * fingerprint — a source, a code, what tells two of them apart —, its repeats
 * counted on it, opened again when it comes back after being resolved.
 *
 * Reports are written by IncidentService straight through the connection, as
 * they come from listeners in the middle of someone else's unit of work; a
 * person's decisions — acknowledge, resolve, ignore — go through the entity.
 */
#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'monitoring_incident')]
#[ORM\UniqueConstraint(name: 'monitoring_incident_fingerprint', columns: ['fingerprint'])]
#[ORM\Index(name: 'monitoring_incident_status', columns: ['status', 'last_seen_at'])]
#[ORM\Index(name: 'monitoring_incident_source', columns: ['source_type', 'source_key'])]
class Incident extends AbstractEntity
{
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    /**
     * `remote`, `heartbeat`, or a type of the application's.
     */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $sourceType;

    /**
     * The remote's key, the heartbeat's.
     */
    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $sourceKey;

    /**
     * Stable, what a list filters on and a translation reads: `call_failed`,
     * `misdated_report`, `missed`.
     */
    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: Severity::class)]
    private Severity $severity;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: IncidentStatus::class)]
    private IncidentStatus $status = IncidentStatus::Open;

    /**
     * The last report's words.
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    /**
     * The last report's context.
     *
     * @var array<string, scalar|null>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $occurrences = 1;

    /**
     * How many times it came back after being resolved.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $reopenings = 0;

    /**
     * UTC.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $firstSeenAt;

    /**
     * UTC.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSeenAt;

    /**
     * UTC; when the status last changed, by hand or not.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $statusChangedAt = null;

    /**
     * Who changed it last; null when the cause went away by itself.
     */
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserEntityInterface $statusChangedBy = null;

    /**
     * What the person who changed the status had to say.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceKey(): string
    {
        return $this->sourceKey;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    public function getStatus(): IncidentStatus
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getOccurrences(): int
    {
        return $this->occurrences;
    }

    public function getReopenings(): int
    {
        return $this->reopenings;
    }

    public function getFirstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getStatusChangedAt(): ?DateTimeImmutable
    {
        return $this->statusChangedAt;
    }

    public function getStatusChangedBy(): ?UserEntityInterface
    {
        return $this->statusChangedBy;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * A person's decision: acknowledged, resolved, ignored.
     */
    public function changeStatus(
        IncidentStatus $status,
        ?UserEntityInterface $by,
        ?string $note,
        DateTimeImmutable $at
    ): static {
        $this->status = $status;
        $this->statusChangedBy = $by;
        $this->statusChangedAt = $at;
        $this->note = $note;

        return $this;
    }
}
