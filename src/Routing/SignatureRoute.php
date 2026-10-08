<?php

namespace Wexample\SymfonySignature\Routing;

/**
 * The pages of signature, symfony-signature-ds's — or the application's
 * under these names.
 */
final class SignatureRoute
{
    /** Where a signer signs, given the signer id as `signer`: signed for an outside one. */
    public const string SIGN = 'signature_sign';

    /** An envelope, given its id as `id`: its documents, its signers, where it stands. */
    public const string ENVELOPE = 'signature_envelope';
}
