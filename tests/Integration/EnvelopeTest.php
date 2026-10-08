<?php

namespace Wexample\SymfonySignature\Tests\Integration;

use DateTimeImmutable;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonySignature\Enum\EnvelopeSignerStatus;
use Wexample\SymfonySignature\Enum\EnvelopeStatus;
use Wexample\SymfonySignature\Enum\SignatureRefusal;
use Wexample\SymfonySignature\Exception\SignatureException;
use Wexample\SymfonySignature\Service\EnvelopeProofService;
use Wexample\SymfonySignature\Service\EnvelopeVerifier;
use Wexample\SymfonySignature\Service\PersonalSignatureService;
use Wexample\SymfonySignature\Service\TimestampAuthorityClient;

class EnvelopeTest extends AbstractSignatureTestCase
{
    /** @var list<string> the hashes the authority was asked to stamp */
    private array $stamped = [];

    protected function setUp(): void
    {
        parent::setUp();

        // An authority granting every query, its token carrying the hash.
        self::getContainer()->get(\Symfony\Contracts\HttpClient\HttpClientInterface::class)->setResponseFactory(
            function (string $method, string $url, array $options) {
                $hash = substr($options['body'], 24, 32);
                $this->stamped[] = bin2hex($hash);
                $content = "\x30\x03\x02\x01\x00" . "\x04\x20" . $hash;

                return new MockResponse("\x30" . chr(strlen($content)) . $content);
            }
        );
    }

    public function testAnEnvelopeIsSealedThenSignedByAllAndVerified(): void
    {
        [$envelope] = $this->sealedEnvelope();

        $this->assertSame(EnvelopeStatus::PENDING, $envelope->getStatus());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $envelope->getFingerprint());
        $this->assertSame(hash('sha256', 'The contract, version 1.'), $envelope->getDocuments()->first()->getSha256());
        $this->assertSame('client@example.org', $envelope->getManifest()['signers'][1]['email']);
        $this->assertSame(['amount' => '1200.00'], $envelope->getManifest()['metadata']);

        [$ada, $client] = $envelope->getSigners()->toArray();
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Browser']);

        $this->envelopes()->sign($client, null, $request);
        $this->assertSame($envelope->getFingerprint(), $client->getPreviousHash());
        $this->assertSame('203.0.113.7', $client->getIp());

        $image = $this->media('a png, as far as the hash cares', 'signature.png');
        $this->envelopes()->sign($ada, $image);
        $this->assertSame($client->getHash(), $ada->getPreviousHash());
        $this->assertSame(hash('sha256', 'a png, as far as the hash cares'), $ada->getSignatureImageSha256());

        $this->assertSame(EnvelopeStatus::COMPLETED, $envelope->getStatus());
        $this->assertSame([$client->getHash(), $ada->getHash()], $this->stamped);

        $verification = self::getContainer()->get(EnvelopeVerifier::class)->verify($envelope);
        $this->assertTrue($verification->isValid());
        $this->assertSame([1 => true, 2 => true], $verification->timestamps);

