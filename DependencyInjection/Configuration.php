<?php

namespace Mabe\BackupBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('mabe_backup');

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('model')->defaultNull()->end()
                            ->arrayNode('groups')->defaultValue(array('Default'))
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('local')->defaultNull()->end()
                ->arrayNode('gaufrette')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
