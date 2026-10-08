<?php

namespace Wexample\SymfonySignature\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_signature');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('timestamp')
                    ->info('A time-stamping authority (RFC 3161) certifying when each signature was made: its token is kept beside the signature. Null, none is asked.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')
                            ->info('Where the query is posted, https://freetsa.org/tsr for instance.')
                            ->defaultNull()
                        ->end()
                        ->floatNode('timeout')->defaultValue(5.0)->end()
                    ->end()
                ->end()
                ->integerNode('link_lifetime')
                    ->info('Seconds the link an outside signer signs through works, when the envelope has no expiry of its own.')
                    ->defaultValue(30 * 86400)
                    ->min(3600)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
