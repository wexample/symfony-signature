<?php

namespace Wexample\SymfonySignature\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * What an envelope is about.
 */
#[ORM\Entity]
class Contract extends AbstractEntity
{
    public function __construct(
        #[ORM\Column(type: Types::STRING)]
        private string $reference,
    ) {
        parent::__construct();
    }
}
