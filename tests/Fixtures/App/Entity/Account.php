<?php

namespace Wexample\SymfonySignature\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMail\Interface\MailRecipientInterface;

#[ORM\Entity]
class Account extends AbstractEntity implements UserInterface, MailRecipientInterface
{
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
        private string $email,
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
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
