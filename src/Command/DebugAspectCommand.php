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

use Go\Console\Command\DebugAspectCommand as BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Lists the aspects registered in the application with their pointcuts and advisors
 */
#[AsCommand(
    name: 'debug:aspect',
    description: 'Display the aspects of the application with their pointcuts and advisors',
    help: <<<'EOT'
    Lists the aspects registered in the application, with the pointcuts and advisors declared by each of them.

      <info>%command.full_name%</info>
      <info>%command.full_name% --aspect='App\Aspect\LoggingAspect'</info>

    With <comment>--aspect</comment> only that aspect is shown.
    EOT,
)]
final class DebugAspectCommand extends BaseCommand
{
    use RunningKernelCommandTrait;
}
