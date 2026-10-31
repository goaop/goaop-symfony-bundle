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

namespace Go\Symfony\GoAopBundle\Command;

use Go\Console\Command\DebugWeavingCommand as BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Checks that the weaving of the application is deterministic
 */
#[AsCommand(
    name: 'debug:weaving',
    description: 'Check that the application is woven identically on two consecutive warm-ups',
    help: <<<'EOT'
    Warms the Go! AOP cache up twice and reports every proxy that differs between the two passes, which
    points to a non-deterministic aspect (for example an aspect depending on a class it advises).

      <info>%command.full_name%</info>
      <info>%command.full_name% -v</info>   also prints the difference of each unstable proxy

    The cache directory of the application is rewritten by the command.
    EOT,
)]
final class DebugWeavingCommand extends BaseCommand
{
    use RunningKernelCommandTrait;
}
