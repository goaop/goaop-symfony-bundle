<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\CacheWarmer;

use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
use Go\Instrument\PathResolver;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Weaves every file below the include paths into the AOP cache during cache:warmup and cache:clear.
 *
 * Symfony warms the new cache up next to the current one (cache:clear reboots the kernel into a
 * temporary directory and renames it afterwards), while the aspect kernel of the process is bound
 * to the current cache directory. A warm-up into any other directory therefore runs in a fresh
 * process, which initializes a kernel from the dumped bootstrap file of that directory.
 *
 * @phpstan-import-type UserKernelOptions from AspectKernel
 */
final class AspectCacheWarmer implements CacheWarmerInterface
{
    /**
     * Script warming up the cache of a bootstrapped kernel in a fresh process
     */
    private const string WARMUP_SCRIPT = __DIR__ . '/../../bin/cache-warmup.php';

    /**
     * @phpstan-param UserKernelOptions $options
     *
     * @param array{anchor: 'cache'|'build'|null, suffix: string} $cacheDirAnchor
     */
    public function __construct(
        private readonly AspectKernel $aspectKernel,
        private readonly BootstrapDumper $bootstrapDumper,
        private readonly array $options,
        private readonly array $cacheDirAnchor,
    ) {}

    /**
     * The cache is also built on demand at runtime, so the warm-up only runs when optional warmers are enabled
     * (cache:warmup and cache:clear without --no-optional-warmers), not on the first request with a cold cache
     */
    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $targetDir     = $this->resolveTargetDir($cacheDir, $buildDir ?? $cacheDir);
        $bootstrapFile = $this->bootstrapDumper->dump($targetDir);

        if (self::isSameDirectory($targetDir, $this->aspectKernel->getOptions()['cacheDir'])) {
            $this->warmUpInProcess();
        } else {
            $this->warmUpInFreshProcess($bootstrapFile);
        }

        return [];
    }

    /**
     * Warms up the cache of the kernel of this process
     */
    private function warmUpInProcess(): void
    {
        $output = new BufferedOutput();
        $errors = new CacheWarmer($this->aspectKernel, $output)->warmUp();
        if ($errors > 0) {
            throw new RuntimeException(sprintf(
                "Go! AOP failed to weave %d file(s):\n%s",
                $errors,
                self::errorLines($output->fetch()),
            ));
        }
    }

    /**
     * Warms up a cache directory other than the one of the kernel of this process
     */
    private function warmUpInFreshProcess(string $bootstrapFile): void
    {
        $php = new PhpExecutableFinder()->find(false);
        if ($php === false) {
            throw new RuntimeException(sprintf(
                'Go! AOP can not warm up the cache in "%s": the PHP executable was not found. Run "cache:warmup" '
                . 'separately, which weaves in the current process.',
                dirname($bootstrapFile),
            ));
        }

        $process = new Process([$php, ...new PhpExecutableFinder()->findArguments(), self::WARMUP_SCRIPT, $bootstrapFile]);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                "Go! AOP failed to warm up the cache in \"%s\" (exit code %d):\n%s",
                dirname($bootstrapFile),
                $process->getExitCode() ?? -1,
                self::errorLines($process->getErrorOutput() . "\n" . $process->getOutput()),
            ));
        }
    }

    /**
     * Rebases the configured cache directory on the directory Symfony asks to warm up
     */
    private function resolveTargetDir(string $cacheDir, string $buildDir): string
    {
        return match ($this->cacheDirAnchor['anchor']) {
            'cache' => $cacheDir . $this->cacheDirAnchor['suffix'],
            'build' => $buildDir . $this->cacheDirAnchor['suffix'],
            null    => $this->options['cacheDir'] ?? $cacheDir . '/aop',
        };
    }

    private static function isSameDirectory(string $first, ?string $second): bool
    {
        if ($second === null) {
            return false;
        }
        $resolve = static function (string $path): string {
            $resolved = PathResolver::realpath($path);

            return rtrim(str_replace('\\', '/', is_string($resolved) ? $resolved : $path), '/');
        };

        return $resolve($first) === $resolve($second);
    }

    /**
     * Keeps the lines that report an error, the progress lines of a successful file are noise in an exception
     */
    private static function errorLines(string $output): string
    {
        $lines = array_filter(
            array_map(trim(...), explode("\n", $output)),
            static fn(string $line): bool => $line !== '' && !str_starts_with($line, '[OK]'),
        );

        return implode("\n", array_slice(array_values($lines), 0, 50));
    }
}
