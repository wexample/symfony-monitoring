<?php

namespace Wexample\SymfonyMonitoring\Tests\Fixtures\App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyMonitoring\Service\IncidentRecipientService;
use Wexample\SymfonyMonitoring\Tests\Fixtures\App\Entity\Member;

/**
 * Every member, in place of symfony-user's accounts the fixture app does not have.
 */
class MemberRecipientService extends IncidentRecipientService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findRecipients(): array
    {
        return $this->entityManager->getRepository(Member::class)->findAll();
    }
}
