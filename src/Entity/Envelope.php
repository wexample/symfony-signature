<?php

namespace Wexample\SymfonySignature\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Traits\HasDateCreatedTrait;
use Wexample\SymfonyHelpers\Entity\Traits\LinkedToEntityTrait;
use Wexample\SymfonySignature\Enum\EnvelopeStatus;
use Wexample\SymfonySignature\Repository\EnvelopeRepository;

/**
 * What is signed together: documents of the media library, data — an
 * amount, the parties —, the entity of the application it is about, and its
 * signers. Sealed, its manifest is frozen and its fingerprint — the SHA-256
 * of the manifest — is what every signer signs.
 *
 * Changed through EnvelopeService only: the rules — a draft alone changes,
 * the documents are checked before each signature — are there.
 */
#[ORM\Entity(repositoryClass: EnvelopeRepository::class)]
#[ORM\Table(name: 'signature_envelope')]
#[ORM\Index(name: 'signature_envelope_owner', columns: ['owner_type', 'owner_id'])]
class Envelope extends AbstractEntity
{
    use HasDateCreatedTrait;
    use LinkedToEntityTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: EnvelopeStatus::class)]
    private EnvelopeStatus $status = EnvelopeStatus::DRAFT;

    /** Who sent it, by type and id. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ownerType = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $ownerId = null;

    /**
     * What is signed beside the documents.
     *
     * @var array<string, scalar|null>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    /** The signers in their order, each waiting for the one before. */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $ordered = false;

    /**
     * What was sealed, frozen: the documents and their SHA-256, the data, the
     * signers.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $manifest = null;

    /** The SHA-256 of the manifest, in hexadecimal: what is signed. */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $fingerprint = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dateSealed = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dateClosed = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    /**
     * @var Collection<int, EnvelopeDocument>
     */
    #[ORM\OneToMany(targetEntity: EnvelopeDocument::class, mappedBy: 'envelope', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $documents;

    /**
     * @var Collection<int, EnvelopeSigner>
     */
    #[ORM\OneToMany(targetEntity: EnvelopeSigner::class, mappedBy: 'envelope', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $signers;

    public function __construct(string $title)
    {
        parent::__construct();

        $this->title = $title;
        $this->documents = new ArrayCollection();
        $this->signers = new ArrayCollection();
        $this->setDateCreatedNow();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): EnvelopeStatus
    {
        return $this->status;
    }

    public function setStatus(EnvelopeStatus $status): static
    {
        $this->status = $status;

        if ($status->isClosed()) {
            $this->dateClosed ??= new DateTimeImmutable();
        }

        return $this;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwner(?string $type, ?string $id): static
    {
        $this->ownerType = $type;
        $this->ownerId = $id;

        return $this;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, scalar|null> $metadata
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function isOrdered(): bool
    {
        return $this->ordered;
    }

    public function setOrdered(bool $ordered): static
    {
        $this->ordered = $ordered;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getManifest(): ?array
    {
        return $this->manifest;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function seal(array $manifest, string $fingerprint): static
    {
        $this->manifest = $manifest;
        $this->fingerprint = $fingerprint;
        $this->dateSealed = new DateTimeImmutable();
        $this->status = EnvelopeStatus::PENDING;

        return $this;
    }

    public function getDateSealed(): ?DateTimeImmutable
    {
        return $this->dateSealed;
    }

    public function getDateClosed(): ?DateTimeImmutable
    {
        return $this->dateClosed;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * @return Collection<int, EnvelopeDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(EnvelopeDocument $document): static
    {
        $this->documents->add($document);

        return $this;
    }

    public function removeDocument(EnvelopeDocument $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    /**
     * @return Collection<int, EnvelopeSigner>
     */
    public function getSigners(): Collection
    {
        return $this->signers;
    }

    public function addSigner(EnvelopeSigner $signer): static
    {
        $this->signers->add($signer);

        return $this;
    }

    public function removeSigner(EnvelopeSigner $signer): static
    {
        $this->signers->removeElement($signer);

        return $this;
    }
}
