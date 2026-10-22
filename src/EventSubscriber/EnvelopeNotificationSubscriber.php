<?php

namespace Wexample\SymfonySignature\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;
use Wexample\SymfonyNotification\Service\NotificationService;
use Wexample\SymfonyNotification\Service\NotificationTextService;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Enum\EnvelopeEventType;
use Wexample\SymfonySignature\Event\EnvelopeEvent;
use Wexample\SymfonySignature\Service\SignerAccessService;

/**
 * Who is told what, through symfony-notification: a signer when its
 * signature is awaited — an account in its inbox and by mail, someone
 * outside by mail, with its signed link —; the sender and the signers once
 * every one signed; the sender when one declined. Registered by the
 * extension where symfony-notification is installed.
 */
class EnvelopeNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly SignerAccessService $access,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [EnvelopeEvent::class => 'onEnvelopeEvent'];
    }

    public function onEnvelopeEvent(EnvelopeEvent $event): void
    {
        $envelope = $event->envelope;
        $parameters = ['title' => $envelope->getTitle()];

        match ($event->type) {
            EnvelopeEventType::SIGNATURE_REQUESTED => $this->notifications->notify(
                'signature.requested',
                [$this->getRecipient($event->signer)],
                $envelope,
                $parameters + [NotificationTextService::PARAMETER_URL => $this->access->getUrl($event->signer)],
            ),
            EnvelopeEventType::COMPLETED => $this->notifications->notify(
                'signature.completed',
                [...array_filter([$this->getOwner($envelope)]), ...array_map($this->getRecipient(...), $envelope->getSigners()->toArray())],
                $envelope,
                $parameters,
            ),
            EnvelopeEventType::DECLINED => $this->notifications->notify(
                'signature.declined',
                array_filter([$this->getOwner($envelope)]),
                $envelope,
                $parameters + ['signer' => $event->signer?->getName(), 'reason' => (string) $event->signer?->getDeclineReason()],
            ),
            default => null,
        };
    }

    /**
     * Its account, or — outside — the signer itself, which has an address.
     */
    private function getRecipient(EnvelopeSigner $signer): object
    {
        return $signer->isOutside()
            ? $signer
            : ($this->find($signer->getAccountType(), $signer->getAccountId()) ?? $signer);
    }

    private function getOwner(Envelope $envelope): ?object
    {
        return $this->find($envelope->getOwnerType(), $envelope->getOwnerId());
    }

    private function find(?string $type, ?string $id): ?object
    {
        try {
            return $type && $id && class_exists($type) ? $this->entityManager->find($type, $id) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
