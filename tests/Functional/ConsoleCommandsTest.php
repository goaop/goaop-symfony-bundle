<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ConsoleCommandsTest extends FunctionalTestCase
{
    public function testDebugAspectListsTheAspectsOfTheApplication(): void
    {
        $tester = $this->runCommand(['command' => 'debug:aspect']);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString(LoggingAspect::class, $tester->getDisplay());
        $this->assertStringContainsString(LoggingAspect::class . '->beforeGreet', $tester->getDisplay());
        $this->assertStringContainsString(LoggingAspect::class . '->aroundShout', $tester->getDisplay());
    }

    public function testDebugAspectRejectsAnUnknownAspect(): void
    {
        $tester = $this->runCommand(['command' => 'debug:aspect', '--aspect' => 'App\Aspect\Missing']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('is not registered in the kernel', $tester->getDisplay());
    }

    public function testDebugAdvisorShowsTheJoinpointsOfAnAdvisor(): void
    {
        $tester = $this->runCommand(['command' => 'debug:advisor', '--advisor' => LoggingAspect::class . '->beforeGreet']);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('GreetingService', $tester->getDisplay());
        $this->assertStringContainsString('greet', $tester->getDisplay());
    }

    public function testCacheWarmupAopWeavesTheIncludePaths(): void
    {
        $tester = $this->runCommand(['command' => 'cache:warmup:aop']);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('[DONE]', $tester->getDisplay());
        $this->assertFileExists(self::cacheRootDir() . '/test/aop/src/Service/GreetingService.php');
        $this->assertFileExists(self::cacheRootDir() . '/test/aop/src/Service/GreetingServiceOriginalTrait.php');
    }

    public function testDebugWeavingReportsStableWeaving(): void
    {
        $tester = $this->runCommand(['command' => 'debug:weaving']);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Weaving is stable', $tester->getDisplay());
    }

    public function testFrameworkCommandsAreRegisteredAsTheyAre(): void
    {
        $tester = $this->runCommand(['command' => 'help', 'command_name' => 'cache:warmup:aop']);

        // The loader argument of the framework commands is optional: the kernel of the application is used
        $this->assertStringContainsString('[<loader>]', $tester->getDisplay());
        $this->assertStringContainsString('--fail-fast', $tester->getDisplay());
        $this->assertStringContainsString('--cache-dir', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input): ApplicationTester
    {
        $application = new Application($this->bootKernel());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $tester = new ApplicationTester($application);
        $tester->run($input, ['decorated' => false]);

        return $tester;
    }
}
