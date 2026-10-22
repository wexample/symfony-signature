<?php

namespace Wexample\SymfonySignature\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Service\EnvelopeService;
use Wexample\SymfonySignature\Tests\Fixtures\App\Entity\Account;

abstract class AbstractSignatureTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        (new Filesystem())->remove(self::getContainer()->getParameter('wexample_symfony_media.storage_dir'));
    }

    protected function envelopes(): EnvelopeService
    {
        return self::getContainer()->get(EnvelopeService::class);
    }

    protected function account(string $email): Account
    {
        $account = new Account($email);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    /**
     * A file of the media library holding $content.
     */
    protected function media(string $content, string $name = 'contract.txt'): Media
    {
        $path = tempnam(sys_get_temp_dir(), 'signature');
        file_put_contents($path, $content);

        return self::getContainer()->get(MediaStorage::class)->import($path, $name);
    }

    /**
     * Sealed, with one document, an account and an outside signer.
     *
     * @return array{Envelope, Account}
     */
    protected function sealedEnvelope(bool $ordered = false): array
    {
        $sender = $this->account('sender@example.com');
        $ada = $this->account('ada@example.com');
        $envelopes = $this->envelopes();

        $envelope = $envelopes->create('Contract 14', $sender, ['amount' => '1200.00'], null, $ordered);
        $envelopes->addDocument($envelope, $this->media('The contract, version 1.'));
        $envelopes->addSigner($envelope, 'ada@example.com', 'Ada', $ada);
        $envelopes->addSigner($envelope, 'Client@Example.org', 'The client');
        $envelopes->seal($envelope);

        return [$envelope, $ada];
    }
}
