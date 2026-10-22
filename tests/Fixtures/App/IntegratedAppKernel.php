<?php

namespace Wexample\SymfonySignature\Tests\Fixtures\App;

use DH\AuditorBundle\DHAuditorBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyLoader\WexampleSymfonyLoaderBundle;
use Wexample\SymfonyMail\WexampleSymfonyMailBundle;
use Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle;
use Wexample\SymfonySecurity\WexampleSymfonySecurityBundle;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

/**
 * Signature with what follows it when installed: the history of
 * symfony-activity, the notifications of symfony-notification.
 */
class IntegratedAppKernel extends AppKernel
{
    protected function getExtraBundles(): iterable
    {
        return [
            new DHAuditorBundle(),
            new WexampleSymfonySecurityBundle(),
            new WexampleSymfonyLoaderBundle(),
            new WexampleSymfonyTranslationsBundle(),
            new WexampleSymfonyMailBundle(),
            new WexampleSymfonyActivityBundle(),
            new WexampleSymfonyNotificationBundle(),
            ...parent::getExtraBundles(),
        ];
    }

    /**
     * Its routes are not AppKernel's: a cache of its own.
     */
    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '/integrated';
    }

    protected function configuresItsOwnSecurity(): bool
    {
        return true;
    }

    protected function getConfigFiles(): array
    {
        return [...parent::getConfigFiles(), __DIR__ . '/config/integrated.yaml'];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/Controller', 'attribute');
    }
}
