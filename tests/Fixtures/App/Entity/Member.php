<?php

namespace Wexample\SymfonyMonitoring\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyMail\Interface\MailRecipientInterface;

/**
 * Someone in charge: told of incidents, deciding about them.
 */
#[ORM\Entity]
class Member extends AbstractEntity implements UserEntityInterface, UserInterface, MailRecipientInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
        private string $email,
        #[ORM\Column(type: Types::JSON)]
        private array $roles = [],
    ) {
        parent::__construct();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return [...$this->roles, 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
