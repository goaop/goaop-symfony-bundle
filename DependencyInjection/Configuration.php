<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\DependencyInjection;


use Go\Aop\Features;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\Kernel;

class Configuration implements ConfigurationInterface
{
    const CONFIGURATION_ROOT_NODE = 'go_aop';

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::CONFIGURATION_ROOT_NODE);
        $rootNode = $this->getRootNode($treeBuilder);
        $features = (new \ReflectionClass('Go\Aop\Features'))->getConstants();

        $rootNode
            ->children()
                ->booleanNode('cache_warmer')->defaultTrue()->end()
                ->booleanNode('doctrine_support')->defaultFalse()->end()
                ->arrayNode('options')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('feature', 'features')
                    ->fixXmlConfig('include_path', 'include_paths')
                    ->fixXmlConfig('exclude_path', 'exclude_paths')
                    ->children()
                        ->scalarNode('features')
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(function ($v) use ($features) {
                                    $featureMask = 0;
                                    foreach ($v as $featureName) {
                                        $featureName = strtoupper($featureName);
                                        if (!isset($features[$featureName])) {
                                            throw new InvalidConfigurationException("Uknown feature: {$featureName}");
                                        }
                                        $featureMask |= $features[$featureName];
                                    }

                                    return $featureMask;
                                })
                            ->end()
                            ->defaultValue(0)
                        ->end()
                        ->scalarNode('app_dir')->defaultValue('%kernel.root_dir%/../src')->end()
                        ->scalarNode('cache_dir')->defaultValue('%kernel.cache_dir%/aspect')->end()
                        ->scalarNode('cache_file_mode')->end()
                        ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                        ->scalarNode('container_class')->end()
                        ->arrayNode('include_paths')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('exclude_paths')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @param TreeBuilder $treeBuilder
     *
     * @return ArrayNodeDefinition
     */
    private function getRootNode(TreeBuilder $treeBuilder)
    {
        if (Kernel::VERSION_ID >= 40200) {
            return $treeBuilder->getRootNode();
        }

        return $treeBuilder->root(self::CONFIGURATION_ROOT_NODE);
    }
}
