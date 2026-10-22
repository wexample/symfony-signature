<?php

namespace Wexample\SymfonySignature\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyMail\Interface\MailRecipientInterface;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonySignature\Enum\EnvelopeSignerStatus;

/**
 * Someone asked to sign: an account of the application, or someone outside
 * it, known by an address, who signs through a signed link. Once signed, it
 * keeps when, from where, the image signed with, and its link in the chain:
 * the SHA-256 of the envelope's fingerprint, the previous signature's and its
 * own facts — reordering or removing a signature breaks the chain.
 *
 * A mail recipient, so that an outside signer can be notified.
 */
#[ORM\Entity]
#[ORM\Table(name: 'signature_envelope_signer')]
class EnvelopeSigner extends AbstractEntity implements MailRecipientInterface
{
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $accountType = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $accountId = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: EnvelopeSignerStatus::class)]
    private EnvelopeSignerStatus $status = EnvelopeSignerStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dateSigned = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Media $signatureImage = null;

    /** The SHA-256 of the image signed with, kept beside it. */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $signatureImageSha256 = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $previousHash = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $hash = null;

    /** The RFC 3161 token of the hash, DER in base64, where a TSA is set. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $timestampToken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $declineReason = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Envelope::class, inversedBy: 'signers')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Envelope $envelope,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $email,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
    ) {
        parent::__construct();

        $this->email = mb_strtolower(trim($email));
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function getAccountId(): ?string
    {
        return $this->accountId;
    }

    public function setAccount(?string $type, ?string $id): static
    {
        $this->accountType = $type;
        $this->accountId = $id;

        return $this;
    }

    public function isOutside(): bool
    {
        return $this->accountId === null;
    }

    public function getStatus(): EnvelopeSignerStatus
    {
        return $this->status;
    }

    public function getDateSigned(): ?DateTimeImmutable
    {
        return $this->dateSigned;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getSignatureImage(): ?Media
    {
        return $this->signatureImage;
    }

    public function getSignatureImageSha256(): ?string
    {
        return $this->signatureImageSha256;
    }

    public function getPreviousHash(): ?string
    {
        return $this->previousHash;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function getTimestampToken(): ?string
    {
        return $this->timestampToken;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    /**
     * Only for EnvelopeService, which computes the chain.
     */
    public function markSigned(
        DateTimeImmutable $date,
        ?string $ip,
        ?string $userAgent,
        ?Media $image,
        ?string $imageSha256,
        string $previousHash,
        string $hash,
        ?string $timestampToken,
    ): static {
        $this->status = EnvelopeSignerStatus::SIGNED;
        $this->dateSigned = $date;
        $this->ip = $ip;
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, 512);
        $this->signatureImage = $image;
        $this->signatureImageSha256 = $imageSha256;
        $this->previousHash = $previousHash;
        $this->hash = $hash;
        $this->timestampToken = $timestampToken;

        return $this;
    }

    public function markDeclined(?string $reason, DateTimeImmutable $date, ?string $ip): static
    {
        $this->status = EnvelopeSignerStatus::DECLINED;
        $this->declineReason = $reason;
        $this->dateSigned = $date;
        $this->ip = $ip;

        return $this;
    }
}
