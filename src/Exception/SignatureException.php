<?php

namespace Wexample\SymfonySignature\Exception;

use RuntimeException;
use Wexample\SymfonySignature\Enum\SignatureRefusal;

/**
 * A change to an envelope, or a signature, the rules refuse. The message is
 * for developers; a page shows the refusal, by its code.
 */
class SignatureException extends RuntimeException
{
    public function __construct(
        public readonly SignatureRefusal $refusal,
    ) {
        parent::__construct(sprintf('Signature refused: %s.', $refusal->value));
    }
}
