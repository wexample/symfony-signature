<?php

namespace Wexample\SymfonySignature\Service;

use RuntimeException;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeDocument;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;

/**
 * What an envelope is, written so that the same envelope always gives the
 * same bytes: keys sorted, no whitespace, unicode and slashes as they are.
 * Its SHA-256 is the fingerprint every signer signs.
 */
class EnvelopeManifestService
{
    public const int VERSION = 1;

    public function __construct(
        private readonly MediaStorage $storage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Envelope $envelope): array
    {
        return [
            'version' => self::VERSION,
            'envelope' => (string) $envelope->getId(),
            'title' => $envelope->getTitle(),
            'metadata' => $envelope->getMetadata(),
            'subject' => $envelope->hasLinkedEntity()
                ? ['type' => $envelope->getEntityType(), 'id' => (string) $envelope->getEntityId()]
                : null,
            'ordered' => $envelope->isOrdered(),
            'documents' => array_map(fn (EnvelopeDocument $document) => [
                'position' => $document->getPosition(),
                'name' => $document->getMedia()->getName(),
                'mime' => $document->getMedia()->getMimeType(),
                'size' => $document->getMedia()->getSize(),
                'sha256' => $this->hashMedia($document->getMedia()),
            ], array_values($envelope->getDocuments()->toArray())),
            'signers' => array_map(static fn (EnvelopeSigner $signer) => [
                'position' => $signer->getPosition(),
                'name' => $signer->getName(),
                'email' => $signer->getEmail(),
                'account' => $signer->isOutside() ? null : ['type' => $signer->getAccountType(), 'id' => $signer->getAccountId()],
            ], array_values($envelope->getSigners()->toArray())),
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function canonicalize(array $manifest): string
    {
        return json_encode(self::sortKeys($manifest), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function fingerprint(array $manifest): string
    {
        return hash('sha256', $this->canonicalize($manifest));
    }

    public function hashMedia(Media $media): string
    {
        $path = $this->storage->getPath($media);

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('The file of media "%s" is missing.', $media->getId()));
        }

        return hash_file('sha256', $path);
    }

    /**
     * Objects sorted by key, lists left in their order.
     */
    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
