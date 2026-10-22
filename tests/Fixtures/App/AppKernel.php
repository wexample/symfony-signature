<?php

namespace Wexample\SymfonySignature\Tests\Fixtures\App;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyMedia\WexampleSymfonyMediaBundle;
use Wexample\SymfonySignature\Tests\Fixtures\App\DependencyInjection\PublicServicesPass;
use Wexample\SymfonySignature\WexampleSymfonySignatureBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

/**
 * Signature alone: no history, no notification.
 */
class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new WexampleSymfonyMediaBundle(),
            new WexampleSymfonySignatureBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__ . '/config/config.yaml'];
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PublicServicesPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
