<?php

namespace Wexample\SymfonySignature\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMedia\Entity\Media;

/**
 * The signature an account signs with by default: an image of its media
 * library, uploaded once, cropped, then offered at each signature.
 */
#[ORM\Entity]
#[ORM\Table(name: 'signature_personal')]
#[ORM\UniqueConstraint(name: 'signature_personal_owner', columns: ['owner_type', 'owner_id'])]
class PersonalSignature extends AbstractEntity
{
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $ownerType,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private string $ownerId,
        #[ORM\ManyToOne(targetEntity: Media::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Media $media,
    ) {
        parent::__construct();
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function setMedia(Media $media): static
    {
        $this->media = $media;

        return $this;
    }
}
