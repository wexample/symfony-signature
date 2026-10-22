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

    // The other pages, and the buttons of an envelope.
    public const string INDEX = 'signature_index';
    public const string CREATE = 'signature_create';
    public const string VERIFY = 'signature_verify';
    public const string PERSONAL = 'signature_personal';
    public const string SEAL = 'signature_envelope_seal';
    public const string CANCEL = 'signature_envelope_cancel';
    public const string PROOF = 'signature_envelope_proof';
    public const string SIGNER_REMOVE = 'signature_envelope_signer_remove';
    public const string DOCUMENT = 'signature_document';
}
