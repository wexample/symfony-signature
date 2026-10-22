<?php

namespace Wexample\SymfonySignature\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMedia\Entity\Media;

/**
 * A document of an envelope: a media of the library. Its SHA-256, taken
 * when the envelope is sealed, is what says later that the file is still
 * the one signed — and what finds the envelope a file belongs to.
 */
#[ORM\Entity]
#[ORM\Table(name: 'signature_envelope_document')]
#[ORM\Index(name: 'signature_envelope_document_hash', columns: ['sha256'])]
class EnvelopeDocument extends AbstractEntity
{
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $sha256 = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Envelope::class, inversedBy: 'documents')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Envelope $envelope,
        // A media signed cannot be deleted from the library.
        #[ORM\ManyToOne(targetEntity: Media::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private Media $media,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
    ) {
        parent::__construct();
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSha256(): ?string
    {
        return $this->sha256;
    }

    public function setSha256(string $sha256): static
    {
        $this->sha256 = $sha256;

        return $this;
    }
}
