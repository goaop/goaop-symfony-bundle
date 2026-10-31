<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection\Compiler;

use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class AspectCollectorPassTest extends TestCase
{
    public function testTaggedAspectsAreCollectedIntoALocatorKeyedByClass(): void
    {
        $container = $this->createContainer();
        $container->register('app.logging_aspect', LoggingAspect::class)->addTag(AspectCollectorPass::ASPECT_TAG);
        $container->register('app.not_an_aspect', Recorder::class);

        new AspectCollectorPass()->process($container);

        $this->assertTrue($container->hasAlias(AspectCollectorPass::LOCATOR_ID));
        $locator = $container->findDefinition(AspectCollectorPass::LOCATOR_ID);
        $map     = $locator->getArgument(0);
        $this->assertIsArray($map);
        $this->assertSame([LoggingAspect::class], array_keys($map));
        $this->assertInstanceOf(ServiceClosureArgument::class, $map[LoggingAspect::class]);
        $this->assertEquals([new Reference('app.logging_aspect')], $map[LoggingAspect::class]->getValues());
    }

    public function testClassOfAChildDefinitionIsResolvedThroughItsParent(): void
    {
        $container = $this->createContainer();
        $container->register('app.aspect.abstract', LoggingAspect::class)->setAbstract(true);
        $container->setDefinition('app.aspect', new ChildDefinition('app.aspect.abstract'))->addTag(AspectCollectorPass::ASPECT_TAG);

        new AspectCollectorPass()->process($container);

        $map = $container->findDefinition(AspectCollectorPass::LOCATOR_ID)->getArgument(0);
        $this->assertIsArray($map);
        $this->assertSame([LoggingAspect::class], array_keys($map));
    }

    public function testServiceThatIsNoAspectIsRejected(): void
    {
        $container = $this->createContainer();
        $container->register('app.recorder', Recorder::class)->addTag(AspectCollectorPass::ASPECT_TAG);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "app.recorder" is tagged "goaop.aspect", but its class "Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder" does not implement "Go\Aop\Aspect".');

        new AspectCollectorPass()->process($container);
    }

    public function testAspectClassRegisteredTwiceIsRejected(): void
    {
        $container = $this->createContainer();
        $container->register('app.aspect.one', LoggingAspect::class)->addTag(AspectCollectorPass::ASPECT_TAG);
        $container->register('app.aspect.two', LoggingAspect::class)->addTag(AspectCollectorPass::ASPECT_TAG);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is registered twice, as the services "app.aspect.one" and "app.aspect.two"');

        new AspectCollectorPass()->process($container);
    }

    public function testAliasOfAnAspectIsNotADuplicate(): void
    {
        $container = $this->createContainer();
        $container->register(LoggingAspect::class)->addTag(AspectCollectorPass::ASPECT_TAG);
        $container->setAlias('app.logging_aspect', LoggingAspect::class);

        new AspectCollectorPass()->process($container);

        $map = $container->findDefinition(AspectCollectorPass::LOCATOR_ID)->getArgument(0);
        $this->assertIsArray($map);
        $this->assertCount(1, $map);
    }

    public function testPassIsSkippedWithoutTheKernelService(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.aspect', LoggingAspect::class)->addTag(AspectCollectorPass::ASPECT_TAG);

        new AspectCollectorPass()->process($container);

        $this->assertFalse($container->hasAlias(AspectCollectorPass::LOCATOR_ID));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('goaop.aspect_kernel');

        return $container;
    }
}
