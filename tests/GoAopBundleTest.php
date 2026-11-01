<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests;

use Go\Bridge\Doctrine\MetadataLoadInterceptor;
use Go\Console\Command\CacheWarmupCommand;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\DoctrineIntegrationPass;
use Go\Symfony\GoAopBundle\GoAopBundle;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GoAopBundleTest extends TestCase
{
    public function testBuildDisablesTheInlineClassLoaderOfTheContainerDumper(): void
    {
        $container = $this->createContainerBuilder();

        new GoAopBundle()->build($container);

        $this->assertFalse($container->getParameter(GoAopBundle::INLINE_CLASS_LOADER_PARAMETER));
        $passes = array_map(static fn(object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $this->assertContains(AspectCollectorPass::class, $passes);
        $this->assertContains(DoctrineIntegrationPass::class, $passes);
    }

    public function testBuildKeepsAnInlineClassLoaderSettingOfTheApplication(): void
    {
        $container = $this->createContainerBuilder();
        $container->setParameter(GoAopBundle::INLINE_CLASS_LOADER_PARAMETER, true);

        new GoAopBundle()->build($container);

        $this->assertTrue($container->getParameter(GoAopBundle::INLINE_CLASS_LOADER_PARAMETER));
    }

    public function testBuildRejectsABundleRegisteredBeforeIt(): void
    {
        $container = $this->createContainerBuilder(['FrameworkBundle' => FrameworkBundle::class, 'GoAopBundle' => GoAopBundle::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('but "FrameworkBundle" is registered before it');

        new GoAopBundle()->build($container);
    }

    public function testInternalComponentBundlesMayPrecedeTheBundle(): void
    {
        $container = $this->createContainerBuilder(
            ['ServicesBundle' => 'Symfony\Component\HttpKernel\Bundle\BundleAdapter', 'GoAopBundle' => GoAopBundle::class],
            ['ServicesBundle' => ['namespace' => 'Symfony\Component\DependencyInjection\Kernel', 'path' => '/vendor']],
        );

        new GoAopBundle()->build($container);

        $this->assertFalse($container->getParameter(GoAopBundle::INLINE_CLASS_LOADER_PARAMETER));
    }

    public function testExtensionRegistersTheKernelServicesFromTheConfiguration(): void
    {
        $container = $this->loadExtension([
            'cache_dir' => '%kernel.build_dir%/go-aop',
            'features'  => ['prebuilt_cache'],
        ]);

        $options = $container->getParameter('goaop.options');
        $this->assertIsArray($options);
        $this->assertSame('%kernel.build_dir%/go-aop', $options['cacheDir']);
        $this->assertSame(64, $options['features']);
        $this->assertSame(['anchor' => 'build', 'suffix' => '/go-aop'], $container->getParameter('goaop.cache_dir_anchor'));

        $kernel = $container->getDefinition(AspectKernel::class);
        $this->assertSame(AspectSymfonyKernel::class, $kernel->getClass());
        $this->assertSame([AspectSymfonyKernel::class, 'create'], $kernel->getFactory());
        $this->assertTrue($kernel->isPublic());
        $this->assertTrue($container->hasDefinition(AspectContainer::class));
        $this->assertTrue($container->hasDefinition(AspectCacheWarmer::class));
        $this->assertTrue($container->hasDefinition(CacheWarmupCommand::class));
        $this->assertSame([[]], $container->getDefinition(CacheWarmupCommand::class)->getTag('console.command'));
        $this->assertFalse($container->hasDefinition(MetadataLoadInterceptor::class));

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(\Go\Aop\Aspect::class, $autoconfigured);
        $this->assertArrayHasKey(AspectCollectorPass::ASPECT_TAG, $autoconfigured[\Go\Aop\Aspect::class]->getTags());
    }

    public function testCustomKernelClassIsUsedForTheKernelService(): void
    {
        $container = $this->loadExtension(['kernel_class' => CustomAspectKernel::class]);

        $kernel = $container->getDefinition(AspectKernel::class);
        $this->assertSame(CustomAspectKernel::class, $kernel->getClass());
        $this->assertSame([CustomAspectKernel::class, 'create'], $kernel->getFactory());
        $this->assertSame(CustomAspectKernel::class, $container->getParameter('goaop.kernel_class'));
    }

    public function testCacheWarmerCanBeDisabled(): void
    {
        $container = $this->loadExtension(['cache_warmer' => false]);

        $this->assertFalse($container->hasDefinition(AspectCacheWarmer::class));
    }

    public function testDoctrineIntegrationIsEnabledWithDoctrineBundle(): void
    {
        $container = $this->loadExtension([], ['GoAopBundle' => GoAopBundle::class, 'DoctrineBundle' => 'Doctrine\Bundle\DoctrineBundle\DoctrineBundle']);

        $listener = $container->getDefinition(MetadataLoadInterceptor::class);
        $this->assertSame([['event' => 'loadClassMetadata']], $listener->getTag('doctrine.event_listener'));
        $this->assertTrue($container->getParameter('.goaop.doctrine'));
    }

    public function testDoctrineIntegrationCanBeDisabledExplicitly(): void
    {
        $container = $this->loadExtension(['doctrine' => false], ['GoAopBundle' => GoAopBundle::class, 'DoctrineBundle' => 'Doctrine\Bundle\DoctrineBundle\DoctrineBundle']);

        $this->assertFalse($container->hasDefinition(MetadataLoadInterceptor::class));
        $this->assertFalse($container->hasParameter('.goaop.doctrine'));
    }

    public function testAspectsAreCollectedWhenTheContainerIsCompiled(): void
    {
        $container = $this->loadExtension();
        $container->register(LoggingAspect::class)->setAutoconfigured(true)->setAutowired(true);
        $container->register(\Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder::class);
        $container->register('service_container', ContainerBuilder::class)->setSynthetic(true);

        $container->compile();

        $this->assertTrue($container->hasDefinition(AspectKernel::class));
        $locator = $container->getDefinition(AspectKernel::class)->getArgument(1);
        if ($locator instanceof \Symfony\Component\DependencyInjection\Reference) {
            $locator = $container->getDefinition((string) $locator);
        }
        $this->assertInstanceOf(\Symfony\Component\DependencyInjection\Definition::class, $locator);
        $this->assertSame(\Symfony\Component\DependencyInjection\ServiceLocator::class, $locator->getClass());
        $map = $locator->getArgument(0);
        $this->assertIsArray($map);
        $this->assertSame([LoggingAspect::class], array_keys($map));
    }

    /**
     * @param array<string, mixed>                $config
     * @param array<string, class-string|string>  $bundles
     */
    private function loadExtension(array $config = [], array $bundles = ['GoAopBundle' => GoAopBundle::class]): ContainerBuilder
    {
        $container = $this->createContainerBuilder($bundles);
        $bundle    = new GoAopBundle();
        $bundle->build($container);
        $extension = $bundle->getContainerExtension();
        $this->assertNotNull($extension);
        $extension->load(['go_aop' => $config], $container);

        return $container;
    }

    /**
     * @param array<string, class-string|string>                       $bundles
     * @param array<string, array{namespace: string, path: string}>    $metadata
     */
    private function createContainerBuilder(array $bundles = ['GoAopBundle' => GoAopBundle::class], array $metadata = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter('kernel.bundles_metadata', $metadata);
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.project_dir', dirname(__DIR__));
        $container->setParameter('kernel.cache_dir', $_SERVER['APP_CACHE_DIR'] . '/unit');
        $container->setParameter('kernel.build_dir', $_SERVER['APP_CACHE_DIR'] . '/unit');
        $container->setParameter('kernel.environment', 'test');

        return $container;
    }
}

final class CustomAspectKernel extends AspectSymfonyKernel
{
}
