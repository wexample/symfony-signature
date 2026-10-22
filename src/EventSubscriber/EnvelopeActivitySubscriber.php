<?php

namespace Wexample\SymfonySignature\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonySignature\Event\EnvelopeEvent;

/**
 * The history of each envelope, in symfony-activity's `signature` category:
 * sealed, asked, signed, declined, completed. Registered by the extension
 * where symfony-activity is installed; kept as long as the application keeps
 * the category.
 */
class EnvelopeActivitySubscriber implements EventSubscriberInterface
{
    public const string CATEGORY = 'signature';

    public function __construct(
        private readonly ActivityRecorder $recorder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [EnvelopeEvent::class => 'onEnvelopeEvent'];
    }

    public function onEnvelopeEvent(EnvelopeEvent $event): void
    {
        $signer = $event->signer;

        $this->recorder->record(
            $event->envelope,
            self::CATEGORY,
            $event->type->value,
            array_filter([
                'title' => $event->envelope->getTitle(),
                'signer' => $signer?->getEmail(),
                'position' => $signer?->getPosition(),
                'hash' => $signer?->getHash(),
            ], static fn ($value) => $value !== null),
            $signer && ! $signer->isOutside() ? new ActivitySubject($signer->getAccountType(), $signer->getAccountId()) : null,
        );
    }
}
