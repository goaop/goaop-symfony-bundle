<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Aop\Features;
use Go\Aop\Proxy;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\CacheWarmer\BootstrapDumper;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CacheWarmupTest extends FunctionalTestCase
{
    public function testCacheWarmupWeavesTheApplicationAndDumpsTheBootstrapFile(): void
    {
        self::runConsole(['cache:warmup']);

        $aopDir = self::cacheRootDir() . '/test/aop';
        $files  = self::listFiles($aopDir);
        $this->assertContains(BootstrapDumper::FILE_NAME, $files);
        $this->assertContains('src/Service/GreetingService.php', $files);
        $this->assertContains('src/Service/GreetingServiceOriginalTrait.php', $files);
        $this->assertContains('_include.cache', $files);
        $this->assertNotContains('src/Aspect/LoggingAspect.php', $files, 'Aspects are never woven');
        $this->assertNotContains('src/Service/Recorder.php', $files, 'Classes without advices are not woven');
    }

    public function testProductionCacheIsBuiltByCacheClearAndTrustedAtRuntime(): void
    {
        // The first run builds the container in place, the second one warms the new cache up next to the current
        // one, in a fresh process, and renames it afterwards
        self::runConsole(['cache:clear'], 'prod');
        $process = self::runConsole(['cache:clear', '-v'], 'prod');
        $this->assertStringContainsString('successfully cleared', $process->getOutput());

        $aopDir = self::cacheRootDir() . '/prod/aop';
        $this->assertFileExists($aopDir . '/src/Service/GreetingService.php');
        $this->assertFileExists($aopDir . '/' . BootstrapDumper::FILE_NAME);
        $this->assertDirectoryDoesNotExist(self::cacheRootDir() . '/pro_');

        $container = $this->bootKernel('prod')->getContainer();
        $this->assertSame(Features::PREBUILT_CACHE, AspectKernel::getInstance()->getOptions()['features']);

        $greeting = $container->get(GreetingService::class);
        $recorder = $container->get(Recorder::class);
        $this->assertInstanceOf(Proxy::class, $greeting, 'The woven class is served from the prebuilt cache');
        $this->assertInstanceOf(GreetingService::class, $greeting);
        $this->assertInstanceOf(Recorder::class, $recorder);
        $this->assertSame('Hello, prod!', $greeting->greet('prod'));
        $this->assertSame(['before:greet', 'after:greet'], $recorder->events);
    }

    public function testWarmUpScriptRebuildsTheCacheFromTheBootstrapFile(): void
    {
        self::runConsole(['cache:warmup']);
        // Everything but the bootstrap file is removed: the metadata files describe the woven files
        $aopDir = self::cacheRootDir() . '/test/aop';
        new Filesystem()->remove([$aopDir . '/src', $aopDir . '/_include.cache', $aopDir . '/_transformation.cache']);
        $this->assertFileDoesNotExist($aopDir . '/src/Service/GreetingService.php');

        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/cache-warmup.php', $aopDir . '/' . BootstrapDumper::FILE_NAME]);
        $process->mustRun();

        $this->assertStringContainsString('[DONE]', $process->getOutput());
        $this->assertFileExists($aopDir . '/src/Service/GreetingService.php');
    }

    public function testWarmUpScriptRequiresABootstrapFile(): void
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/cache-warmup.php', '/nowhere/bootstrap.php']);
        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Usage:', $process->getErrorOutput());
    }
}
