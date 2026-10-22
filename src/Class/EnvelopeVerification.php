<?php

namespace Wexample\SymfonySignature\Class;

/**
 * What a verification of an envelope found, piece by piece: valid only when
 * every piece is.
 */
final readonly class EnvelopeVerification
{
    /**
     * @param array<int, bool>    $documents   each document, by position: still the file sealed
     * @param array<int, bool>    $signatures  each signature, by position: its hash recomputed, and in the chain
     * @param array<int, ?bool>   $timestamps  each signature's token, by position: null where it has none
     */
    public function __construct(
        public bool $sealed,
        public bool $fingerprint,
        public array $documents,
        public array $signatures,
        public array $timestamps,
        public bool $chain,
    ) {
    }

    public function isValid(): bool
    {
        return $this->sealed
            && $this->fingerprint
            && $this->chain
            && ! in_array(false, $this->documents, true)
            && ! in_array(false, $this->signatures, true)
            && ! in_array(false, $this->timestamps, true);
    }
}
