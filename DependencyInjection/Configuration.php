<?php

namespace Mabe\BackupBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
                ->arrayNode('jobs')
                    ->requiresAtLeastOneElement()
                    ->validate()
                        ->always()
                        ->then(function ($jobs){
                            foreach (array_keys($jobs) as $jobName) {
                                if (!empty($jobName) && in_array($jobName, array('jobs', 'entities', 'entity', 'local', 'gaufrette'))) {
                                    throw new InvalidConfigurationException($jobName.' is a reserved word.');
                                }
                            }
                            return $jobs;
                        })
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('entities')
                                ->useAttributeAsKey('entity')
                                ->arrayPrototype()
                                    ->validate()
                                        ->always()
                                        ->then(function ($entities){
                                            if(!empty($entities['properties']) && !empty($entities['groups'])) {
                                                throw new InvalidConfigurationException('Entity properties and groups cannot be configured at the same time.');
                                            }
                                        })
                                    ->end()
                                    ->children()
                                        ->arrayNode('groups')
                                            ->prototype('scalar')->end()
                                        ->end()
                                        ->arrayNode('properties')
                                            ->prototype('scalar')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('local')->beforeNormalization()
                                ->ifString()
                                    ->then(function ($dir) {
                                        if (substr($dir, -1) === '/') {
                                            return $dir;
                                        } else {
                                            return $dir.'/';
                                        }
                                })->end()
                            ->end()
                            ->arrayNode('gaufrette')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->always()
                            ->then(function ($jobs){
                                $local = false; $gaufrette = false;
                                foreach ($jobs as $jobProperty => $jobValue) {
                                    if($jobProperty === 'local') {
                                        $local = true;
                                    }
                                    if($jobProperty === 'gaufrette' && !empty($jobValue)) {
                                        $gaufrette = true;
                                    }
                                }
                                if ($local || $gaufrette) {
                                    return $jobs;
                                } else {
                                    throw new InvalidConfigurationException('At least one save location must be specified.');
                                }
                            })
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
