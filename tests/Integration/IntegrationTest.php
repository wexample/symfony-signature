<?php

namespace Wexample\SymfonySignature\Tests\Integration;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyActivity\Repository\ActivityRepository;
use Wexample\SymfonyMail\Service\MailboxService;
use Wexample\SymfonyNotification\Repository\NotificationRepository;
use Wexample\SymfonySignature\Service\SignerAccessService;
use Wexample\SymfonySignature\Tests\Fixtures\App\IntegratedAppKernel;

/**
 * Signature with symfony-activity and symfony-notification installed: each
 * step kept in the envelope's history, each signer told, the outside one by
 * mail with its signed link.
 */
class IntegrationTest extends AbstractSignatureTestCase
{
    protected static function getKernelClass(): string
    {
        return IntegratedAppKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::getContainer()->get(MailboxService::class)->purge();
        self::getContainer()->get(\Symfony\Contracts\HttpClient\HttpClientInterface::class)->setResponseFactory(new MockResponse('', ['http_code' => 503]));
    }

    public function testTheSignersAreToldAndTheOutsideOneSignsThroughItsLink(): void
    {
        [$envelope, $ada] = $this->sealedEnvelope();
        [$adaSigner, $client] = $envelope->getSigners()->toArray();

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $this->assertSame(1, $notifications->countUnread($ada::class, (string) $ada->getId()));
        $this->assertSame(1, $notifications->countUnread($client::class, (string) $client->getId()));

        $link = self::getContainer()->get(MailboxService::class)->last('client@example.org')->getFirstLink();
        $this->assertStringContainsString('/sign/' . $client->getId(), $link);
        $this->assertStringContainsString('_hash=', $link);

        $access = self::getContainer()->get(SignerAccessService::class);
        $this->assertTrue($access->isValidLink(Request::create($link)));
        $this->assertFalse($access->isValidLink(Request::create(str_replace((string) $client->getId(), (string) $adaSigner->getId(), $link))));

        $this->envelopes()->sign($client);
        $this->envelopes()->sign($adaSigner);

        // The sender, Ada and the client told it is complete.
        $sender = $this->entityManager->getRepository($ada::class)->findOneBy(['email' => 'sender@example.com']);
        $this->assertSame(1, $notifications->countUnread($sender::class, (string) $sender->getId()));
        $this->assertSame(2, $notifications->countUnread($ada::class, (string) $ada->getId()));

        $history = self::getContainer()->get(ActivityRepository::class)->paginate(
            new \Wexample\SymfonyActivity\Class\ActivitySubject($envelope::class, (string) $envelope->getId()),
            null,
            1,
            20
        );
        $types = array_map(static fn ($entry) => $entry->getType(), $history['entries']);
        sort($types);
        $this->assertSame([
            'envelope.completed',
            'envelope.sealed',
            'envelope.signature_requested',
            'envelope.signature_requested',
            'envelope.signed',
            'envelope.signed',
        ], $types);
    }
}
