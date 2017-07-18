<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection;

use Go\Symfony\GoAopBundle\DependencyInjection\GoAopExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class GoAopExtensionTest
 */
class GoAopExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function itLoadsServices()
    {
        $this->load();

        $expectedServices = [
            'goaop.aspect.kernel',
            'goaop.aspect.container',
            'goaop.cache.path.manager',
            'goaop.cache.warmer',
            'goaop.command.warmup',
            'goaop.command.debug_advisor',
            'goaop.command.debug_aspect',
            'goaop.bridge.doctrine.metadata_load_interceptor',
        ];

        foreach ($expectedServices as $id) {
            $this->assertContainerBuilderHasService($id);
        }

        $this->assertEquals(count($expectedServices), count(array_filter($this->container->getDefinitions(), function ($id) {
            return 0 === strpos($id, 'goaop.');
        }, ARRAY_FILTER_USE_KEY)));
    }

    /**
     * @test
     */
    public function itNormalizesAndSetsAspectKernelOptions()
    {
        $this->load();

        $this->assertEquals([
            'features'     => 0,
            'appDir'       => '%kernel.root_dir%/../src',
            'cacheDir'     => '%kernel.cache_dir%/aspect',
            'debug'        => '%kernel.debug%',
            'includePaths' => [],
            'excludePaths' => [],
        ], $this->container->getParameter('goaop.options'));
    }

    /**
     * @test
     */
    public function itDisablesCacheWarmer()
    {
        $this->load([
            'cache_warmer' => false,
        ]);

        $definition = $this->container->getDefinition('goaop.cache.warmer');

        $this->assertFalse($definition->hasTag('kernel.cache_warmer'));
    }

    /**
     * @test
     */
    public function itEnablesDoctrineSupport()
    {
        $this->load([
            'doctrine_support' => true,
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag('goaop.bridge.doctrine.metadata_load_interceptor', 'doctrine.event_subscriber');
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions()
    {
        return [
            new GoAopExtension(),
        ];
    }
}
