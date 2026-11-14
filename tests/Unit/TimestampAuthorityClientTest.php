<?php

declare(strict_types=1);

namespace Wexample\SymfonySignature\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wexample\SymfonySignature\Service\TimestampAuthorityClient;

/**
 * The authority's answers are built here, in DER, the way a TimeStampResp is
 * laid out: a status, then the token, which carries the hash it stamps.
 */
final class TimestampAuthorityClientTest extends TestCase
{
    private const string SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);

        if ($length < 0x80) {
            return chr($tag).chr($length).$content;
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr($tag).chr(0x80 | strlen($bytes)).$bytes.$content;
    }

    /**
     * A TimeStampResp of $status whose token carries $sha256, padded to make
     * its lengths take the long form when asked.
     */
    private static function response(
        string $status,
        string $sha256 = self::SHA256,
        int $padding = 0
    ): string {
        $token = self::der(0x30, self::der(0x04, hex2bin($sha256)).self::der(0x04, str_repeat("\x00", $padding)));

        return self::der(0x30, self::der(0x30, self::der(0x02, $status)).$token);
    }

    public static function answers(): array
    {
        return [
            'granted' => [self::response("\x00"), true],
            'granted with modifications' => [self::response("\x01"), true],
            'granted, lengths in the long form' => [self::response("\x00", padding: 300), true],
            'rejection' => [self::response("\x02"), false],
            'waiting' => [self::response("\x03"), false],
            'revocation warning' => [self::response("\x04"), false],
            'status on two bytes' => [self::response("\x00\x00"), false],
            'status not an integer' => [self::der(0x30, self::der(0x30, self::der(0x04, "\x00"))), false],
            'no status info' => [self::der(0x30, self::der(0x02, "\x00")), false],
            'not a sequence' => [self::der(0x04, 'granted'), false],
            'cut after the tags' => ["\x30\x05\x30\x03", false],
            'an html page' => ['<html>Service unavailable</html>', false],
            'nothing' => ['', false],
        ];
    }

    #[DataProvider('answers')]
    public function testIsGranted(string $der, bool $granted): void
    {
        $this->assertSame($granted, TimestampAuthorityClient::isGranted($der));
    }

    public function testATokenMatchesTheHashItWasGrantedFor(): void
    {
        $client = $this->client();
        $token = base64_encode(self::response("\x00"));

        $this->assertTrue($client->matches($token, self::SHA256));
        $this->assertFalse($client->matches($token, hash('sha256', 'another document')));
    }

    public function testARefusedOrUnreadableTokenMatchesNothing(): void
    {
        $client = $this->client();

        $this->assertFalse($client->matches(base64_encode(self::response("\x02")), self::SHA256));
        $this->assertFalse($client->matches('not base64 at all!', self::SHA256));
        $this->assertFalse($client->matches('', self::SHA256));
    }

    public function testTheQueryAsksForTheHashWithAFreshNonce(): void
    {
        $hash = hex2bin(self::SHA256);
        $query = TimestampAuthorityClient::buildQuery($hash);

        $this->assertSame("\x30", $query[0]);
        $this->assertSame(strlen($query) - 2, ord($query[1]));
        // version 1, then the imprint: SHA-256's OID and the hash.
        $this->assertStringStartsWith("\x02\x01\x01\x30\x31\x30\x0d\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01\x05\x00\x04\x20".$hash, substr($query, 2));
        // certReq true closes it.
        $this->assertStringEndsWith("\x01\x01\xFF", $query);
        $this->assertNotSame($query, TimestampAuthorityClient::buildQuery($hash));

        // The nonce is a positive INTEGER: its first byte never has the sign bit.
        $nonce = substr($query, 2 + 3 + 0x33, -3);
        $this->assertSame("\x02", $nonce[0]);
        $this->assertLessThan(0x80, ord($nonce[2]));
    }

    public function testAGrantedTimestampIsKeptInBase64(): void
    {
        $sent = null;
        $client = $this->client(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = $options;

            return new MockResponse(self::response("\x00"));
        });

        $this->assertSame(base64_encode(self::response("\x00")), $client->stamp(self::SHA256));
        $this->assertContains('Content-Type: application/timestamp-query', $sent['headers']);
    }

    public function testARefusalOrAnOutageGivesNoTimestamp(): void
    {
        $this->assertNull($this->client(fn () => new MockResponse(self::response("\x02")))->stamp(self::SHA256));
        $this->assertNull($this->client(fn () => new MockResponse('', ['http_code' => 503]))->stamp(self::SHA256));
    }

    public function testWithoutAnAuthorityNothingIsAsked(): void
    {
        $client = new TimestampAuthorityClient(
            new MockHttpClient(fn () => $this->fail('Nothing to ask.')),
            new NullLogger(),
            ['url' => null, 'timeout' => 5.0]
        );

        $this->assertFalse($client->isEnabled());
        $this->assertNull($client->stamp(self::SHA256));
    }

    private function client(?callable $responses = null): TimestampAuthorityClient
    {
        return new TimestampAuthorityClient(
            new MockHttpClient($responses ?? fn () => new MockResponse('')),
            new NullLogger(),
            ['url' => 'https://tsa.test/', 'timeout' => 5.0]
        );
    }
}
