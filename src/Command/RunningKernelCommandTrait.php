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

use Go\Core\AspectKernel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Binds a console command of the framework to the aspect kernel of the Symfony application.
 *
 * The framework commands boot the kernel by executing a "loader" file; inside a Symfony application
 * the kernel is already booted by the bundle, so the argument is dropped and the kernel injected.
 */
trait RunningKernelCommandTrait
{
    public function __construct(
        private readonly AspectKernel $runningAspectKernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $arguments = $this->getDefinition()->getArguments();
        unset($arguments['loader']);
        $this->getDefinition()->setArguments(array_values($arguments));
    }

    protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
    {
        $this->aspectKernel = $this->runningAspectKernel;
    }
}
