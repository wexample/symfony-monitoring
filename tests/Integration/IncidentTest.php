<?php

namespace Wexample\SymfonyMonitoring\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\PhpRemote\Class\RemoteReporter;
use Wexample\SymfonyCheck\Enum\Severity;
use Wexample\SymfonyMonitoring\Class\IncidentSource;
use Wexample\SymfonyMonitoring\Entity\Heartbeat;
use Wexample\SymfonyMonitoring\Entity\Incident;
use Wexample\SymfonyMonitoring\Enum\IncidentStatus;
use Wexample\SymfonyMonitoring\Repository\IncidentRepository;
use Wexample\SymfonyMonitoring\Service\HeartbeatService;
use Wexample\SymfonyMonitoring\Service\IncidentPurgeService;
use Wexample\SymfonyMonitoring\Service\IncidentService;
use Wexample\SymfonyMonitoring\Tests\Fixtures\App\Entity\Member;
use Wexample\SymfonyNotification\Entity\Notification;

/**
 * Errors alerted to every member, warnings not; one heartbeat, due daily.
 */
class IncidentTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private IncidentService $incidents;

    private IncidentRepository $repository;

    private Member $member;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->incidents = self::getContainer()->get('test.incident_service');
        $this->repository = self::getContainer()->get('test.incident_repository');
        $this->member = new Member('admin@app.test', ['ROLE_ADMIN']);
        $this->entityManager->persist($this->member);
        $this->entityManager->flush();
    }

    public function testAReportOpensAnIncidentAndItsRepeatsCountOnIt(): void
    {
        $source = IncidentSource::remote('senat_debates');

        $this->incidents->raise($source, 'call_failed', Severity::Error, 'Timeout.', ['exception' => 'TransportException']);
        $this->incidents->raise($source, 'call_failed', Severity::Error, 'Timeout again.');

        $incident = $this->incident($source, 'call_failed');
        $this->assertSame(IncidentStatus::Open, $incident->getStatus());
        $this->assertSame(2, $incident->getOccurrences());
        $this->assertSame('Timeout again.', $incident->getMessage());
        $this->assertSame(1, $this->countAlerts(), 'An incident alerts once per opening.');
    }

    public function testARecoveryResolvesItAndARepeatReopensIt(): void
    {
        $source = IncidentSource::remote('dila_an_debates');

        $this->incidents->raise($source, 'down', Severity::Error, 'HTTP 503.');
        $this->incidents->recover($source, 'down');
        $this->assertSame(IncidentStatus::Resolved, $this->incident($source, 'down')->getStatus());
        $this->assertNull($this->incident($source, 'down')->getStatusChangedBy());

        $this->incidents->raise($source, 'down', Severity::Error, 'HTTP 503.');

        $incident = $this->incident($source, 'down');
        $this->assertSame(IncidentStatus::Open, $incident->getStatus());
        $this->assertSame(1, $incident->getReopenings());
        $this->assertSame(2, $this->countAlerts());
    }

    public function testARecoveryWithoutIncidentDoesNothing(): void
    {
        $this->incidents->recover(IncidentSource::remote('sparql_wikidata'), 'call_failed');

        $this->assertSame(0, $this->repository->countByStatuses());
    }

    public function testAWorseRepeatRaisesTheSeverityAndAWarningAlertsNobody(): void
    {
        $source = IncidentSource::remote('senat_debates');

        $this->incidents->raise($source, 'misdated_report', Severity::Warning, 'Dated after today.', [], 'd20261204.xml');
        $this->assertSame(0, $this->countAlerts());

        $this->incidents->raise($source, 'misdated_report', Severity::Error, 'Dated after today.', [], 'd20261204.xml');
        $this->assertSame(Severity::Error, $this->incident($source, 'misdated_report', 'd20261204.xml')->getSeverity());

        $this->incidents->raise($source, 'misdated_report', Severity::Warning, 'Dated after today.', [], 'd20271204.xml');
        $this->assertSame(2, $this->repository->countByStatuses([IncidentStatus::Open]), 'Two files, two incidents.');
    }

    public function testDecisionsAreKeptWithTheirAuthorAndAnIgnoredIncidentOnlyCounts(): void
    {
        $source = IncidentSource::remote('senat_debates');
        $this->incidents->raise($source, 'misdated_report', Severity::Warning, 'Dated after today.', [], 'd20261204.xml');
        $incident = $this->incident($source, 'misdated_report', 'd20261204.xml');

        $member = $this->entityManager->find(Member::class, $this->member->getId());

        $this->incidents->acknowledge($incident, $member, ' Looking into it. ');
        $this->assertSame(IncidentStatus::Acknowledged, $incident->getStatus());
        $this->assertSame('Looking into it.', $incident->getNote());
        $this->assertSame($member, $incident->getStatusChangedBy());

        $this->incidents->ignore($incident, $member, 'The Sénat will not fix it.');
        $this->incidents->raise($source, 'misdated_report', Severity::Warning, 'Dated after today.', [], 'd20261204.xml');
        $this->incidents->recover($source, 'misdated_report', 'd20261204.xml');

        $incident = $this->incident($source, 'misdated_report', 'd20261204.xml');
        $this->assertSame(IncidentStatus::Ignored, $incident->getStatus());
        $this->assertSame(2, $incident->getOccurrences());
    }

    public function testWhatTheRemotesSignalBecomesIncidents(): void
    {
        /** @var RemoteReporter $reporter */
        $reporter = self::getContainer()->get('test.remote_reporter');

        $reporter->failure('dila_an_debates', 'call_failed', 'Timeout.');
        $reporter->anomaly('senat_debates', 'misdated_report', 'Dated after today.', ['file' => 'd20261204.xml'], 'd20261204.xml');

        $failure = $this->incident(IncidentSource::remote('dila_an_debates'), 'call_failed');
        $this->assertSame(Severity::Error, $failure->getSeverity());
        $anomaly = $this->incident(IncidentSource::remote('senat_debates'), 'misdated_report', 'd20261204.xml');
        $this->assertSame(Severity::Warning, $anomaly->getSeverity());
        $this->assertSame(['file' => 'd20261204.xml'], $anomaly->getContext());

        $reporter->recovery('dila_an_debates', 'call_failed');
        $this->assertSame(IncidentStatus::Resolved, $this->incident(IncidentSource::remote('dila_an_debates'), 'call_failed')->getStatus());
    }

    public function testAHeartbeatLateOnItsIntervalOpensAnIncidentWhichASuccessCloses(): void
    {
        /** @var HeartbeatService $heartbeats */
        $heartbeats = self::getContainer()->get('test.heartbeat_service');
        $source = IncidentSource::heartbeat('import.nightly');

        $heartbeats->watch();
        $this->assertSame(0, $this->repository->countByStatuses(), 'Measured from when it was first watched.');

        $this->entityManager->getConnection()->executeStatement("UPDATE monitoring_heartbeat SET watched_since = '2020-01-01 00:00:00'");
        $heartbeats->watch();
        $this->assertSame(IncidentStatus::Open, $this->incident($source, HeartbeatService::CODE_MISSED)->getStatus());

        $heartbeats->beat('import.nightly', false, 'The source failed.');
        $this->assertSame('The source failed.', $this->incident($source, HeartbeatService::CODE_FAILED)->getMessage());

        $heartbeats->beat('import.nightly');
        $this->assertSame(IncidentStatus::Resolved, $this->incident($source, HeartbeatService::CODE_MISSED)->getStatus());
        $this->assertSame(IncidentStatus::Resolved, $this->incident($source, HeartbeatService::CODE_FAILED)->getStatus());
        $this->assertNotNull($this->entityManager->getRepository(Heartbeat::class)->findOneBy(['key' => 'import.nightly'])->getLastSuccessAt());
    }

    public function testClosedIncidentsAreDeletedAfterTheRetentionActiveOnesNever(): void
    {
        $this->incidents->raise(IncidentSource::remote('old'), 'down', Severity::Error, 'Down.');
        $this->incidents->recover(IncidentSource::remote('old'), 'down');
        $this->incidents->raise(IncidentSource::remote('still'), 'down', Severity::Error, 'Down.');
        $this->entityManager->getConnection()->executeStatement("UPDATE monitoring_incident SET status_changed_at = '2020-01-01 00:00:00', first_seen_at = '2020-01-01 00:00:00'");

        /** @var IncidentPurgeService $purge */
        $purge = self::getContainer()->get('test.purge_service');

        $this->assertSame(1, $purge->purge());
        $this->assertSame(1, $this->repository->countByStatuses([IncidentStatus::Open]));
    }

    private function incident(
        IncidentSource $source,
        string $code,
        ?string $discriminator = null
    ): Incident {
        $this->entityManager->clear();

        return $this->repository->findOneBy(['fingerprint' => $source->fingerprint($code, $discriminator)]);
    }

    private function countAlerts(): int
    {
        return $this->entityManager->getRepository(Notification::class)->count([]);
    }
}
