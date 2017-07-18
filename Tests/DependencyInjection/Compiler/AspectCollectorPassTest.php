<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection\Compiler;

use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AspectCollectorPassTest
 */
class AspectCollectorPassTest extends AbstractCompilerPassTestCase
{
    /**
     * @test
     */
    public function itRegistersAspects()
    {
        $this->setDefinition('goaop.aspect.container', new Definition());

        $someAspect = new Definition();
        $someAspect->addTag('goaop.aspect');
        $this->setDefinition('some_aspect', $someAspect);

        $someOtherAspect = new Definition();
        $someOtherAspect->addTag('goaop.aspect');
        $this->setDefinition('some_other_aspect', $someOtherAspect);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall('goaop.aspect.container', 'registerAspect', [new Reference('some_aspect')], 0);
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall('goaop.aspect.container', 'registerAspect', [new Reference('some_other_aspect')], 1);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerCompilerPass(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AspectCollectorPass());
    }
}
