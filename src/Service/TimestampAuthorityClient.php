<?php

namespace Wexample\SymfonySignature\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * A trusted timestamp (RFC 3161) of a SHA-256: a time-stamping authority
 * signs that it saw this hash at this time. The query is built here, in DER,
 * with no library; the token comes back as the authority wrote it, kept in
 * base64.
 *
 * What is checked here is that the token was granted and carries the hash.
 * Its signature is the authority's, checked with its certificate:
 *
 *     openssl ts -verify -digest <sha256> -in token.tsr -CAfile cacert.pem -untrusted tsa.crt
 */
class TimestampAuthorityClient
{
    /** 2.16.840.1.101.3.4.2.1, SHA-256. */
    private const string SHA256_OID = "\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01";

    /**
     * @param array{url: ?string, timeout: float} $timestamp
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'wexample_symfony_signature.timestamp')]
        private readonly array $timestamp,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->timestamp['url'] !== null;
    }

    /**
     * The token of $sha256 — hexadecimal —, base64; null without an
     * authority, or when it refused or could not be reached: a signature is
     * never stopped by its timestamp.
     */
    public function stamp(string $sha256): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', $this->timestamp['url'], [
                'headers' => ['Content-Type' => 'application/timestamp-query'],
                'body' => self::buildQuery(hex2bin($sha256)),
                'timeout' => $this->timestamp['timeout'],
            ]);
            $token = $response->getContent();

            if (! self::isGranted($token)) {
                throw new \RuntimeException('The authority did not grant the timestamp.');
            }

            return base64_encode($token);
        } catch (Throwable $exception) {
            $this->logger->warning('Timestamp of a signature failed: {error}', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Whether $token, base64, was granted and carries $sha256.
     */
    public function matches(string $token, string $sha256): bool
    {
        $der = base64_decode($token, true);

        return $der !== false && self::isGranted($der) && str_contains($der, hex2bin($sha256));
    }

    public static function buildQuery(string $hash): string
    {
        $nonce = random_bytes(8);

        if (ord($nonce[0]) & 0x80) {
            $nonce = "\x00" . $nonce;
        }

        $algorithm = self::der(0x30, self::SHA256_OID . "\x05\x00");
        $imprint = self::der(0x30, $algorithm . self::der(0x04, $hash));

        return self::der(0x30,
            self::der(0x02, "\x01")      // version
            . $imprint
            . self::der(0x02, $nonce)    // nonce
            . self::der(0x01, "\xFF")    // certReq: the certificate in the token
        );
    }

    /**
     * TimeStampResp ::= SEQUENCE { status PKIStatusInfo ::= SEQUENCE { status INTEGER, … }, … }:
     * 0 granted, 1 granted with modifications.
     */
    public static function isGranted(string $der): bool
    {
        $offset = 0;

        if (! self::enter($der, $offset, 0x30) || ! self::enter($der, $offset, 0x30)) {
            return false;
        }

        if (($der[$offset] ?? null) !== "\x02") {
            return false;
        }

        $length = ord($der[$offset + 1] ?? "\x00");
        $status = $length === 1 ? ord($der[$offset + 2] ?? "\xFF") : -1;

        return in_array($status, [0, 1], true);
    }

    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);

        if ($length < 0x80) {
            return chr($tag) . chr($length) . $content;
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr($tag) . chr(0x80 | strlen($bytes)) . $bytes . $content;
    }

    /**
     * Moves $offset past the tag and the length of the element there, into
     * its content.
     */
    private static function enter(string $der, int &$offset, int $tag): bool
    {
        if (($der[$offset] ?? null) !== chr($tag)) {
            return false;
        }

        $first = ord($der[$offset + 1] ?? "\x00");
        $offset += 2 + ($first & 0x80 ? $first & 0x7F : 0);

        return true;
    }
}