        $proof = self::getContainer()->get(EnvelopeProofService::class)->build($envelope);
        $this->assertSame($envelope->getFingerprint(), hash('sha256', $proof['manifest_canonical']));
        $this->assertTrue($proof['verification']['valid']);
    }

    public function testASealedEnvelopeNoLongerChanges(): void
    {
        [$envelope] = $this->sealedEnvelope();

        $this->assertRefused(SignatureRefusal::NOT_A_DRAFT, fn () => $this->envelopes()->addDocument($envelope, $this->media('Another.')));
        $this->assertRefused(SignatureRefusal::NOT_A_DRAFT, fn () => $this->envelopes()->addSigner($envelope, 'eve@example.com', 'Eve'));
        $this->assertRefused(SignatureRefusal::NOT_A_DRAFT, fn () => $this->envelopes()->seal($envelope));
    }

    public function testAnEnvelopeIsSealedWithDocumentsAndSigners(): void
    {
        $envelope = $this->envelopes()->create('Empty');
        $this->assertRefused(SignatureRefusal::INCOMPLETE, fn () => $this->envelopes()->seal($envelope));

        $this->envelopes()->addSigner($envelope, 'ada@example.com', 'Ada');
        $this->assertRefused(SignatureRefusal::DUPLICATE_SIGNER, fn () => $this->envelopes()->addSigner($envelope, 'ADA@example.com', 'Ada again'));
    }

    public function testInOrderEachSignerWaitsForTheOneBefore(): void
    {
        [$envelope] = $this->sealedEnvelope(ordered: true);
        [$ada, $client] = $envelope->getSigners()->toArray();

        $this->assertSame([$ada], $this->envelopes()->getAwaited($envelope));
        $this->assertRefused(SignatureRefusal::NOT_YOUR_TURN, fn () => $this->envelopes()->sign($client));

        $this->envelopes()->sign($ada);
        $this->assertSame([$client], $this->envelopes()->getAwaited($envelope));
        $this->assertRefused(SignatureRefusal::ALREADY_DONE, fn () => $this->envelopes()->sign($ada));
    }

    public function testADocumentChangedAfterTheSealIsNotSigned(): void
    {
        [$envelope] = $this->sealedEnvelope();
        $document = $envelope->getDocuments()->first();
        file_put_contents(self::getContainer()->get(MediaStorage::class)->getPath($document->getMedia()), 'The contract, version 2.');

        $this->assertRefused(SignatureRefusal::TAMPERED, fn () => $this->envelopes()->sign($envelope->getSigners()->first()));

        $verification = self::getContainer()->get(EnvelopeVerifier::class)->verify($envelope);
        $this->assertSame([1 => false], $verification->documents);
        $this->assertFalse($verification->isValid());
    }

    public function testASignatureAlteredAfterwardsBreaksTheVerification(): void
    {
        [$envelope] = $this->sealedEnvelope();
        [$ada, $client] = $envelope->getSigners()->toArray();
        $this->envelopes()->sign($ada);
        $this->envelopes()->sign($client);

        // Rewriting when Ada signed, in the database.
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE signature_envelope_signer SET date_signed = ? WHERE email = ?',
            ['2020-01-01 00:00:00', 'ada@example.com']
        );
        $this->entityManager->clear();
        $envelope = $this->entityManager->find($envelope::class, $envelope->getId());

        $verification = self::getContainer()->get(EnvelopeVerifier::class)->verify($envelope);
        $this->assertSame([1 => false, 2 => true], $verification->signatures);
        $this->assertFalse($verification->isValid());
    }

    public function testOneSignerDecliningDeclinesTheEnvelope(): void
    {
        [$envelope] = $this->sealedEnvelope();
        [$ada, $client] = $envelope->getSigners()->toArray();

        $this->envelopes()->decline($client, 'The amount is wrong.');

        $this->assertSame(EnvelopeStatus::DECLINED, $envelope->getStatus());
        $this->assertSame(EnvelopeSignerStatus::DECLINED, $client->getStatus());
        $this->assertRefused(SignatureRefusal::NOT_PENDING, fn () => $this->envelopes()->sign($ada));
    }

    public function testAFileIsFoundByItsBytes(): void
    {
        [$envelope] = $this->sealedEnvelope();
        $copy = tempnam(sys_get_temp_dir(), 'copy');
        file_put_contents($copy, 'The contract, version 1.');

        $found = self::getContainer()->get(EnvelopeVerifier::class)->findByFile($copy);

        $this->assertCount(1, $found);
        $this->assertSame($envelope, $found[0]->getEnvelope());
    }

    public function testASignatureIsNotStoppedByItsTimestamp(): void
    {
        self::getContainer()->get(\Symfony\Contracts\HttpClient\HttpClientInterface::class)->setResponseFactory(new MockResponse('', ['http_code' => 503]));
        [$envelope] = $this->sealedEnvelope();
        $signer = $envelope->getSigners()->first();

        $this->envelopes()->sign($signer);

        $this->assertSame(EnvelopeSignerStatus::SIGNED, $signer->getStatus());
        $this->assertNull($signer->getTimestampToken());
    }

    public function testTheQueryIsATimeStampReq(): void
    {
        $query = TimestampAuthorityClient::buildQuery(hash('sha256', 'x', true));

        // SEQUENCE { INTEGER 1, SEQUENCE { SEQUENCE { sha256 OID, NULL }, OCTET STRING[32] }, … }
        $this->assertSame("\x30", $query[0]);
        $this->assertStringStartsWith("\x02\x01\x01\x30\x31\x30\x0d\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01\x05\x00\x04\x20", substr($query, 2));
        $this->assertSame(hash('sha256', 'x', true), substr($query, 24, 32));
    }

    public function testPendingEnvelopesExpire(): void
    {
        [$envelope] = $this->sealedEnvelope();
        $envelope->setExpiresAt(new DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $this->assertSame(1, $this->envelopes()->expireDue());
        $this->assertSame(EnvelopeStatus::EXPIRED, $envelope->getStatus());
    }

    public function testAnAccountKeepsItsSignature(): void
    {
        $ada = $this->account('ada@example.com');
        $signatures = self::getContainer()->get(PersonalSignatureService::class);

        $this->assertNull($signatures->get($ada));

        $signatures->set($ada, $first = $this->media('first', 'first.png'));
        $signatures->set($ada, $second = $this->media('second', 'second.png'));

        $this->assertSame($second, $signatures->get($ada));
    }

    private function assertRefused(SignatureRefusal $refusal, callable $action): void
    {
        try {
            $action();
            $this->fail('Not refused.');
        } catch (SignatureException $exception) {
            $this->assertSame($refusal, $exception->refusal);
        }
    }
}
