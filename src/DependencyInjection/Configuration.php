<?php

namespace Naoned\OaiPmhServerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** {@inheritDoc} */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('naoned_oai_pmh_server');
        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode('count_per_load')
            ->defaultValue(50)
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
