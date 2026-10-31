<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ManagerRegistry;
use Go\Aop\Proxy;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Entity\Order;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionClass;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DoctrineIntegrationTest extends FunctionalTestCase
{
    public function testEntitiesAreDiscoveredThroughTheirWovenClasses(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'doctrine');

        $container = $this->bootKernel('doctrine')->getContainer();

        // Doctrine's own file locator would include the entity source directly, bypassing the weaver
        $driver = $container->get('test.attribute_driver');
        $this->assertInstanceOf(AttributeDriver::class, $driver);
        $this->assertSame([Order::class], $driver->getAllClassNames());
        $this->assertTrue(new ReflectionClass(Order::class)->implementsInterface(Proxy::class), 'The entity is woven');

        $recorder = $container->get(Recorder::class);
        $this->assertInstanceOf(Recorder::class, $recorder);
        $this->assertSame(42, new Order()->total());
        $this->assertSame(['before:total'], $recorder->events);
    }

    public function testMetadataOfWovenEntitiesLoads(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'doctrine');

        $container = $this->bootKernel('doctrine')->getContainer();
        $registry  = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager();
        $this->assertInstanceOf(EntityManagerInterface::class, $manager);

        $metadata = $manager->getClassMetadata(Order::class);
        $this->assertSame(Order::class, $metadata->getName());
        $this->assertSame(['id'], $metadata->getIdentifierFieldNames());
        $this->assertTrue($metadata->getReflectionClass()->implementsInterface(Proxy::class));
    }
}
