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


use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class GoAopExtension extends Extension
{

    /**
     * Loads a specific configuration.
     *
     * @param array $config An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     *
     * @api
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $configurator = new Configuration();
        $config       = $this->processConfiguration($configurator, $config);

        $normalizedOptions = array();
        foreach ($config['options'] as $optionKey => $value) {
            // this will convert 'under_scores' into 'underScores'
            $optionKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $optionKey))));
            $normalizedOptions[$optionKey] = $value;
        }
        $container->setParameter('goaop.options', $normalizedOptions);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
    }
}