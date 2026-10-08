<?php

namespace Wexample\SymfonySignature\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wexample\SymfonyHelpers\Entity\Interfaces\AbstractEntityInterface;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeDocument;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Enum\EnvelopeEventType;
use Wexample\SymfonySignature\Enum\EnvelopeSignerStatus;
use Wexample\SymfonySignature\Enum\EnvelopeStatus;
use Wexample\SymfonySignature\Enum\SignatureRefusal;
use Wexample\SymfonySignature\Event\EnvelopeEvent;
use Wexample\SymfonySignature\Exception\SignatureException;
use Wexample\SymfonySignature\Interface\SignerAccountResolverInterface;

/**
 * An envelope from its draft to its last signature:
 *
 *     $envelope = $envelopes->create('Contract 2026-14', $sender, ['amount' => '1200.00'], $contract);
 *     $envelopes->addDocument($envelope, $media);
 *     $envelopes->addSigner($envelope, 'ada@example.com', 'Ada', $adaAccount);
 *     $envelopes->addSigner($envelope, 'client@example.org', 'The client');   // outside: signs by link
 *     $envelopes->seal($envelope);
 *     $envelopes->sign($signer, $signatureImage, $request);
 *
 * Each change is flushed, then told as an EnvelopeEvent.
 */
class EnvelopeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EnvelopeManifestService $manifests,
        private readonly TimestampAuthorityClient $timestamps,
        private readonly SignatureKeyService $keys,
        private readonly ?SignerAccountResolverInterface $accounts = null,
    ) {
    }

    /**
     * @param array<string, scalar|null> $metadata what is signed beside the documents
     */
    public function create(string $title, ?object $owner = null, array $metadata = [], ?AbstractEntityInterface $subject = null, bool $ordered = false): Envelope
    {
        $envelope = (new Envelope($title))
            ->setMetadata($metadata)
            ->setOrdered($ordered);

        if ($owner) {
            $envelope->setOwner(...$this->keys->key($owner));
        }

        if ($subject) {
            $envelope->setLinkedEntity($subject);
        }

        $this->entityManager->persist($envelope);
        $this->entityManager->flush();

        return $envelope;
    }

    /**
     * @throws SignatureException
     */
    public function addDocument(Envelope $envelope, Media $media): EnvelopeDocument
    {
        $this->assertDraft($envelope);

        $document = new EnvelopeDocument($envelope, $media, $envelope->getDocuments()->count() + 1);
        $envelope->addDocument($document);
        $this->entityManager->flush();

        return $document;
    }

    /**
     * An account of the application, or — without one, and none found for
     * the address by the application's SignerAccountResolverInterface —
     * someone outside it, who signs through a signed link.
     *
     * @throws SignatureException
     */
    public function addSigner(Envelope $envelope, string $email, string $name, ?object $account = null): EnvelopeSigner
    {
        $this->assertDraft($envelope);

        foreach ($envelope->getSigners() as $signer) {
            if ($signer->getEmail() === mb_strtolower(trim($email))) {
                throw new SignatureException(SignatureRefusal::DUPLICATE_SIGNER);
            }
        }

        $signer = new EnvelopeSigner($envelope, $envelope->getSigners()->count() + 1, $email, $name);
        // Added by its address alone: its account, where the application
        // knows one.
        $account ??= $this->accounts?->findAccount($email);

        if ($account) {
            $signer->setAccount(...$this->keys->key($account));
        }

        $envelope->addSigner($signer);
        $this->entityManager->flush();

        return $signer;
    }

    /**
     * @throws SignatureException
     */
    public function removeSigner(EnvelopeSigner $signer): void
    {
        $envelope = $signer->getEnvelope();
        $this->assertDraft($envelope);
        $envelope->removeSigner($signer);
        $this->entityManager->flush();
    }

    /**
     * @throws SignatureException
     */
    public function removeDocument(EnvelopeDocument $document): void
    {
        $envelope = $document->getEnvelope();
        $this->assertDraft($envelope);
        $envelope->removeDocument($document);
        $this->entityManager->flush();
    }

    /**
     * Freezes the envelope: its manifest — the SHA-256 of each document
     * among it — and the fingerprint of the manifest. Then asks the first
     * signer, or every one where the order does not matter.
     *
     * @throws SignatureException
     */
    public function seal(Envelope $envelope): void
    {
        $this->assertDraft($envelope);

        if ($envelope->getDocuments()->isEmpty() || $envelope->getSigners()->isEmpty()) {
            throw new SignatureException(SignatureRefusal::INCOMPLETE);
        }

        $manifest = $this->manifests->build($envelope);

        foreach ($envelope->getDocuments() as $index => $document) {
            $document->setSha256($manifest['documents'][$index]['sha256']);
        }

        $envelope->seal($manifest, $this->manifests->fingerprint($manifest));
        $this->entityManager->flush();

        $this->dispatch(EnvelopeEventType::SEALED, $envelope);

        foreach ($this->getAwaited($envelope) as $signer) {
            $this->dispatch(EnvelopeEventType::SIGNATURE_REQUESTED, $envelope, $signer);
        }
    }

    /**
     * Signs, once the documents are checked to be still the ones sealed.
     * The signature links to the previous one in the chain, and gets a
     * trusted timestamp where an authority is set.
     *
     * @param Media|null $image the image signed with, kept beside the signature
     *
     * @throws SignatureException
     */
    public function sign(EnvelopeSigner $signer, ?Media $image = null, ?Request $request = null): void
    {
        $envelope = $signer->getEnvelope();
        $this->assertMaySign($signer);

        foreach ($envelope->getDocuments() as $document) {
            if ($this->manifests->hashMedia($document->getMedia()) !== $document->getSha256()) {
                throw new SignatureException(SignatureRefusal::TAMPERED);
            }
        }

        // Without its microseconds, which the database drops.
        $now = new DateTimeImmutable();
        $date = $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
        $previousHash = $this->getLastHash($envelope);
        $imageSha256 = $image ? $this->manifests->hashMedia($image) : null;
        $hash = $this->chainHash($envelope, $signer, $previousHash, $date, $imageSha256);

        $signer->markSigned(
            $date,
            $request?->getClientIp(),
            $request?->headers->get('User-Agent'),
            $image,
            $imageSha256,
            $previousHash,
            $hash,
            $this->timestamps->stamp($hash),
        );

        $complete = ! $envelope->getSigners()->exists(static fn ($key, EnvelopeSigner $other) => $other->getStatus() !== EnvelopeSignerStatus::SIGNED);

        if ($complete) {
            $envelope->setStatus(EnvelopeStatus::COMPLETED);
        }

        $this->entityManager->flush();
        $this->dispatch(EnvelopeEventType::SIGNED, $envelope, $signer);

        if ($complete) {
            $this->dispatch(EnvelopeEventType::COMPLETED, $envelope);

            return;
        }

        // In order, the next one is asked now.
        if ($envelope->isOrdered()) {
            foreach ($this->getAwaited($envelope) as $next) {
                $this->dispatch(EnvelopeEventType::SIGNATURE_REQUESTED, $envelope, $next);
            }
        }
    }

    /**
     * One signer declining declines the envelope: it will not be complete.
     *
     * @throws SignatureException
     */
    public function decline(EnvelopeSigner $signer, ?string $reason = null, ?Request $request = null): void
    {
        $this->assertMaySign($signer);

        $signer->markDeclined($reason, new DateTimeImmutable(), $request?->getClientIp());
        $signer->getEnvelope()->setStatus(EnvelopeStatus::DECLINED);
        $this->entityManager->flush();

        $this->dispatch(EnvelopeEventType::DECLINED, $signer->getEnvelope(), $signer);
    }

    /**
     * @throws SignatureException
     */
    public function cancel(Envelope $envelope): void
    {
        if ($envelope->getStatus()->isClosed()) {
            throw new SignatureException(SignatureRefusal::NOT_PENDING);
        }

        $envelope->setStatus(EnvelopeStatus::CANCELLED);
        $this->entityManager->flush();
        $this->dispatch(EnvelopeEventType::CANCELLED, $envelope);
    }

    /**
     * Closes the pending envelopes whose expiry passed; for a scheduled task.
     *
     * @return int how many
     */
    public function expireDue(?DateTimeImmutable $now = null): int
    {
        $expired = $this->entityManager->getRepository(Envelope::class)->findExpired($now ?? new DateTimeImmutable());

        foreach ($expired as $envelope) {
            $envelope->setStatus(EnvelopeStatus::EXPIRED);
        }

        $this->entityManager->flush();

        foreach ($expired as $envelope) {
            $this->dispatch(EnvelopeEventType::EXPIRED, $envelope);
        }

        return count($expired);
    }

    /**
     * The signers whose signature is expected now: every pending one, or
     * the first pending one where they sign in order.
     *
     * @return list<EnvelopeSigner>
     */
    public function getAwaited(Envelope $envelope): array
    {
        if ($envelope->getStatus() !== EnvelopeStatus::PENDING) {
            return [];
        }

        $pending = array_values(array_filter(
            $envelope->getSigners()->toArray(),
            static fn (EnvelopeSigner $signer) => $signer->getStatus() === EnvelopeSignerStatus::PENDING
        ));

        return $envelope->isOrdered() ? array_slice($pending, 0, 1) : $pending;
    }

    /**
     * The hash of one signature: the envelope's fingerprint, the previous
     * signature's hash, who signed, when, with which image.
     */
    public function chainHash(Envelope $envelope, EnvelopeSigner $signer, string $previousHash, DateTimeImmutable $date, ?string $imageSha256): string
    {
        return hash('sha256', implode("\n", [
            $envelope->getFingerprint(),
            $previousHash,
            (string) $signer->getPosition(),
            (string) $signer->getEmail(),
            // To the second, as the database keeps it, and in UTC: read back in
            // the application's timezone, it is still the same instant.
            $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            $imageSha256 ?? '',
        ]));
    }

    /**
     * The last link of the chain: the hash of the latest signature, or the
     * fingerprint before the first.
     */
    public function getLastHash(Envelope $envelope): string
    {
        $last = null;

        foreach ($envelope->getSigners() as $signer) {
            if ($signer->getStatus() === EnvelopeSignerStatus::SIGNED
                && ($last === null || $signer->getDateSigned() > $last->getDateSigned())) {
                $last = $signer;
            }
        }

        return $last?->getHash() ?? (string) $envelope->getFingerprint();
    }

    /**
     * @throws SignatureException
     */
    private function assertMaySign(EnvelopeSigner $signer): void
    {
        if ($signer->getEnvelope()->getStatus() !== EnvelopeStatus::PENDING) {
            throw new SignatureException(SignatureRefusal::NOT_PENDING);
        }

        if ($signer->getStatus() !== EnvelopeSignerStatus::PENDING) {
            throw new SignatureException(SignatureRefusal::ALREADY_DONE);
        }

        if (! in_array($signer, $this->getAwaited($signer->getEnvelope()), true)) {
            throw new SignatureException(SignatureRefusal::NOT_YOUR_TURN);
        }
    }

    /**
     * @throws SignatureException
     */
    private function assertDraft(Envelope $envelope): void
    {
        if ($envelope->getStatus() !== EnvelopeStatus::DRAFT) {
            throw new SignatureException(SignatureRefusal::NOT_A_DRAFT);
        }
    }

    private function dispatch(EnvelopeEventType $type, Envelope $envelope, ?EnvelopeSigner $signer = null): void
    {
        $this->eventDispatcher->dispatch(new EnvelopeEvent($type, $envelope, $signer));
    }
}
