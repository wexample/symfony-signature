<?php

namespace Wexample\SymfonySignature\Service;

use Doctrine\Persistence\Proxy;
use LogicException;

/**
 * An account or an owner as an envelope keeps it, by type and id.
 */
class SignatureKeyService
{
    /**
     * @return array{string, string}
     */
    public function key(object $entity): array
    {
        if (! method_exists($entity, 'getId') || null === $entity->getId()) {
            throw new LogicException(sprintf('A %s has no id to be kept by.', $entity::class));
        }

        return [$entity instanceof Proxy ? get_parent_class($entity) : $entity::class, (string) $entity->getId()];
    }

    public function is(object $entity, ?string $type, ?string $id): bool
    {
        if ($type === null || $id === null || ! method_exists($entity, 'getId') || null === $entity->getId()) {
            return false;
        }

        [$entityType, $entityId] = $this->key($entity);

        return $entityId === $id && is_a($entityType, $type, true);
    }
}
