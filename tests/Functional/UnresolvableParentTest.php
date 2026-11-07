<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use Go\Aop\Exception\WeavingException;
use Go\Aop\Proxy;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Admin\ReportController;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\BaseClassGenerator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;

/**
 * A class below the include paths whose parent class Composer can not locate (generated at runtime)
 *
 * The weaver reflects the ancestors of a class from their source files, which it finds through Composer,
 * unless they are already declared. The container is built in a separate process, so the parent class is
 * not declared when the class is woven, like on every request after the one that compiled the container.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class UnresolvableParentTest extends FunctionalTestCase
{
    public function testWeavingFailsWithTheFileAndTheClassThatCanNotBeLocated(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'generated');
        $container = $this->bootKernel('generated')->getContainer();

        try {
            $container->get(ReportController::class);
            $this->fail('Weaving a class whose parent can not be located must fail');
        } catch (WeavingException $exception) {
            $this->assertStringContainsString('Admin/ReportController.php', $exception->getMessage());
            $this->assertStringContainsString(
                'Class ' . BaseClassGenerator::CLASS_NAME . ' was not found by locator',
                $exception->getMessage(),
            );
            $this->assertStringContainsString(
                'make ' . BaseClassGenerator::CLASS_NAME . ' loadable by Composer',
                $exception->getMessage(),
                'The framework tells how to fix the failure at runtime too',
            );
        }
    }

    public function testCacheWarmupFailsWithAHintToExcludeTheDirectory(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'generated');
        $this->bootKernel('generated');

        try {
            self::warmUpNextCache();
            $this->fail('The warm-up must fail when a file can not be woven');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Go! AOP failed to weave 1 file(s)', $message);
            $this->assertStringContainsString('[ERR]: ' . self::projectDir() . '/Admin/ReportController.php', $message);
            $this->assertStringContainsString(BaseClassGenerator::CLASS_NAME . ' was not found by locator', $message);
            $this->assertStringContainsString(
                'In the bundle configuration, the excludePaths option is go_aop.exclude_paths and the Features::PARTIAL_REFLECTION flag is go_aop.features: [PARTIAL_REFLECTION].',
                $message,
            );
            $this->assertSame(1, substr_count($message, 'loadable by Composer'), 'The framework explains the failure once');
            $this->assertStringNotContainsString('[OK]', $message, 'Only the failed files are reported');
            $this->assertStringNotContainsString('Total ', $message, 'Only the failed files are reported');
        }
    }

    public function testExcludedDirectoryIsLoadedWithoutWeaving(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'generated_excluded');
        $container = $this->bootKernel('generated_excluded')->getContainer();

        self::warmUpNextCache();
        $files = self::listFiles(self::cacheRootDir() . '/next/aop');
        $this->assertContains('src/Service/GreetingService.php', $files, 'The rest of the application is woven');
        $this->assertNotContains('Admin/ReportController.php', $files);

        $report = $container->get(ReportController::class);
        $this->assertInstanceOf(ReportController::class, $report);
        $this->assertNotInstanceOf(Proxy::class, $report, 'The excluded class is loaded as it is');
        $this->assertSame('Report x of generated base', $report->render('x'));
    }

    public function testParentClassLocatedByComposerIsWoven(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'generated');
        $kernel    = $this->bootKernel('generated');
        $container = $kernel->getContainer();

        // The generated code is made known to Composer, e.g. with a "classmap" autoload entry or addPsr4()
        $generatedFile = BaseClassGenerator::generate($kernel->getCacheDir() . '/generated');
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addClassMap([BaseClassGenerator::CLASS_NAME => $generatedFile]);
        }

        self::warmUpNextCache();
        $this->assertContains('Admin/ReportController.php', self::listFiles(self::cacheRootDir() . '/next/aop'));

        $report = $container->get(ReportController::class);
        $this->assertInstanceOf(ReportController::class, $report);
        $this->assertInstanceOf(Proxy::class, $report);
        $this->assertSame('[Report x of generated base]', $report->render('x'));
    }

    public function testPartialReflectionWeavesTheClassWithItsOwnMembers(): void
    {
        self::runConsole(['cache:warmup', '--no-optional-warmers'], 'generated_partial');
        $container = $this->bootKernel('generated_partial')->getContainer();

        self::warmUpNextCache();
        $this->assertContains('Admin/ReportController.php', self::listFiles(self::cacheRootDir() . '/next/aop'));

        $report = $container->get(ReportController::class);
        $this->assertInstanceOf(ReportController::class, $report);
        $this->assertInstanceOf(Proxy::class, $report, 'The class is woven although Composer can not locate its parent');
        $this->assertSame('[Report x of generated base]', $report->render('x'), 'Its own methods are intercepted');
    }

    /**
     * Runs the cache warmer of the bundle like cache:clear does: into another directory, from scratch
     */
    private static function warmUpNextCache(): void
    {
        $directory = self::cacheRootDir() . '/next';
        mkdir($directory, 0o777, true);
        $aspectKernel = AspectKernel::getInstance();
        $warmer       = new AspectCacheWarmer($aspectKernel, $aspectKernel->getOptions(), ['anchor' => 'cache', 'suffix' => '/aop']);
        $warmer->warmUp($directory);
    }
}
