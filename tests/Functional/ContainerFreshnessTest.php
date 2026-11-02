<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * In debug mode the kernel checks the freshness of the compiled container before the bundles boot. A source
 * file of a service that is newer than the container must not make that check load the class unwoven
 * (issue #24): the check runs on every request until the container is rebuilt, which an edit of a method
 * body never triggers.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ContainerFreshnessTest extends FunctionalTestCase
{
    private ?int $originalModificationTime = null;

    protected function tearDown(): void
    {
        if ($this->originalModificationTime !== null) {
            touch(self::serviceSource(), $this->originalModificationTime);
            clearstatcache();
        }
        parent::tearDown();
    }

    public function testServiceStaysWovenAfterItsSourceChanged(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers']);
        $containerFiles = glob(self::cacheRootDir() . '/test/*Container.php') ?: [];
        $this->assertCount(1, $containerFiles);
        $containerFile = $containerFiles[0];
        $compiledAt    = filemtime($containerFile);

        $this->assertWoven(self::handleRequest('before'), 'Before the change');

        // Like an edit of a method body: the source is newer than the container, the class signature is the same
        $this->originalModificationTime = (int) filemtime(self::serviceSource());
        touch(self::serviceSource(), time() + 2);
        clearstatcache();

        // Each request checks the container again: the problem showed on every request until a cache:clear
        $this->assertWoven(self::handleRequest('first'), 'First request after the change');
        $this->assertWoven(self::handleRequest('second'), 'Second request after the change');

        clearstatcache();
        $this->assertSame($compiledAt, filemtime($containerFile), 'The container is not rebuilt for an unchanged signature');
    }

    /**
     * @param array{status: int, response: array{greeting: string, events: list<string>}, loadedBeforeWeaving: list<class-string>} $result
     */
    private function assertWoven(array $result, string $request): void
    {
        $this->assertSame(200, $result['status'], $request);
        $this->assertSame([], $result['loadedBeforeWeaving'], $request . ': no class is loaded before the aspect kernel is initialized');
        $this->assertSame(['before:greet', 'after:greet'], $result['response']['events'], $request . ': the advices run');
    }

    private static function serviceSource(): string
    {
        return self::projectDir() . '/src/Service/GreetingService.php';
    }
}
