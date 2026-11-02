<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Aop\Exception\AspectCircularReferenceException as FrameworkCircularReferenceException;
use Go\Aop\Proxy;
use Go\Symfony\GoAopBundle\Exception\AspectCircularReferenceException;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\ClockAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Counter;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Ledger;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Locksmith;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Purse;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Safe;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\SafeAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Scheduler;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Vault;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\VaultAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Wallet;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\WalletAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Aspects depending on the services they advise ("cycle" environment of the fixture application).
 *
 * An aspect is created by the container when one of its advices runs first. Depending on a woven service is
 * fine, but when creating a dependency runs an advice of the aspect, that advice would run on an aspect that
 * does not exist yet: the framework stops the advice, and the bundle reports the circular reference with the
 * ways to inject the dependency lazily in Symfony.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AspectDependencyCycleTest extends FunctionalTestCase
{
    public function testAspectCanDependOnTheServiceItAdvises(): void
    {
        $container = $this->bootCycleKernel();

        $ledger = $container->get(Ledger::class);
        $this->assertInstanceOf(Ledger::class, $ledger);
        $this->assertInstanceOf(Proxy::class, $ledger);
        $ledger->post('first');
        $ledger->post('second');

        $this->assertSame(['post:0:same', 'post:1:same'], $this->recorder($container)->events);
    }

    public function testAspectDependingLazilyOnTheServiceWhoseConstructorItAdvisesWorks(): void
    {
        $container = $this->bootCycleKernel();

        $purse = $container->get(Purse::class);
        $this->assertInstanceOf(Purse::class, $purse);
        $purse->add(5);

        $this->assertSame(['purse:constructing', 'purse:constructed', 'purse:balance:5'], $this->recorder($container)->events);
    }

    /**
     * @return iterable<string, array{class-string, class-string, list<string>}>
     */
    public static function circularReferences(): iterable
    {
        yield 'constructor of the dependency' => [Wallet::class, WalletAspect::class, [
            Wallet::class . '->__construct()',
            WalletAspect::class . '->beforeConstruct()',
        ]];
        yield 'method called by the constructor of the dependency' => [Vault::class, VaultAspect::class, [
            Vault::class . '->__construct()',
            Vault::class . '->lock()',
            VaultAspect::class . '->beforeLock()',
        ]];
        yield 'method of another service called by the constructor of the dependency' => [Scheduler::class, ClockAspect::class, [
            Scheduler::class . '->__construct()',
            'Go\Symfony\GoAopBundle\Tests\Fixtures\App\Cycle\Clock->now()',
            ClockAspect::class . '->beforeNow()',
        ]];
    }

    /**
     * @param class-string $service
     * @param class-string $aspect
     * @param list<string> $path
     */
    #[DataProvider('circularReferences')]
    public function testAspectWhoseCreationRunsItsOwnAdviceIsReported(string $service, string $aspect, array $path): void
    {
        $container = $this->bootCycleKernel();

        try {
            $container->get($service);
            $this->fail('Creating the service must fail');
        } catch (AspectCircularReferenceException $exception) {
            $this->assertSame($aspect, $exception->aspectClass);
            $this->assertSame($path, $exception->path);
            $this->assertStringContainsString(
                sprintf('Circular reference detected for the aspect "%s", path: "%s".', $aspect, implode(' -> ', [$aspect, ...$path])),
                $exception->getMessage(),
            );
            $this->assertStringContainsString('#[Autowire(lazy: true)]', $exception->getMessage());
            $this->assertInstanceOf(FrameworkCircularReferenceException::class, $exception->getPrevious());
        }
    }

    public function testAdviceReadingNoPropertyOfTheAspectBeingCreatedIsReported(): void
    {
        $container = $this->bootCycleKernel();
        $safe      = $container->get(Safe::class);
        $this->assertInstanceOf(Safe::class, $safe);

        try {
            $safe->open();
            $this->fail('Creating the aspect must fail');
        } catch (AspectCircularReferenceException $exception) {
            $this->assertSame(SafeAspect::class, $exception->aspectClass);
            $this->assertSame(
                [Locksmith::class . '->__construct()', Locksmith::class . '->cut()', SafeAspect::class . '->beforeCut()'],
                $exception->path,
            );
        }
        $this->assertSame(0, SafeAspect::$cuts, 'The advice did not run on the aspect being created');
    }

    public function testExceptionOfTheAspectConstructorIsNotReportedAsCircularReference(): void
    {
        $container = $this->bootCycleKernel();

        $counter = $container->get(Counter::class);
        $this->assertInstanceOf(Counter::class, $counter);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Misconfigured at 0');

        $counter->increment();
    }

    private function bootCycleKernel(): ContainerInterface
    {
        // The service classes must be loaded through the weaver: the container is built in another process
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'cycle');

        return $this->bootKernel('cycle')->getContainer();
    }

    private function recorder(ContainerInterface $container): Recorder
    {
        $recorder = $container->get(Recorder::class);
        $this->assertInstanceOf(Recorder::class, $recorder);

        return $recorder;
    }
}
