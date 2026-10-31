<?php

declare(strict_types=1);

namespace Go\Symfony\GoAopBundle\Tests\Functional;

use Go\Symfony\GoAopBundle\Tests\Fixtures\App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Base class of the tests booting the fixture application.
 *
 * The aspect kernel is a process-wide singleton bound to the cache directory of its first
 * initialization, so every test booting a kernel runs in a process of its own
 * (#[RunTestsInSeparateProcesses]), with the cache directory chosen by tests/bootstrap.php.
 */
abstract class FunctionalTestCase extends TestCase
{
    private ?Kernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        new Filesystem()->remove(self::cacheRootDir());
    }

    protected static function cacheRootDir(): string
    {
        return $_SERVER['APP_CACHE_DIR'];
    }

    protected static function projectDir(): string
    {
        return dirname(__DIR__) . '/Fixtures/App';
    }

    protected function bootKernel(string $environment = 'test', ?bool $debug = null): Kernel
    {
        $this->kernel = new Kernel($environment, $debug ?? $environment !== 'prod');
        $this->kernel->boot();

        return $this->kernel;
    }

    /**
     * Runs the console of the fixture application in a separate process
     *
     * @param list<string> $arguments
     */
    protected static function runConsole(array $arguments, string $environment = 'test'): Process
    {
        $process = new Process(
            [PHP_BINARY, self::projectDir() . '/bin/console', ...$arguments, '--env=' . $environment, '--no-interaction'],
            self::projectDir(),
            ['APP_CACHE_DIR' => self::cacheRootDir()],
        );
        $process->setTimeout(300);
        $process->mustRun();

        return $process;
    }

    /**
     * Lists the files below a directory, with their paths relative to it
     *
     * @return list<string>
     */
    protected static function listFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), strlen($directory) + 1);
        }
        sort($files);

        return $files;
    }
}
