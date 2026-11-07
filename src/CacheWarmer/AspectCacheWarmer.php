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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Weaves every file below the include paths into the AOP cache during cache:warmup and cache:clear.
 *
 * Symfony warms the new cache up next to the current one (cache:clear reboots the kernel into a
 * temporary directory and renames it afterwards). The framework builds the cache in any directory
 * while the kernel of the process keeps running on its own, so the configured cache directory is
 * rebased on the directory Symfony asks to warm up.
 *
 * @phpstan-import-type UserKernelOptions from AspectKernel
 */
final class AspectCacheWarmer implements CacheWarmerInterface
{
    /**
     * @phpstan-param UserKernelOptions $options
     *
     * @param array{anchor: 'cache'|'build'|null, suffix: string} $cacheDirAnchor
     */
    public function __construct(
        private readonly AspectKernel $aspectKernel,
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
        $targetDir = $this->resolveTargetDir($cacheDir, $buildDir ?? $cacheDir);
        // Verbose output lists one line per file instead of a progress bar, the errors are picked from it
        $output    = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $kernelOptions = $this->aspectKernel->getOptions();
        // The directory of the running kernel is warmed up in place, any other one is built next to it
        $otherDir = self::isSameDirectory($targetDir, $kernelOptions['cacheDir']) ? null : $targetDir;
        // Keeps as many parsed files in memory as the kernel, like cache:warmup:aop without --cached-files
        $warmer   = new CacheWarmer($this->aspectKernel, $output, cachedFiles: $kernelOptions['parsedFilesLimit'], cacheDir: $otherDir);

        $errors = $warmer->warmUp();
        if ($errors > 0) {
            $errorLines = self::errorLines($output->fetch());

            throw new RuntimeException(sprintf(
                "Go! AOP failed to weave %d file(s) into \"%s\":\n%s%s",
                $errors,
                $targetDir,
                $errorLines,
                self::hint($errorLines),
            ));
        }

        return [];
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
     * Keeps the lines that report the errors of files: the header, the summary and the lines of the successful
     * files are noise in an exception
     */
    private static function errorLines(string $output): string
    {
        $noise = ['[OK]', '[DONE]', 'Total ', 'Weaving in '];
        $lines = array_filter(
            array_map(trim(...), explode("\n", $output)),
            static function (string $line) use ($noise): bool {
                foreach ($noise as $prefix) {
                    if (str_starts_with($line, $prefix)) {
                        return false;
                    }
                }

                return $line !== '';
            },
        );

        return implode("\n", array_slice(array_values($lines), 0, 50));
    }

    /**
     * Names the bundle option for the most common failure: the weaver reads the ancestors of a class from their
     * source files, which it finds through Composer, unless they are already declared. The framework explains the
     * failure in the error of the file, with the name of its kernel option.
     */
    private static function hint(string $errorLines): string
    {
        if (preg_match('/Class \S+ was not found (by locator|in the )/', $errorLines) !== 1) {
            return '';
        }

        return "\n\nIn the bundle configuration, the excludePaths option is go_aop.exclude_paths.";
    }
}
