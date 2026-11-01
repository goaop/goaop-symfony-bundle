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
        $output    = new BufferedOutput();
        $warmer    = new CacheWarmer($this->aspectKernel, $output);

        $errors = $warmer->warmUp(self::isSameDirectory($targetDir, $this->aspectKernel->getOptions()['cacheDir']) ? null : $targetDir);
        if ($errors > 0) {
            throw new RuntimeException(sprintf(
                "Go! AOP failed to weave %d file(s) into \"%s\":\n%s",
                $errors,
                $targetDir,
                self::errorLines($output->fetch()),
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
