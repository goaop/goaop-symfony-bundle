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

namespace Go\Symfony\GoAopBundle\DependencyInjection\Compiler;

use Closure;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;
use Go\Symfony\GoAopBundle\Config\ClassSignatureResource;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps the freshness check of the compiled container from loading weavable classes before the aspect
 * kernel is initialized.
 *
 * In debug mode the kernel checks the resources of the compiled container before the bundles boot. The
 * {@see ReflectionClassResource} of a service class loads the class to compute its signature when one of
 * its files changed, so the class is not woven in that request, nor in the following ones as long as its
 * signature does not change. This pass replaces the reflection resources of the classes whose hierarchy
 * holds a file of the include paths (or a woven file of the cache directory) by a
 * {@see ClassSignatureResource}, which checks the sources without loading the class.
 *
 * It runs as the last compiler pass, after every pass that registers a reflection resource. The kernel
 * checks the resources only in debug mode, in production mode the pass leaves them as they are.
 */
final class ClassSignatureResourcePass implements CompilerPassInterface
{
    /**
     * Priority among the passes run after the removing passes: after the last pass of Symfony
     */
    public const int PRIORITY = -4096;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->isTrackingResources()
            || !$container->hasParameter('goaop.options')
            || !$container->hasParameter('kernel.debug')
            || $container->getParameter('kernel.debug') !== true
        ) {
            return;
        }

        $isWeavable = self::weavableFileFilter($container->getParameterBag()->resolveValue($container->getParameter('goaop.options')));
        if ($isWeavable === null) {
            return;
        }

        $resources = [];
        $replaced  = false;
        foreach ($container->getResources() as $resource) {
            if ($resource instanceof ReflectionClassResource) {
                $resource = self::replacement($resource, $isWeavable) ?? $resource;
                $replaced = $replaced || $resource instanceof ClassSignatureResource;
            }
            $resources[(string) $resource] = $resource;
        }

        if ($replaced) {
            $container->setResources($resources);
        }
    }

    /**
     * Returns the resource tracking the class of a reflection resource without loading it, or null when no
     * file of the class hierarchy is weavable
     *
     * @param Closure(string): bool $isWeavable
     */
    private static function replacement(ReflectionClassResource $resource, Closure $isWeavable): ?ClassSignatureResource
    {
        // The serialized form is the state Symfony writes to the meta file: the class and its files
        $state     = $resource->__serialize();
        $className = $state['className'] ?? null;
        $files     = $state['files'] ?? null;
        if (!is_string($className) || !is_array($files)) {
            return null;
        }

        $files = array_values(array_filter(array_keys($files), is_string(...)));
        foreach ($files as $file) {
            if ($isWeavable($file)) {
                return new ClassSignatureResource($className, $files);
            }
        }

        return null;
    }

    /**
     * Builds the filter of the files that belong to woven classes from the options of the aspect kernel: the
     * files below the include paths, as the kernel filters them, and the woven files of its cache directory,
     * which reflection reports for the classes woven through the class map of the cache (when the container is
     * compiled while the aspect kernel runs without its debug mode)
     *
     * @return (Closure(string): bool)|null
     */
    private static function weavableFileFilter(mixed $options): ?Closure
    {
        if (!is_array($options) || !is_string($options['appDir'] ?? null)) {
            return null;
        }
        $appDir     = PathResolver::realpath($options['appDir']);
        $isIncluded = new Enumerator(
            is_string($appDir) ? $appDir : $options['appDir'],
            self::realPaths($options['includePaths'] ?? []),
            self::realPaths($options['excludePaths'] ?? []),
        )->getPathFilter();

        $cacheDir = self::realPaths([$options['cacheDir'] ?? null])[0] ?? null;
        if ($cacheDir === null) {
            return $isIncluded;
        }
        $cachePrefix = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR;

        return static fn(string $file): bool => $isIncluded($file) || str_starts_with($file, $cachePrefix);
    }

    /**
     * @return list<string>
     */
    private static function realPaths(mixed $paths): array
    {
        if (!is_array($paths)) {
            return [];
        }
        $paths = array_values(array_filter($paths, is_string(...)));
        if ($paths === []) {
            return [];
        }
        $resolved = PathResolver::realpath($paths);

        return is_array($resolved) ? array_values(array_filter($resolved, is_string(...))) : [];
    }
}
