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

namespace Go\Symfony\GoAopBundle\Kernel;

use Composer\Autoload\ClassLoader;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\PathResolver;
use Go\ParserReflection\LocatorInterface;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Locates the original source of a class for the weaver, also behind Symfony's DebugClassLoader.
 *
 * The weaver reflects a class and its ancestors from their source files, which the locator of the framework finds
 * by asking the Composer loaders registered with spl_autoload_register(). In debug mode Symfony's DebugClassLoader
 * wraps every autoloader, the Go! AOP loader included, so that locator finds none of them and weaving a class whose
 * ancestors are not declared yet fails with "Class ... was not found by locator". This locator asks the loaders the
 * same way, a wrapped one through the DebugClassLoader around it: the Go! AOP loader by the rules of the Composer
 * loader it wraps (never the woven files of the cache), a Composer loader directly.
 *
 * @internal Installed by AspectSymfonyKernel::init()
 */
final class DebugClassLoaderAwareLocator implements LocatorInterface
{
    public function locateClass(string $className): false|string
    {
        $className = ltrim($className, '\\');
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof DebugClassLoader) {
                $loader = $loader[0]->getClassLoader();
            }
            if (!is_array($loader)) {
                continue;
            }
            $file = match (true) {
                $loader[0] instanceof AopComposerLoader => $loader[0]->findSourceFile($className),
                $loader[0] instanceof ClassLoader       => $loader[0]->findFile($className),
                default                                 => false,
            };
            if ($file !== false) {
                $resolvedPath = PathResolver::realpath($file);

                return is_string($resolvedPath) ? $resolvedPath : false;
            }
        }

        return false;
    }
}
