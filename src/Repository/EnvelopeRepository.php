<?php

namespace Wexample\SymfonySignature\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeDocument;
use Wexample\SymfonySignature\Enum\EnvelopeStatus;

/**
 * @extends ServiceEntityRepository<Envelope>
 */
class EnvelopeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Envelope::class);
    }

    /**
     * Sent by this owner, newest first.
     *
     * @return list<Envelope>
     */
    public function findByOwner(string $ownerType, string $ownerId): array
    {
        return $this->findBy(['ownerType' => $ownerType, 'ownerId' => $ownerId], ['dateCreated' => 'DESC']);
    }

    /**
     * Waiting for this account's signature, or signed by it.
     *
     * @return list<Envelope>
     */
    public function findBySignerAccount(string $accountType, string $accountId): array
    {
        return $this->createQueryBuilder('envelope')
            ->innerJoin('envelope.signers', 'signer')
            ->where('signer.accountType = :type')
            ->andWhere('signer.accountId = :id')
            ->andWhere('envelope.status != :draft')
            ->setParameter('type', $accountType)
            ->setParameter('id', $accountId)
            ->setParameter('draft', EnvelopeStatus::DRAFT)
            ->orderBy('envelope.dateSealed', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * The documents sealed with this SHA-256: where a file was signed.
     *
     * @return list<EnvelopeDocument>
     */
    public function findDocumentsBySha256(string $sha256): array
    {
        return $this->getEntityManager()->getRepository(EnvelopeDocument::class)->findBy(['sha256' => strtolower($sha256)]);
    }

    /**
     * @return list<Envelope>
     */
    public function findExpired(DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('envelope')
            ->where('envelope.status = :pending')
            ->andWhere('envelope.expiresAt IS NOT NULL')
            ->andWhere('envelope.expiresAt <= :now')
            ->setParameter('pending', EnvelopeStatus::PENDING)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
