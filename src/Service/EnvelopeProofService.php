<?php

namespace Wexample\SymfonySignature\Service;

use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;

/**
 * What an envelope proves, written out to be kept apart from the application:
 * the manifest as sealed, its fingerprint, each signature with its facts,
 * its hash and its timestamp token, and the verification of today. Anyone
 * holding the documents recomputes it.
 */
class EnvelopeProofService
{
    public function __construct(
        private readonly EnvelopeVerifier $verifier,
        private readonly EnvelopeManifestService $manifests,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Envelope $envelope): array
    {
        $verification = $this->verifier->verify($envelope);

        return [
            'envelope' => (string) $envelope->getId(),
            'title' => $envelope->getTitle(),
            'status' => $envelope->getStatus()->value,
            'sealed_at' => $envelope->getDateSealed()?->format(DATE_ATOM),
            'closed_at' => $envelope->getDateClosed()?->format(DATE_ATOM),
            'manifest' => $envelope->getManifest(),
            // The exact bytes the fingerprint is the SHA-256 of.
            'manifest_canonical' => $envelope->getManifest() ? $this->manifests->canonicalize($envelope->getManifest()) : null,
            'fingerprint' => $envelope->getFingerprint(),
            'signers' => array_map(static fn (EnvelopeSigner $signer) => [
                'position' => $signer->getPosition(),
                'name' => $signer->getName(),
                'email' => $signer->getEmail(),
                'status' => $signer->getStatus()->value,
                'date' => $signer->getDateSigned()?->format('Y-m-d\TH:i:sP'),
                'ip' => $signer->getIp(),
                'user_agent' => $signer->getUserAgent(),
                'signature_image_sha256' => $signer->getSignatureImageSha256(),
                'previous_hash' => $signer->getPreviousHash(),
                'hash' => $signer->getHash(),
                'timestamp_token' => $signer->getTimestampToken(),
                'decline_reason' => $signer->getDeclineReason(),
            ], array_values($envelope->getSigners()->toArray())),
            'verification' => [
                'valid' => $verification->isValid(),
                'fingerprint' => $verification->fingerprint,
                'documents' => $verification->documents,
                'signatures' => $verification->signatures,
                'timestamps' => $verification->timestamps,
                'chain' => $verification->chain,
            ],
            'hash_rule' => 'sha256(fingerprint \n previous_hash \n position \n email \n date \n signature_image_sha256)',
        ];
    }
}
