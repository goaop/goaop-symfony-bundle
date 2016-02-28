<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Symfony\GoAopBundle\Kernel;


use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Symfony\Component\Debug\DebugClassLoader;

class AspectSymfonyKernel extends AspectKernel
{
    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container)
    {
    }

    /**
     * Cache warmer in SF doesn't call Bundle::boot, so we need to duplicate this logic one more time
     *
     * @inheritDoc
     */
    public function init(array $options = [])
    {
        // it is a quick way to check if loader was enabled
        $wasDebugEnabled = class_exists(DebugClassLoader::class, false);
        if ($wasDebugEnabled) {
            // disable temporary to apply AOP loader first
            DebugClassLoader::disable();
        }
        parent::init($options);

        if (!AopComposerLoader::wasInitialized()) {
            throw new \RuntimeException("Initialization of AOP loader was failed, probably due to Debug::enable()");
        }
        if ($wasDebugEnabled) {
            DebugClassLoader::enable();
        }
    }
}
