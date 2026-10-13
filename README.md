# symfony-signature

Version: 2.0.0

## What it is

A simple electronic signature (eIDAS "simple" level: admissible as evidence, the weakest level), with what makes it hold: the exact content signed, who signed it, when, from where, in which order, and a way to check it all again later. A provider-backed signature (Yousign, DocuSign) is a signing mode to add later, for contracts that must hold more.

## An envelope

What is signed together: documents of the media library, data — an amount, the parties —, the entity of the application it is about, and its signers.

```php
$envelope = $envelopes->create('Contract 2026-14', $sender, ['amount' => '1200.00'], $contract, ordered: false);
$envelopes->addDocument($envelope, $media);                         // a Media of symfony-media
$envelopes->addSigner($envelope, 'ada@example.com', 'Ada', $ada);  // an account: signs signed in
$envelopes->addSigner($envelope, 'client@example.org', 'Client');  // outside: signs through a signed link
$envelopes->seal($envelope);
```

Sealing freezes it: its **manifest** — title, data, subject, signers, and each document with its SHA-256 — written canonically (keys sorted, no whitespace), and its **fingerprint**, the SHA-256 of that manifest. Nothing changes afterwards: another version is another envelope. A media signed cannot be deleted from the library.

`draft` → `pending` → `completed`, or `declined` (one signer declined), `cancelled` (`cancel()`), `expired` (`expiresAt` passed; `bin/console signature:expire` in a scheduled task).

## Signing

```php
$envelopes->sign($signer, $signatureImage, $request);   // the image and the request are optional
$envelopes->decline($signer, 'The amount is wrong.', $request);
```

- Each document is hashed again first: a file changed since the seal refuses the signature (`tampered`).
- In order (`ordered: true`), a signer waits for the one before (`not_your_turn`); otherwise all sign in any order. `getAwaited()` names who is expected.
- The signature keeps when (to the second), the IP, the browser, the image signed with and its SHA-256, and its **hash**: `sha256(fingerprint \n previous_hash \n position \n email \n date in UTC \n image_sha256)`, `previous_hash` being the previous signature's — the fingerprint for the first. Reordering, removing or rewriting a signature breaks the chain.
- With `timestamp.url` set, a time-stamping authority (RFC 3161) stamps the hash; its token is kept beside the signature. A refusal or a timeout never stops the signature, it is logged.

Refusals come as `SignatureException`, with `Enum\SignatureRefusal`: `not_a_draft`, `incomplete`, `not_pending`, `already_done`, `not_your_turn`, `tampered`, `duplicate_signer`.

## Checking

- `EnvelopeVerifier::verify($envelope)` recomputes everything from what is stored: each file against its sealed SHA-256, the fingerprint against the manifest, each signature's hash, the chain, each timestamp token against its hash. `isValid()` only when every piece is.
- `EnvelopeVerifier::findByFile($path)`: the envelopes a file belongs to, by its bytes, whatever it is called.
- `EnvelopeProofService::build($envelope)`: the proof to keep apart from the application — the manifest and the exact bytes hashed, the fingerprint, each signature, the hash rule, and today's verification.

The timestamp token is checked here to be granted and to carry the hash; its signature is the authority's, checked with its certificate:

```bash
openssl ts -verify -digest <hash> -in token.tsr -CAfile cacert.pem -untrusted tsa.crt
```

## Outside signers

`SignerAccessService::getUrl($signer)` gives the link to the signing page (`SignatureRoute::SIGN`, given `signer`): signed for an outside signer, valid until the envelope expires — or `link_lifetime` after the seal —; the plain page for an account, which signs signed in. `isValidLink($request)` checks one.

## A personal signature

`PersonalSignatureService::set($account, $media)` keeps the image an account signs with by default — uploaded and cropped in its media library —, `get($account)` offers it again.

## Events

Every step is told as an `Event\EnvelopeEvent` once flushed — `envelope.sealed`, `.signature_requested`, `.signed`, `.declined`, `.completed`, `.cancelled`, `.expired` — with the signer concerned. symfony-activity and symfony-notification follow them when installed; an application can too.

| Event | Told, with symfony-notification |
|---|---|
| `signature_requested` | the signer: an account in its inbox and by mail, an outside one by mail with its signed link |
| `completed` | the sender and every signer |
| `declined` | the sender, with the reason |

## Accounts found by their address

A signer added by its address alone signs from outside, unless the application implements `Interface\SignerAccountResolverInterface`: `findAccount($email)` gives the account the address belongs to, and the signer then signs signed in; `describe($account)` gives the address and the name an account signs as.

## Table of Contents

- [What it is](#what-it-is)
- [An envelope](#an-envelope)
- [Signing](#signing)
- [Checking](#checking)
- [Outside signers](#outside-signers)
- [A personal signature](#a-personal-signature)
- [Events](#events)
- [Accounts found by their address](#accounts-found-by-their-address)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- doctrine/orm: ^3.0
- doctrine/doctrine-bundle: ^2.13 || ^3.0
- symfony/console: ^7.4 || ^8.0
- symfony/event-dispatcher: ^7.4 || ^8.0
- symfony/http-client: ^7.4 || ^8.0
- symfony/http-kernel: ^7.4 || ^8.0
- symfony/routing: ^7.4 || ^8.0
- symfony/uid: ^7.4 || ^8.0
- wexample/symfony-helpers: >=16.0.0
- wexample/symfony-mail: >=2.0.0
- wexample/symfony-media: >=2.0.0
- wexample/symfony-notification: >=2.0.0
- wexample/symfony-activity: >=3.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
