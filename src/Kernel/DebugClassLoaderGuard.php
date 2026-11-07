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

use Closure;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Lifts Symfony's DebugClassLoader for code that looks for the Composer loaders among the registered autoloaders.
 *
 * In debug mode the DebugClassLoader wraps every autoloader, which hides the Composer loader, and the Go! AOP loader
 * wrapping it, from the code of the framework that looks for them with spl_autoload_functions().
 *
 * @internal
 */
final class DebugClassLoaderGuard
{
    /**
     * Static helper, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Tells whether the DebugClassLoader wraps the autoloaders, without loading it
     */
    public static function isEnabled(): bool
    {
        if (!class_exists(DebugClassLoader::class, false)) {
            return false;
        }
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof DebugClassLoader) {
                return true;
            }
        }

        return false;
    }

    /**
     * Runs the callback with the DebugClassLoader lifted, when it is enabled, and puts it back on top afterwards
     *
     * @template T
     *
     * @param Closure(): T $callback
     *
     * @return T
     */
    public static function lifted(Closure $callback): mixed
    {
        $enabled = self::isEnabled();
        if ($enabled) {
            DebugClassLoader::disable();
        }
        try {
            return $callback();
        } finally {
            if ($enabled) {
                DebugClassLoader::enable();
            }
        }
    }
}
