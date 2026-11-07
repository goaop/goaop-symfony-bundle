<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Aop\Features;
use Go\Aop\Proxy;
use Go\Core\AspectKernel;
use Go\ParserReflection\ReflectionEngine;
use Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\Recorder;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CacheWarmupTest extends FunctionalTestCase
{
    public function testCacheWarmupWeavesTheApplication(): void
    {
        self::runConsole(['cache:warmup']);

        $aopDir = self::cacheRootDir() . '/test/aop';
        $files  = self::listFiles($aopDir);
        $this->assertContains('src/Service/GreetingService.php', $files);
        $this->assertContains('src/Service/GreetingServiceOriginalTrait.php', $files);
        $this->assertContains('_include.cache', $files);
        $this->assertNotContains('src/Aspect/LoggingAspect.php', $files, 'Aspects are never woven');
        $this->assertNotContains('src/Service/Recorder.php', $files, 'Classes without advices are not woven');
    }

    public function testProductionCacheIsBuiltByCacheClearAndTrustedAtRuntime(): void
    {
        // The first run builds the container in place, the second one warms the new cache up next to the current
        // one (the running kernel keeps its own directory) and renames it afterwards
        self::runConsole(['cache:clear'], 'prod');
        $process = self::runConsole(['cache:clear', '-v'], 'prod');
        $this->assertStringContainsString('successfully cleared', $process->getOutput());

        $aopDir = self::cacheRootDir() . '/prod/aop';
        $this->assertFileExists($aopDir . '/src/Service/GreetingService.php');
        $this->assertFileExists($aopDir . '/_include.cache');
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

    public function testCacheWarmerKeepsAsManyParsedFilesAsTheKernel(): void
    {
        $this->bootKernel('prod');
        $aspectKernel = AspectKernel::getInstance();
        $this->assertSame(50, $aspectKernel->getOptions()['parsedFilesLimit']);
        $this->assertSame(50, self::maximumCachedFiles(), 'The kernel applies the option');

        // The warm-up sets the limit of its own weaving process, from the option of the kernel
        ReflectionEngine::setMaximumCachedFiles(7);
        $directory = self::cacheRootDir() . '/next';
        mkdir($directory, 0o777, true);
        new AspectCacheWarmer($aspectKernel, $aspectKernel->getOptions(), ['anchor' => 'cache', 'suffix' => '/aop'])->warmUp($directory);

        $this->assertFileExists($directory . '/aop/src/Service/GreetingService.php');
        $this->assertSame(50, self::maximumCachedFiles());
    }

    private static function maximumCachedFiles(): mixed
    {
        return new \ReflectionProperty(ReflectionEngine::class, 'maximumCachedFiles')->getValue();
    }
}
