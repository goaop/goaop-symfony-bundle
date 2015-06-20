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
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('go_aop');
        $features = (new \ReflectionClass('Go\Aop\Features'))->getConstants();

        $rootNode
            ->children()
                ->scalarNode('features')
//                    ->validate()
//                    ->ifTrue(function ($v) use ($features) {
//                        if (is_array($v)) {
//                            foreach ($v as $featureName) {
//                                $featureName = strtoupper($featureName);
//                                if (!isset($features[$featureName])) {
//                                    return true;
//                                }
//                            }
//                        }
//
//                        return true;
//                    })
//                    ->thenInvalid('Invalid feature definition %s')
//                    ->end()

//                    ->beforeNormalization()
//                        ->ifArray()
//                        ->then(function ($v) use ($features) {
//                            $featureMask = 0;
//                            foreach ($v as $featureName) {
//                                $featureName = strtoupper($featureName);
//                                $featureMask += isset($features[$featureName]) ? $features[$featureName] : 0;
//                            }
//
//                            return $featureMask;
//                        })
//                    ->end()
                    ->defaultValue(AspectSymfonyKernel::getDefaultFeatures())
                ->end()
                ->scalarNode('appDir')->defaultValue('%kernel.root_dir%/../src')->end()
                ->scalarNode('cacheDir')->defaultValue('%kernel.cache_dir%/aspect')->end()
                ->scalarNode('cacheFileMode')->end()
                ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                ->scalarNode('containerClass')->end()
                ->arrayNode('includePaths')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('excludePaths')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}