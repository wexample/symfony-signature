<?php

namespace Wexample\SymfonySignature\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonySignature\Entity\PersonalSignature;

/**
 * The image an account signs with by default, a media of its library: set
 * once, offered at each signature.
 */
class PersonalSignatureService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SignatureKeyService $keys,
    ) {
    }

    public function get(object $owner): ?Media
    {
        return $this->find($owner)?->getMedia();
    }

    public function set(object $owner, Media $media): void
    {
        $signature = $this->find($owner);

        if ($signature) {
            $signature->setMedia($media);
        } else {
            $this->entityManager->persist(new PersonalSignature(...[...$this->keys->key($owner), $media]));
        }

        $this->entityManager->flush();
    }

    private function find(object $owner): ?PersonalSignature
    {
        [$type, $id] = $this->keys->key($owner);

        return $this->entityManager->getRepository(PersonalSignature::class)->findOneBy(['ownerType' => $type, 'ownerId' => $id]);
    }
}
