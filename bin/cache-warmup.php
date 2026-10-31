<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/*
 * Warms up the Go! AOP cache of the kernel initialized by a bootstrap file, in a process of its own.
 *
 * The cache warmer of the bundle runs it when Symfony warms up a cache directory other than the one
 * the aspect kernel of the current process is bound to (cache:clear builds the new cache next to the
 * current one). It is also usable by hand:
 *
 *     php vendor/goaop/goaop-symfony-bundle/bin/cache-warmup.php var/cache/prod/aop/bootstrap.php
 *
 * The bootstrap file loads the Composer autoloader itself, so this script needs no autoload discovery.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

$bootstrapFile = $argv[1] ?? null;
if (!is_string($bootstrapFile) || !is_file($bootstrapFile)) {
    fwrite(STDERR, 'Usage: php cache-warmup.php <path to the bootstrap.php of the Go! AOP cache directory>' . PHP_EOL);
    exit(1);
}

$kernel = require $bootstrapFile;
if (!$kernel instanceof Go\Core\AspectKernel) {
    fwrite(STDERR, sprintf('The bootstrap file "%s" must return the aspect kernel.', $bootstrapFile) . PHP_EOL);
    exit(1);
}

$output = new Symfony\Component\Console\Output\ConsoleOutput();
$errors = new Go\Instrument\ClassLoading\CacheWarmer($kernel, $output)->warmUp();

exit($errors > 0 ? 1 : 0);
