<?php

namespace Wexample\SymfonySignature\Enum;

/**
 * Why an envelope or a signature was refused: the stable code a page turns
 * into its message.
 */
enum SignatureRefusal: string
{
    /** Its documents, data or signers change while it is a draft only. */
    case NOT_A_DRAFT = 'not_a_draft';

    /** Sealed with no document or no signer. */
    case INCOMPLETE = 'incomplete';

    /** Signed or declined while the envelope is not waiting for signatures. */
    case NOT_PENDING = 'not_pending';

    /** This signer signed or declined already. */
    case ALREADY_DONE = 'already_done';

    /** Signers in order, and one before this one has not signed yet. */
    case NOT_YOUR_TURN = 'not_your_turn';

    /** A document no longer is what was sealed: nothing is signed. */
    case TAMPERED = 'tampered';

    /** The signer added twice. */
    case DUPLICATE_SIGNER = 'duplicate_signer';
}
