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

namespace Go\Symfony\GoAopBundle\Command;

use Go\Console\Command\CacheWarmupCommand as BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Weaves every file below the include paths into the AOP cache of the application
 */
#[AsCommand(
    name: 'cache:warmup:aop',
    description: 'Warm up the Go! AOP cache with the woven classes of the application',
    help: <<<'EOT'
    Weaves every PHP file below the configured include paths into the Go! AOP cache directory of the
    application, so that no weaving happens at runtime.

      <info>%command.full_name%</info>
      <info>%command.full_name% --fail-fast</info>

    The command fails when a file cannot be woven; use <comment>--fail-fast</comment> to stop at the first error.
    The same warm-up runs as a cache warmer of <info>cache:warmup</info> and <info>cache:clear</info>.
    EOT,
)]
final class CacheWarmupCommand extends BaseCommand
{
    use RunningKernelCommandTrait;
}
