<?php

namespace Wexample\SymfonySignature\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle;
use Wexample\SymfonySignature\EventSubscriber\EnvelopeActivitySubscriber;
use Wexample\SymfonySignature\EventSubscriber\EnvelopeNotificationSubscriber;

class WexampleSymfonySignatureExtension extends AbstractWexampleSymfonyExtension
{
    public const string NOTIFICATION_TEXTS = 'WexampleSymfonySignatureBundle.common.notifications::';

    /**
     * With symfony-notification installed, the categories the signers and
     * the sender are told through: declared here, so that installing both is
     * all there is to do.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        if (! $this->hasBundle($container, WexampleSymfonyNotificationBundle::class)) {
            return;
        }

        $categories = [];

        foreach (['requested', 'completed', 'declined'] as $name) {
            $categories['signature.' . $name] = [
                'title' => self::NOTIFICATION_TEXTS . $name . '.title',
                'body' => self::NOTIFICATION_TEXTS . $name . '.body',
                'label' => self::NOTIFICATION_TEXTS . $name . '.label',
                'group' => self::NOTIFICATION_TEXTS . 'group',
                'level' => $name === 'declined' ? 'warning' : ($name === 'completed' ? 'success' : 'info'),
                'channels' => ['mail'],
            ];
        }

        $container->prependExtensionConfig('wexample_symfony_notification', ['categories' => $categories]);
    }

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_signature.timestamp', $config['timestamp']);
        $container->setParameter('wexample_symfony_signature.link_lifetime', $config['link_lifetime']);

        $this->loadConfig(
            __DIR__,
            $container
        );

        // The history of each envelope, and the notifications of its
        // signers, where the application installed what keeps and sends them.
        if ($this->hasBundle($container, WexampleSymfonyActivityBundle::class)) {
            $container->autowire(EnvelopeActivitySubscriber::class)->setAutoconfigured(true);
        }

        if ($this->hasBundle($container, WexampleSymfonyNotificationBundle::class)) {
            $container->autowire(EnvelopeNotificationSubscriber::class)->setAutoconfigured(true);
        }
    }

    private function hasBundle(ContainerBuilder $container, string $class): bool
    {
        return class_exists($class) && in_array($class, $container->getParameter('kernel.bundles'), true);
    }
}
