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


use Go\Aop\Aspect;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class GoAopExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return 'http://go.aopphp.com/xsd-schema/go-aop-bundle';
    }

    /**
     * {@inheritdoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__ . '/../Resources/config/schema';
    }

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
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
        $loader->load('commands.xml');

        $configurator = new Configuration();
        $config       = $this->processConfiguration($configurator, $config);

        $normalizedOptions = array();
        foreach ($config['options'] as $optionKey => $value) {
            // this will convert 'under_scores' into 'underScores'
            $optionKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $optionKey))));
            $normalizedOptions[$optionKey] = $value;
        }
        $container->setParameter('goaop.options', $normalizedOptions);

        if ($config['cache_warmer']) {
            $definition = $container->getDefinition('goaop.cache.warmer');
            $definition->addTag('kernel.cache_warmer');
        }

        if ($config['doctrine_support']) {
            $container
                ->getDefinition('goaop.bridge.doctrine.metadata_load_interceptor')
                ->addTag('doctrine.event_subscriber');
        }

        // Service autoconfiguration is available in Symfony 3.3+
        if (method_exists($container, 'registerForAutoconfiguration')) {
            $container
                ->registerForAutoconfiguration(Aspect::class)
                ->addTag('goaop.aspect');
        }
    }
}
