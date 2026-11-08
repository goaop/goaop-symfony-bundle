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
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * Registers Composer's class loader again behind the Go! AOP loader that replaced it, for the container dumper
 *
 * The aspect kernel replaces Composer's ClassLoader in spl_autoload_functions() with AopComposerLoader. Symfony's
 * PhpDumper writes the preload file of the container only when it finds a ClassLoader there (directly or wrapped by
 * the DebugClassLoader): "cache:clear" over an existing cache boots the current kernel, and with it the aspect kernel,
 * before it dumps the new container in the same process, so the preload file was silently left out (#46).
 *
 * Behind the Go! AOP loader, Composer's loader never loads a class: the Go! AOP loader handles every class that
 * Composer finds, and returns nothing only when Composer's findFile() fails too (a miss, which Composer remembers).
 * It is only registered in the processes that build the cache, see GoAopBundle::boot().
 */
final class ComposerLoaderExposer
{
    private function __construct() {}

    /**
     * Appends the Composer loader of every Go! AOP loader to the autoloaders, unless it is registered already
     */
    public static function exposeBehindAopLoaders(): void
    {
        $registered = [];
        $originals  = [];
        foreach (spl_autoload_functions() as $autoloader) {
            if (!is_array($autoloader)) {
                continue;
            }
            $loader = $autoloader[0];
            if ($loader instanceof DebugClassLoader) {
                $wrapped = $loader->getClassLoader();
                $loader  = is_array($wrapped) ? $wrapped[0] : $wrapped;
            }
            if ($loader instanceof ClassLoader) {
                $registered[spl_object_id($loader)] = true;
            } elseif ($loader instanceof AopComposerLoader) {
                $original                             = $loader->getOriginalLoader();
                $originals[spl_object_id($original)] = $original;
            }
        }

        foreach (array_diff_key($originals, $registered) as $original) {
            // Appended, behind every loader of the process
            $original->register();
        }
    }
}
