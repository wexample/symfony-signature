<?php

namespace Wexample\SymfonySignature\Enum;

/**
 * What happens to an envelope, dispatched as an EnvelopeEvent: what the
 * history (symfony-activity) and the notifications (symfony-notification)
 * follow, and an application too.
 */
enum EnvelopeEventType: string
{
    case SEALED = 'envelope.sealed';
    case SIGNATURE_REQUESTED = 'envelope.signature_requested';
    case SIGNED = 'envelope.signed';
    case DECLINED = 'envelope.declined';
    case COMPLETED = 'envelope.completed';
    case CANCELLED = 'envelope.cancelled';
    case EXPIRED = 'envelope.expired';
}
