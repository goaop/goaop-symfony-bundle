<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Aop\Aspect;
use Go\Aop\Proxy;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\EntityAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Symfony\Component\ErrorHandler\DebugClassLoader;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class KernelBootTest extends FunctionalTestCase
{
    public function testServicesAreWovenWithAspectsFromTheContainer(): void
    {
        // The process compiling the container loads the service classes before the bundle boots,
        // so the container is built in a process of its own, like on a deployment
        self::runConsole(['cache:warmup', '--no-optional-warmers']);

        $container = $this->bootKernel()->getContainer();

        $greeting = $container->get(GreetingService::class);
        $recorder = $container->get(Recorder::class);
        $this->assertInstanceOf(GreetingService::class, $greeting);
        $this->assertInstanceOf(Recorder::class, $recorder);
        $this->assertInstanceOf(Proxy::class, $greeting, 'The service class must be woven');

        $this->assertSame('Hello, Symfony!', $greeting->greet('Symfony'));
        $this->assertSame('AOP!', $greeting->shout('aop'));
        try {
            $greeting->explode();
            $this->fail('explode() must throw');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame(['before:greet', 'after:greet', 'afterThrowing:explode:boom'], $recorder->events);
    }

    public function testAspectKernelAndContainerAreServices(): void
    {
        $container = $this->bootKernel()->getContainer();

        $kernel = $container->get('goaop.aspect_kernel');
        $this->assertInstanceOf(AspectSymfonyKernel::class, $kernel);
        $this->assertSame(AspectKernel::getInstance(), $kernel);
        $this->assertSame(self::cacheRootDir() . '/test/aop', $kernel->getOptions()['cacheDir']);
        $this->assertTrue($kernel->getOptions()['debug']);
        $this->assertSame([self::projectDir() . '/src'], $kernel->getOptions()['includePaths']);

        $aspectContainer = $kernel->getContainer();
        $this->assertSame([EntityAspect::class, LoggingAspect::class], $aspectContainer->getServiceIdsByInterface(Aspect::class));
        $this->assertInstanceOf(AspectContainer::class, $aspectContainer);
    }

    public function testAspectContainerDelegatesToTheApplicationContainer(): void
    {
        $container       = $this->bootKernel()->getContainer();
        $aspectContainer = AspectKernel::getInstance()->getContainer();

        $this->assertTrue($aspectContainer->has(Recorder::class));
        $this->assertSame($container->get(Recorder::class), $aspectContainer->get(Recorder::class));
        $this->assertSame($container->get('kernel'), $aspectContainer->get('kernel'));
    }

    public function testCompiledContainerDoesNotIncludeWovenSourcesDirectly(): void
    {
        $kernel = $this->bootKernel();

        $containerDir = self::cacheRootDir() . '/test/' . basename(dirname((new \ReflectionObject($kernel->getContainer()))->getFileName() ?: ''));
        $this->assertDirectoryExists($containerDir);
        foreach (self::listFiles($containerDir) as $file) {
            $this->assertStringNotContainsString(
                'GreetingService.php',
                (string) file_get_contents($containerDir . '/' . $file),
                sprintf('The compiled container file "%s" must not include the source of a woven class', $file),
            );
        }
    }

    public function testWeavingWorksBehindTheDebugClassLoader(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers']);
        DebugClassLoader::enable();

        $container = $this->bootKernel()->getContainer();

        $autoloaders = spl_autoload_functions();
        $this->assertIsArray($autoloaders[0]);
        $this->assertInstanceOf(DebugClassLoader::class, $autoloaders[0][0], 'The DebugClassLoader is enabled again after the initialization');

        $greeting = $container->get(GreetingService::class);
        $this->assertInstanceOf(Proxy::class, $greeting);
        $this->assertSame('AOP!', $greeting->shout('aop'));
    }

    public function testBundleMustBeRegisteredFirst(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('GoAopBundle must be the first bundle in config/bundles.php, but "FrameworkBundle" is registered before it.');

        $this->bootKernel('misplaced');
    }
}
