<?php

namespace Wexample\SymfonyMonitoring\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Repository\AbstractUserRepository;
use Wexample\SymfonyUser\Service\ReversedRoleHierarchyService;

/**
 * Who is told of an incident: the enabled users holding one of the roles of
 * `alerts` — directly, or through a role above it in the hierarchy.
 */
class IncidentRecipientService
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReversedRoleHierarchyService $reversedRoleHierarchy,
        #[Autowire('%wexample_symfony_monitoring.alerts.roles%')]
        private readonly array $roles,
    ) {
    }

    /**
     * @return list<AbstractUser>
     */
    public function findRecipients(): array
    {
        if ([] === $this->roles) {
            return [];
        }

        /** @var AbstractUserRepository $repository */
        $repository = $this->entityManager->getRepository(UserEntityInterface::class);
        $roles = array_values(array_unique(array_merge(...array_map($this->reversedRoleHierarchy->getParentRoles(...), $this->roles))));

        return $repository
            ->queryByRoles($roles)
            ->andWhere('user.enabled = true')
            ->getQuery()
            ->getResult();
    }
}
