<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Process\Process;

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
        $this->assertStringContainsString('--preload', $tester->getDisplay());

        $tester = $this->runCommand(['command' => 'help', 'command_name' => 'cache:warmup:opcache']);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('--file-cache', $tester->getDisplay());
    }

    public function testCacheWarmupAopWritesThePreloadScriptBehindTheDebugClassLoader(): void
    {
        // The console of the fixture application enables the DebugClassLoader in debug mode, like the Symfony runtime
        $process = self::runConsole(['cache:warmup:aop', '--preload']);

        $this->assertFileExists(self::cacheRootDir() . '/test/aop/_preload.php', $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('Preload script written to', $process->getOutput());
    }

    public function testOpcacheWarmupPrimesTheFileCache(): void
    {
        if (!extension_loaded('Zend OPcache')) {
            $this->markTestSkipped('The opcache extension is not loaded');
        }
        self::runConsole(['cache:warmup:aop']);

        $fileCacheDir = self::cacheRootDir() . '/opcache';
        mkdir($fileCacheDir, 0o777, true);
        // Compiled scripts land in the file cache only in a process started with these settings
        $process = new Process(
            [
                PHP_BINARY,
                '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.file_cache_only=1',
                '-d', 'opcache.file_cache=' . $fileCacheDir,
                self::projectDir() . '/bin/console',
                'cache:warmup:opcache',
                '--file-cache=' . $fileCacheDir,
                '--entry=' . self::projectDir() . '/public/index.php',
                '--env=test',
            ],
            self::projectDir(),
            ['APP_CACHE_DIR' => self::cacheRootDir()],
        );
        $process->setTimeout(300);
        $process->run();

        // Composer's autoloaded files are included already and declare functions: compiling them again fails, which
        // the command ignores, and the console must not report it as a fatal error at shutdown
        $this->assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        $this->assertStringContainsString('Compiled ', $process->getOutput());
        $this->assertNotSame([], self::listFiles($fileCacheDir));
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
