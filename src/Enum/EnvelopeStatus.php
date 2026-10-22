<?php

namespace Wexample\SymfonySignature\Enum;

/**
 * Where an envelope stands. Its documents, data and signers change while it
 * is a draft only: sealed, it is `pending` until every signer signed —
 * `completed` —, one declined, its sender cancelled it, or it expired.
 */
enum EnvelopeStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case DECLINED = 'declined';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function isClosed(): bool
    {
        return ! in_array($this, [self::DRAFT, self::PENDING], true);
    }
}
