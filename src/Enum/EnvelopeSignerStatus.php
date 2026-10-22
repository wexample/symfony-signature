<?php

namespace Wexample\SymfonySignature\Enum;

enum EnvelopeSignerStatus: string
{
    case PENDING = 'pending';
    case SIGNED = 'signed';
    case DECLINED = 'declined';
}
