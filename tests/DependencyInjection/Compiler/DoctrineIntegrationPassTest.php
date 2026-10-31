<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Go\Bridge\Doctrine\WovenEntityClassLocator;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\DoctrineIntegrationPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DoctrineIntegrationPassTest extends TestCase
{
    public function testAttributeDriverDirectoriesAreWrappedIntoTheWovenEntityClassLocator(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('.goaop.doctrine', true);
        $container->register('doctrine.orm.default_attribute_metadata_driver', AttributeDriver::class)
            ->setArguments([['App\Entity' => '/app/src/Entity', 'Acme' => '/app/vendor/acme/src/Entity']]);

        new DoctrineIntegrationPass()->process($container);

        $locator = $container->getDefinition('doctrine.orm.default_attribute_metadata_driver')->getArgument(0);
        $this->assertInstanceOf(Definition::class, $locator);
        $this->assertSame(WovenEntityClassLocator::class, $locator->getClass());
        $this->assertSame([WovenEntityClassLocator::class, 'createFromDirectories'], $locator->getFactory());
        $this->assertSame([['/app/src/Entity', '/app/vendor/acme/src/Entity']], $locator->getArguments());
    }

    public function testDriversAreLeftAloneWhenTheIntegrationIsDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.orm.default_attribute_metadata_driver', AttributeDriver::class)
            ->setArguments([['/app/src/Entity']]);

        new DoctrineIntegrationPass()->process($container);

        $this->assertSame([['/app/src/Entity']], $container->getDefinition('doctrine.orm.default_attribute_metadata_driver')->getArguments());
    }

    public function testDriverAlreadyUsingALocatorIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('.goaop.doctrine', true);
        $locator = new Definition(WovenEntityClassLocator::class, [[]]);
        $container->register('doctrine.orm.default_attribute_metadata_driver', AttributeDriver::class)->setArguments([$locator]);

        new DoctrineIntegrationPass()->process($container);

        $this->assertSame($locator, $container->getDefinition('doctrine.orm.default_attribute_metadata_driver')->getArgument(0));
    }
}
