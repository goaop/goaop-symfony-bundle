<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\DependencyInjection;

use Go\Aop\Features;
use Go\Core\AspectKernel;
use Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel;
use ReflectionClass;

/**
 * Translates the processed "go_aop" configuration into the options of the aspect kernel
 *
 * @phpstan-import-type UserKernelOptions from AspectKernel
 * @phpstan-type CompiledKernelOptions array{
 *   debug: bool|string,
 *   appDir: string,
 *   cacheDir: string,
 *   includePaths: list<string>,
 *   excludePaths: list<string>,
 *   parsedFilesLimit: int,
 *   features: int,
 *   cacheFileMode?: int
 * }
 * @phpstan-type BundleConfig array{
 *   debug: bool|string,
 *   app_dir: string,
 *   cache_dir: string,
 *   cache_file_mode: int|string|null,
 *   include_paths: array<string>,
 *   exclude_paths: array<string>,
 *   parsed_files_limit: int,
 *   features: array<string>,
 *   kernel_class: class-string<AspectSymfonyKernel>,
 *   cache_warmer: bool,
 *   doctrine: bool|null
 * }
 *
 * @internal
 */
final class KernelOptionsResolver
{
    /**
     * Static helper, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Builds the kernel options from the processed bundle configuration
     *
     * Values holding parameter or environment placeholders are passed through: they are resolved by the
     * container when the options parameter is read.
     *
     * @phpstan-param BundleConfig $config
     *
     * @phpstan-return CompiledKernelOptions Kernel options, the "debug" flag may still be a parameter placeholder
     */
    public static function fromConfig(array $config): array
    {
        $features = 0;
        $constants = self::featureConstants();
        foreach ($config['features'] as $feature) {
            $features |= $constants[strtoupper($feature)] ?? 0;
        }

        $options = [
            'debug'            => $config['debug'],
            'appDir'           => $config['app_dir'],
            'cacheDir'         => $config['cache_dir'],
            'includePaths'     => array_values($config['include_paths']),
            'excludePaths'     => array_values($config['exclude_paths']),
            'parsedFilesLimit' => $config['parsed_files_limit'],
            'features'         => $features,
        ];

        $cacheFileMode = self::cacheFileMode($config['cache_file_mode']);
        if ($cacheFileMode !== null) {
            $options['cacheFileMode'] = $cacheFileMode;
        }

        return $options;
    }

    /**
     * Normalizes a configured file mode: an integer, an octal string like "0770" or "770", or null (kernel default)
     *
     * @return int|null The mode, or null when the value is not a valid mode (null itself is valid and yields null)
     */
    public static function cacheFileMode(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && preg_match('/^0?o?([0-7]{3,4})$/i', trim($value), $matches) === 1) {
            $value = octdec($matches[1]);
        }
        // Every mode from 0600 up has the owner read and write bits set
        if (!is_int($value) || $value < 0o600 || $value > 0o777) {
            return null;
        }

        return $value;
    }

    /**
     * Tells which kernel directory a configured cache directory is anchored on, by its unresolved value
     *
     * The cache warmer rebases the cache directory on the directory it is asked to warm up, which differs
     * from the configured one while cache:clear warms up the new cache next to the current one.
     *
     * @return array{anchor: 'cache'|'build'|null, suffix: string}
     */
    public static function cacheDirAnchor(string $configuredCacheDir): array
    {
        foreach (['cache' => '%kernel.cache_dir%', 'build' => '%kernel.build_dir%'] as $anchor => $placeholder) {
            if (str_starts_with($configuredCacheDir, $placeholder)) {
                return ['anchor' => $anchor, 'suffix' => substr($configuredCacheDir, strlen($placeholder))];
            }
        }

        return ['anchor' => null, 'suffix' => ''];
    }

    /**
     * Names of the configurable features
     *
     * @return list<string>
     */
    public static function featureNames(): array
    {
        return array_keys(self::featureConstants());
    }

    /**
     * @return array<string, int>
     */
    private static function featureConstants(): array
    {
        $constants = [];
        foreach (new ReflectionClass(Features::class)->getConstants() as $name => $value) {
            if ($name !== 'ALL' && is_int($value)) {
                $constants[$name] = $value;
            }
        }

        return $constants;
    }
}
