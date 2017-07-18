<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Command;

use Go\Core\AspectKernel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Go\Console\Command\CacheWarmupCommand as BaseCommand;

/**
 * Console command for warming the cache
 *
 * @codeCoverageIgnore
 */
class CacheWarmupCommand extends BaseCommand
{
    public function __construct(AspectKernel $aspectKernel)
    {
        parent::__construct(null);
        $this->aspectKernel = $aspectKernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $arguments = $this->getDefinition()->getArguments();
        unset($arguments['loader']);
        $this->getDefinition()->setArguments($arguments);
    }

    /**
     * {@inheritdoc}
     */
    protected function loadAspectKernel(InputInterface $input, OutputInterface $output)
    {
        /* noop */
    }
}
