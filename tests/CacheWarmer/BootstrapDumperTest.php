<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\CacheWarmer;

use Go\Aop\Aspect;
use Go\Aop\Features;
use Go\Symfony\GoAopBundle\CacheWarmer\BootstrapDumper;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Aspect\LoggingAspect;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class BootstrapDumperTest extends TestCase
{
    public function testPathsBelowACommonAncestorAreWrittenRelativeToTheCacheDirectory(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $dumper     = new BootstrapDumper(
            [
                'debug'        => false,
                'appDir'       => $projectDir,
                'cacheDir'     => $projectDir . '/var/cache/prod/aop',
                'includePaths' => [$projectDir . '/src', $projectDir . '/var/cache/prod/aop/generated', '/opt/shared/lib'],
                'excludePaths' => [],
                'features'     => Features::PREBUILT_CACHE,
            ],
            AspectSymfonyKernel::class,
            $this->aspects([LoggingAspect::class]),
        );

        $content = $dumper->getContent($projectDir . '/var/cache/prod/aop');

        $this->assertStringContainsString("'cacheDir' => __DIR__,", $content);
        $this->assertStringContainsString("'appDir' => \\dirname(__DIR__, 4),", $content);
        $this->assertStringContainsString("\\dirname(__DIR__, 4) . '/src',", $content);
        $this->assertStringContainsString("__DIR__ . '/generated',", $content);
        $this->assertStringContainsString("'/opt/shared/lib',", $content);
        $this->assertStringContainsString("'features' => 64,", $content);
        $this->assertStringContainsString('\\' . LoggingAspect::class . '::class,', $content);
        $this->assertStringContainsString('return \\' . AspectSymfonyKernel::class . '::bootstrap(', $content);
        $this->assertStringContainsString("require_once \\dirname(__DIR__, 4) . '/vendor/autoload.php';", $content);
        $this->assertStringContainsString('vendor/bin/aspect debug:aspect var/cache/prod/aop/bootstrap.php', $content);
    }

    public function testDumpedFileBootstrapsTheKernelInAFreshProcess(): void
    {
        $cacheDir = $_SERVER['APP_CACHE_DIR'] . '/bootstrap-dumper';
        $dumper   = new BootstrapDumper(
            [
                'debug'        => true,
                'appDir'       => dirname(__DIR__) . '/Fixtures/App',
                'cacheDir'     => $cacheDir,
                'includePaths' => [dirname(__DIR__) . '/Fixtures/App/src'],
                'excludePaths' => [],
                'features'     => 0,
            ],
            AspectSymfonyKernel::class,
            $this->aspects([LoggingAspect::class]),
        );

        $file = $dumper->dump($cacheDir);

        $this->assertFileExists($file);
        $this->assertSame($cacheDir . '/' . BootstrapDumper::FILE_NAME, $file);

        $script = <<<'PHP'
            $kernel = require $argv[1];
            $container = $kernel->getContainer();
            echo json_encode([
                'kernel'   => $kernel::class,
                'cacheDir' => $kernel->getOptions()['cacheDir'],
                'aspects'  => $container->getServiceIdsByInterface(Go\Aop\Aspect::class),
            ]);
            PHP;
        $output = shell_exec(sprintf('%s -r %s -- %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script), escapeshellarg($file)));

        $this->assertIsString($output);
        $this->assertJson($output, $output);
        $this->assertSame(
            ['kernel' => AspectSymfonyKernel::class, 'cacheDir' => realpath($cacheDir), 'aspects' => [LoggingAspect::class]],
            json_decode($output, true),
        );
    }

    /**
     * @param list<class-string<Aspect>> $classes
     *
     * @return ServiceLocator<Aspect>
     */
    private function aspects(array $classes): ServiceLocator
    {
        $factories = [];
        foreach ($classes as $class) {
            $factories[$class] = static fn(): never => throw new \LogicException('never created');
        }

        return new ServiceLocator($factories);
    }
}
