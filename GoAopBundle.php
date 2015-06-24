<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle;


use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Symfony\GoAopBundle\DependencyInjection\Compiler\AspectCollectorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GoAopBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $bundles     = $container->getParameter('kernel.bundles');
        $firstBundle = key($bundles);
        if ($firstBundle !== $this->name) {
            $message = "Please move the {$this->name} initialization to the top in your Kernel->init()";
            throw new \InvalidArgumentException($message);
        }

        $container->addCompilerPass(new AspectCollectorPass());
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->container->get('goaop.aspect.container');
        if (!AopComposerLoader::wasInitialized()) {
            throw new \RuntimeException("Initialization of AOP loader was failed, probably due to Debug::enable()");
        }
    }
}