<?php

namespace Wexample\SymfonySignature\Event;

use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Enum\EnvelopeEventType;

/**
 * Something that happened to an envelope, after it was flushed; the signer
 * concerned, where there is one.
 */
final class EnvelopeEvent
{
    public function __construct(
        public readonly EnvelopeEventType $type,
        public readonly Envelope $envelope,
        public readonly ?EnvelopeSigner $signer = null,
    ) {
    }
}
