# symfony-signature

Version: 2.0.8

Electronic signature of an envelope — documents of the media library and data, sealed by their fingerprint — by accounts or outside signers, chained, timestamped (RFC 3161) and verifiable

## Table of Contents

- [Installation](#installation)

## Installation

```bash
composer require wexample/symfony-signature
```

```php
// config/bundles.php
Wexample\SymfonyMedia\WexampleSymfonyMediaBundle::class => ['all' => true],
Wexample\SymfonySignature\WexampleSymfonySignatureBundle::class => ['all' => true],
```

The bundle maps its tables — `signature_envelope`, `signature_envelope_document`, `signature_envelope_signer`, `signature_personal` —: generate a migration after installing.

Where symfony-activity is installed, each envelope keeps its history in the `signature` category — declare it (`wexample_symfony_activity.categories.signature`). Where symfony-notification is installed, its categories `signature.requested`, `signature.completed` and `signature.declined` are declared by this bundle, and the signers and the sender are told. Neither is required.

The pages — composing an envelope, signing, verifying — are symfony-signature-ds's.
