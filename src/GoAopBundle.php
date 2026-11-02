<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle;

use Go\Aop\Aspect;
use Go\Bridge\Doctrine\MetadataLoadInterceptor;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer;
use Go\Symfony\GoAopBundle\DataCollector\AspectDataCollector;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\DoctrineIntegrationPass;
use Go\Symfony\GoAopBundle\DependencyInjection\KernelOptionsResolver;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Integrates the Go! AOP framework into a Symfony application.
 *
 * The aspect kernel is initialized when this bundle boots, which is why the bundle must be the
 * first one in config/bundles.php: weaving applies to classes loaded after the initialization,
 * and bundles boot in registration order.
 *
 * @phpstan-import-type BundleConfig from KernelOptionsResolver
 */
final class GoAopBundle extends AbstractBundle
{
    /**
     * Parameter of the container dumper that makes the compiled container `include_once` the source files of
     * services directly, bypassing the class loader and thereby the weaving. It is disabled by default here
     * and can be enabled again (at the cost of weaving) through a parameter in the application configuration.
     */
    public const string INLINE_CLASS_LOADER_PARAMETER = '.container.dumper.inline_class_loader';

    protected string $extensionAlias = 'go_aop';

    public function build(ContainerBuilder $container): void
    {
        $this->assertRegisteredFirst($container);

        // Set before the application configuration is loaded, so a parameter defined there still wins
        if (!$container->hasParameter(self::INLINE_CLASS_LOADER_PARAMETER)) {
            $container->setParameter(self::INLINE_CLASS_LOADER_PARAMETER, false);
        }

        // After the autoconfiguration passes (priority 100), which apply the "goaop.aspect" tag to aspects
        $container->addCompilerPass(new AspectCollectorPass());
        $container->addCompilerPass(new DoctrineIntegrationPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $featureNames = KernelOptionsResolver::featureNames();

        $rootNode = $definition->rootNode();
        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new LogicException('The root node of the "go_aop" configuration must be an array node.');
        }

        $rootNode
            ->fixXmlConfig('include_path')
            ->fixXmlConfig('exclude_path')
            ->fixXmlConfig('feature')
            ->children()
                ->booleanNode('debug')
                    ->info('Development mode: changed sources and aspects are woven again on the next request, breakpoints work in the original files. Keep it off in production.')
                    ->defaultValue('%kernel.debug%')
                ->end()
                ->scalarNode('app_dir')
                    ->info('Application root: only files below it can be woven, cache paths mirror source paths relative to it.')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.project_dir%')
                ->end()
                ->scalarNode('cache_dir')
                    ->info('Directory for woven sources, proxies and advisor caches. Anchor it on %kernel.cache_dir% or %kernel.build_dir% so cache:clear and cache:warmup manage it.')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.cache_dir%/aop')
                ->end()
                ->scalarNode('cache_file_mode')
                    ->info('Permissions of the cache files, as an octal literal (0o770), an octal string ("0770") or an integer. Defaults to 0770 minus the umask.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn(mixed $value): bool => KernelOptionsResolver::cacheFileMode($value) === null)
                        ->thenInvalid('The cache file mode %s is not a valid octal file mode between 0600 and 0777.')
                    ->end()
                ->end()
                ->arrayNode('include_paths')
                    ->info('Directories that are woven. Keep the list narrow: weaving vendor code is slow and rarely needed.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(['%kernel.project_dir%/src'])
                ->end()
                ->arrayNode('exclude_paths')
                    ->info('Directories below the include paths that are never woven (the cache directory and the framework are always excluded).')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('features')
                    ->info(sprintf('Framework features to enable, by the name of the Go\Aop\Features constant: %s.', implode(', ', $featureNames)))
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->beforeNormalization()
                        ->ifString()->then(static fn(string $value): array => [$value])
                    ->end()
                    ->validate()
                        ->always(static function (array $features) use ($featureNames): array {
                            $normalized = [];
                            foreach ($features as $feature) {
                                if (!is_string($feature) || !in_array(strtoupper($feature), $featureNames, true)) {
                                    throw new InvalidConfigurationException(sprintf(
                                        'Unknown Go! AOP feature %s, known features are: %s.',
                                        json_encode($feature),
                                        implode(', ', $featureNames),
                                    ));
                                }
                                $normalized[] = strtoupper($feature);
                            }

                            return array_values(array_unique($normalized));
                        })
                    ->end()
                ->end()
                ->scalarNode('kernel_class')
                    ->info('Aspect kernel class, extend Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel to customize the transformation pipeline.')
                    ->cannotBeEmpty()
                    ->defaultValue(AspectSymfonyKernel::class)
                    ->validate()
                        ->ifTrue(static fn(mixed $class): bool => !is_string($class) || !is_a($class, AspectSymfonyKernel::class, true))
                        ->thenInvalid(sprintf('The kernel class %%s must extend "%s".', AspectSymfonyKernel::class))
                    ->end()
                ->end()
                ->booleanNode('cache_warmer')
                    ->info('Weave every file below the include paths during cache:warmup and cache:clear, so no weaving happens at runtime.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('doctrine')
                    ->info('Wire woven entities into Doctrine ORM (entity discovery through the woven classes, configuration checks for intercepted entity properties). Enabled automatically when DoctrineBundle is registered.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    /**
     * @phpstan-param BundleConfig $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.php');

        $options = KernelOptionsResolver::fromConfig($config);
        $container->parameters()->set('goaop.options', $options);
        $container->parameters()->set('goaop.kernel_class', $config['kernel_class']);
        $container->parameters()->set('goaop.cache_dir_anchor', KernelOptionsResolver::cacheDirAnchor($config['cache_dir']));

        $builder->getDefinition(AspectKernel::class)
            ->setClass($config['kernel_class'])
            ->setFactory([$config['kernel_class'], 'create']);

        $builder->registerForAutoconfiguration(Aspect::class)->addTag(AspectCollectorPass::ASPECT_TAG);

        if ($config['cache_warmer'] !== true) {
            $builder->removeDefinition(AspectCacheWarmer::class);
        }

        $bundles = $builder->getParameter('kernel.bundles');
        $bundles = is_array($bundles) ? $bundles : [];

        if (isset($bundles['WebProfilerBundle'])) {
            $builder->register(AspectDataCollector::class)
                ->addArgument(new Reference(AspectKernel::class))
                ->addTag('data_collector', ['template' => '@GoAop/Collector/aspect.html.twig', 'id' => AspectDataCollector::NAME, 'priority' => 240]);
        }

        $doctrineEnabled = $config['doctrine'] ?? isset($bundles['DoctrineBundle']);
        if ($doctrineEnabled === true) {
            if (!class_exists(MetadataLoadInterceptor::class)) {
                throw new LogicException('The Doctrine integration of GoAopBundle needs the Doctrine bridge of goaop/framework, update the framework.');
            }
            $builder->register(MetadataLoadInterceptor::class)
                ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
            $builder->setParameter('.goaop.doctrine', true);
        }
    }

    public function boot(): void
    {
        if ($this->container === null) {
            throw new LogicException('GoAopBundle can not boot without a container.');
        }
        // Initializes the aspect kernel: everything loaded from now on goes through the weaver
        $this->container->get(AspectKernel::class);
    }

    /**
     * Bundles boot in registration order, and only classes loaded after this bundle booted are woven
     */
    private function assertRegisteredFirst(ContainerBuilder $container): void
    {
        $bundles  = $container->getParameter('kernel.bundles');
        $metadata = $container->hasParameter('kernel.bundles_metadata') ? $container->getParameter('kernel.bundles_metadata') : [];
        if (!is_array($bundles) || !is_array($metadata)) {
            return;
        }
        foreach (array_keys($bundles) as $name) {
            // The internal bundles of the Symfony components (ServicesBundle, ConsoleBundle, ...) hold no code of the application
            $namespace = is_array($metadata[$name] ?? null) ? ($metadata[$name]['namespace'] ?? null) : null;
            if (is_string($namespace) && str_starts_with($namespace, 'Symfony\\Component\\')) {
                continue;
            }
            if ($name === $this->getName()) {
                return;
            }
            break;
        }
        if (!isset($name)) {
            return;
        }

        throw new LogicException(sprintf(
            'GoAopBundle must be the first bundle in config/bundles.php, but "%s" is registered before it. '
            . 'Classes loaded before the aspect kernel is initialized can not be woven. '
            . 'Move the line "%s::class => [\'all\' => true]," to the top of the array returned by config/bundles.php '
            . '(or yield the bundle first in Kernel::registerBundles()). Symfony Flex appends new bundles at the end '
            . 'of config/bundles.php, so this is needed once after "composer require goaop/goaop-symfony-bundle".',
            (string) $name,
            self::class,
        ));
    }
}
