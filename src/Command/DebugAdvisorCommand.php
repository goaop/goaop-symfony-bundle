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

use Go\Console\Command\DebugAdvisorCommand as BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Lists the advisors of the application and the joinpoints they match
 */
#[AsCommand(
    name: 'debug:advisor',
    description: 'Display the advisors of the application and the joinpoints they match',
    help: <<<'EOT'
    Lists the advisors of the registered aspects.

      <info>%command.full_name%</info>
      <info>%command.full_name% --advisor='App\Aspect\LoggingAspect->logMethodCall'</info>

    With <comment>--advisor</comment> the joinpoints matched by that advisor are listed, which is the fastest way to
    check what a pointcut expression actually matches.
    EOT,
)]
final class DebugAdvisorCommand extends BaseCommand
{
    use RunningKernelCommandTrait;
}
