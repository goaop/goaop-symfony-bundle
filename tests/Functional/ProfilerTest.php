<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Symfony\GoAopBundle\DataCollector\AspectDataCollector;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ProfilerTest extends FunctionalTestCase
{
    public function testCollectorDescribesTheAspectsAndTheWovenClassesOfTheRequest(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'profiler');
        $client = new KernelBrowser($this->bootKernel('profiler'));
        $client->enableProfiler();

        $client->request('GET', '/greet/profiler');

        $this->assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $this->assertSame(
            ['greeting' => 'Hello, profiler!', 'events' => ['before:greet', 'after:greet']],
            json_decode((string) $client->getResponse()->getContent(), true),
        );

        $profile = $client->getProfile();
        $this->assertNotFalse($profile);
        $this->assertNotNull($profile);
        $collector = $profile->getCollector(AspectDataCollector::NAME);
        $this->assertInstanceOf(AspectDataCollector::class, $collector);

        $aspects = array_column($collector->getAspects(), null, 'class');
        $this->assertArrayHasKey(LoggingAspect::class, $aspects);
        $this->assertSame(4, count($aspects[LoggingAspect::class]['advisors']));
        $advisors = array_column($aspects[LoggingAspect::class]['advisors'], null, 'id');
        $this->assertSame('Before', $advisors[LoggingAspect::class . '->beforeGreet']['type']);
        $this->assertSame(
            'execution(public Go\Symfony\GoAopBundle\Tests\Fixtures\App\Service\GreetingService->greet(*))',
            $advisors[LoggingAspect::class . '->beforeGreet']['pointcut'],
        );
        $this->assertSame(5, $collector->getAdvisorCount());

        $this->assertArrayHasKey(GreetingService::class, $collector->getWovenClasses());
        $this->assertStringStartsWith(self::cacheRootDir() . '/profiler/aop/', $collector->getWovenClasses()[GreetingService::class]);
        $this->assertGreaterThanOrEqual(1, $collector->getCachedClassCount());
        $this->assertSame([], $collector->getClassesDeclaredBeforeInitialization(), 'The container was built in another process');
        $this->assertTrue($collector->isDebug());
        $this->assertSame([self::projectDir() . '/src'], $collector->getIncludePaths());
    }

    public function testProfilerPanelRenders(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'profiler');
        $client = new KernelBrowser($this->bootKernel('profiler'));
        $client->enableProfiler();
        $client->request('GET', '/greet/panel');
        $profile = $client->getProfile();
        $this->assertNotFalse($profile);
        $this->assertNotNull($profile);

        $crawler = $client->request('GET', '/_profiler/' . $profile->getToken() . '?panel=' . AspectDataCollector::NAME);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(LoggingAspect::class, $crawler->filter('#collector-content')->text());
        $this->assertStringContainsString(GreetingService::class, $crawler->filter('#collector-content')->text());
        $this->assertStringContainsString('Woven classes used in this request', $crawler->filter('#collector-content')->text());
    }

    public function testClassesLoadedBeforeTheKernelAreReported(): void
    {
        // Booting without a prebuilt container compiles it in this process, which loads the service classes first
        $client = new KernelBrowser($this->bootKernel('profiler'));
        $client->enableProfiler();
        $client->request('GET', '/greet/early');

        $profile = $client->getProfile();
        $this->assertNotFalse($profile);
        $this->assertNotNull($profile);
        $collector = $profile->getCollector(AspectDataCollector::NAME);
        $this->assertInstanceOf(AspectDataCollector::class, $collector);

        $this->assertContains(GreetingService::class, $collector->getClassesDeclaredBeforeInitialization());
        $this->assertArrayNotHasKey(GreetingService::class, $collector->getWovenClasses());
    }
}
