<?php

namespace Wexample\SymfonySignature\Service;

use Throwable;
use Wexample\SymfonySignature\Class\EnvelopeVerification;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeDocument;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Enum\EnvelopeSignerStatus;
use Wexample\SymfonySignature\Repository\EnvelopeRepository;

/**
 * Checks an envelope again from what it stores: each document's file
 * against its sealed SHA-256, the fingerprint against the manifest, each
 * signature's hash recomputed, the chain from the fingerprint to the last
 * signature, each timestamp token against its hash.
 */
class EnvelopeVerifier
{
    public function __construct(
        private readonly EnvelopeManifestService $manifests,
        private readonly EnvelopeService $envelopes,
        private readonly TimestampAuthorityClient $timestamps,
        private readonly EnvelopeRepository $repository,
    ) {
    }

    public function verify(Envelope $envelope): EnvelopeVerification
    {
        $manifest = $envelope->getManifest();

        if ($manifest === null || $envelope->getFingerprint() === null) {
            return new EnvelopeVerification(false, false, [], [], [], false);
        }

        $documents = [];

        foreach ($envelope->getDocuments() as $document) {
            try {
                $documents[$document->getPosition()] = $this->manifests->hashMedia($document->getMedia()) === $document->getSha256();
            } catch (Throwable) {
                $documents[$document->getPosition()] = false;
            }
        }

        $signed = array_filter(
            $envelope->getSigners()->toArray(),
            static fn (EnvelopeSigner $signer) => $signer->getStatus() === EnvelopeSignerStatus::SIGNED
        );

        $signatures = [];
        $timestamps = [];

        foreach ($signed as $signer) {
            $signatures[$signer->getPosition()] = $signer->getHash() === $this->envelopes->chainHash(
                $envelope,
                $signer,
                (string) $signer->getPreviousHash(),
                $signer->getDateSigned(),
                $signer->getSignatureImageSha256()
            );
            $timestamps[$signer->getPosition()] = $signer->getTimestampToken() === null
                ? null
                : $this->timestamps->matches($signer->getTimestampToken(), (string) $signer->getHash());
        }

        return new EnvelopeVerification(
            true,
            $this->manifests->fingerprint($manifest) === $envelope->getFingerprint(),
            $documents,
            $signatures,
            $timestamps,
            $this->isChained($envelope->getFingerprint(), $signed),
        );
    }

    /**
     * The documents a file was signed as: whatever it is called, its bytes
     * say which envelopes it belongs to.
     *
     * @return list<EnvelopeDocument>
     */
    public function findByFile(string $path): array
    {
        return $this->repository->findDocumentsBySha256(hash_file('sha256', $path));
    }

    /**
     * Each signature points to the one before, the first to the fingerprint,
     * and none is left out.
     *
     * @param array<EnvelopeSigner> $signed
     */
    private function isChained(string $fingerprint, array $signed): bool
    {
        $byPrevious = [];

        foreach ($signed as $signer) {
            if (isset($byPrevious[$signer->getPreviousHash()])) {
                return false;
            }

            $byPrevious[$signer->getPreviousHash()] = $signer;
        }

        $current = $fingerprint;
        $walked = 0;

        while (isset($byPrevious[$current])) {
            $current = (string) $byPrevious[$current]->getHash();
            ++$walked;
        }

        return $walked === count($signed);
    }
}
